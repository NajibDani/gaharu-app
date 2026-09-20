<?php

namespace App\Http\Controllers;

use App\Models\Penggajian;
use App\Models\Karyawan;
use App\Models\Keterlambatan;
use App\Models\Journal;
use App\Models\JournalItem;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class PenggajianController extends Controller
{
    /**
     * Helper untuk mendapatkan outlet terpilih berdasarkan role atau query/input parameter
     */
    private function getOutlet(Request $request): string
    {
        $user = auth()->user();
        $role = $user->role->nama ?? '';

        if ($role === 'Kepala Outlet Gaharu') {
            return 'Gaharu';
        }
        if ($role === 'Kepala Outlet Kejingga') {
            return 'Kejingga';
        }

        return $request->input('outlet') ?? $request->query('outlet', 'Gaharu');
    }

    /**
     * TAMPILAN UTAMA: Mengirimkan data penggajian yang sudah di-group berdasarkan periode dan outlet.
     */
    public function index(Request $request)
    {
        $search = $request->query('search');
        $selectedOutlet = $this->getOutlet($request);

        // Paginate by unique periods filtered by outlet
        $periodsQuery = Penggajian::select('periode_bulan_tahun')
            ->where(function ($q) use ($selectedOutlet) {
                $q->where('outlet', $selectedOutlet)
                  ->orWhereHas('karyawan', function ($kq) use ($selectedOutlet) {
                      $kq->where('outlet', $selectedOutlet);
                  });
            })
            ->groupBy('periode_bulan_tahun')
            ->orderBy('periode_bulan_tahun', 'desc');

        if ($search) {
            $periodsQuery->where(function($q) use ($search) {
                $q->where('periode_bulan_tahun', 'like', '%' . $search . '%')
                  ->orWhereHas('karyawan', function($kq) use ($search) {
                      $kq->where('nama_karyawan', 'like', '%' . $search . '%');
                  });
            });
        }

        $periods = $periodsQuery->paginate(10)->withQueryString();
        $periodNames = $periods->pluck('periode_bulan_tahun')->toArray();

        // Get all payrolls for the paginated periods and selected outlet
        $payrolls = Penggajian::with('karyawan')
            ->where(function ($q) use ($selectedOutlet) {
                $q->where('outlet', $selectedOutlet)
                  ->orWhereHas('karyawan', function ($kq) use ($selectedOutlet) {
                      $kq->where('outlet', $selectedOutlet);
                  });
            })
            ->whereIn('periode_bulan_tahun', $periodNames)
            ->orderBy('periode_bulan_tahun', 'desc')
            ->get();

        $karyawans = Karyawan::where('outlet', $selectedOutlet)->get();

        return view('penggajian.index', compact('payrolls', 'periods', 'karyawans', 'selectedOutlet'));
    }

    public function create(Request $request): View
    {
        $target_periode = $request->query('target_periode');
        $selectedOutlet = $this->getOutlet($request);
        $karyawan_id    = $request->query('karyawan_id');
        
        $alreadyPaidIds = Penggajian::where('periode_bulan_tahun', $target_periode)
            ->where(function ($q) use ($selectedOutlet) {
                $q->where('outlet', $selectedOutlet)
                  ->orWhereHas('karyawan', function ($kq) use ($selectedOutlet) {
                      $kq->where('outlet', $selectedOutlet);
                  });
            })
            ->pluck('karyawan_id')
            ->toArray();

        // Jika user sengaja menambah periode gaji untuk karyawan tertentu, jangan filter keluar ID karyawan tersebut
        if ($karyawan_id) {
            $alreadyPaidIds = array_diff($alreadyPaidIds, [(int) $karyawan_id, (string) $karyawan_id]);
        }
            
        $karyawans = Karyawan::where('outlet', $selectedOutlet)
            ->whereNotIn('id', $alreadyPaidIds)
            ->get();

        $lockedKaryawan = $karyawan_id ? Karyawan::find($karyawan_id) : null;
        if ($lockedKaryawan && !$karyawans->contains('id', $lockedKaryawan->id)) {
            $karyawans->push($lockedKaryawan);
        }

        // Ambil data akumulasi Keterlambatan bulan ini per karyawan
        $akumulasiTerlambatMap = Keterlambatan::whereRaw("DATE_FORMAT(tanggal, '%Y-%m') = ?", [$target_periode])
            ->groupBy('karyawan_id')
            ->selectRaw('karyawan_id, sum(potongan) as total_potongan, count(*) as total_kali')
            ->get()
            ->keyBy('karyawan_id');

        // Ambil data mentah keterlambatan untuk filter live di JavaScript sesuai tanggal slip
        $keterlambatanRawMap = Keterlambatan::whereRaw("DATE_FORMAT(tanggal, '%Y-%m') = ?", [$target_periode])
            ->select('id', 'karyawan_id', 'tanggal', 'potongan', 'durasi_menit')
            ->get()
            ->groupBy('karyawan_id');

        return view('penggajian.create', compact('karyawans', 'target_periode', 'akumulasiTerlambatMap', 'keterlambatanRawMap', 'selectedOutlet', 'lockedKaryawan'));
    }


    /**
     * MENYIMPAN GAJI PER KARYAWAN
     */
    /**
     * MENYIMPAN GAJI PER KARYAWAN BERDASARKAN RUMUS SPREADSHEET
     */
    public function store(Request $request)
    {
        $request->validate([
            'karyawan_id'             => 'required|exists:karyawan,id',
            'periode'                 => 'required|string|max:50',
            'tanggal_mulai'           => 'nullable|date',
            'tanggal_selesai'         => 'nullable|date|after_or_equal:tanggal_mulai',
            'hari_kerja'              => 'nullable|numeric|min:0',
            'jam_lembur'              => 'nullable|numeric|min:0',
            'banyak_target'           => 'nullable|integer|min:0',
            'banyak_tanggal_merah'    => 'nullable|integer|min:0',
            'banyak_birthday_service' => 'nullable|integer|min:0',
            'bonus_dll'               => 'nullable|string',
            'potongan_terlambat'      => 'nullable|string',
            'potongan_inventaris'     => 'nullable|string',
            'potongan_kasbon'         => 'nullable|string',
            'potongan_dll'            => 'nullable|string',
        ]);

        // Cek duplikat identik: izinkan jika pilihan periode atau rentang tanggal berbeda
        $duplicateQuery = Penggajian::where('karyawan_id', $request->karyawan_id)
            ->where('periode_bulan_tahun', $request->periode)
            ->where('hari_kerja', '>', 0);

        if ($request->filled('tanggal_mulai') && $request->filled('tanggal_selesai')) {
            $duplicateQuery->where(function ($q) use ($request) {
                $q->where(function ($q2) use ($request) {
                    $q2->where('tanggal_mulai', '<=', $request->tanggal_selesai)
                       ->where('tanggal_selesai', '>=', $request->tanggal_mulai);
                });
            });
            if ($duplicateQuery->exists()) {
                return back()->withErrors(['karyawan_id' => 'Gaji karyawan ini untuk rentang tanggal tersebut sudah pernah diinput sebelumnya. Silakan gunakan rentang tanggal yang berbeda atau edit data yang ada.'])->withInput();
            }
        }

        $karyawan = Karyawan::findOrFail($request->karyawan_id);

        $cleanRupiah = function ($value) {
            if (is_null($value)) return 0;
            return (float) preg_replace('/[^0-9.]/', '', str_replace(',', '.', $value));
        };

        // 1. Data Dasar Berdasarkan Pilihan Periode Gaji (Periode 1 vs Periode 2)
        $pilihanPeriode = intval($request->pilihan_periode ?? 1);
        if ($pilihanPeriode === 2 && $karyawan->gaji_pokok_2 !== null) {
            $gajiPokokHarian  = floatval($karyawan->gaji_pokok_2);
            $uangMakan        = floatval($karyawan->uang_makan_2);
            $uangTransport    = floatval($karyawan->uang_transport_2);
            $satuanGaji       = $karyawan->satuan_gaji_2 ?? $karyawan->satuan_gaji ?? 'Harian';
        } else {
            $pilihanPeriode   = 1;
            $gajiPokokHarian  = floatval($karyawan->gaji_pokok);
            $uangMakan        = floatval($karyawan->uang_makan);
            $uangTransport    = floatval($karyawan->uang_transport);
            $satuanGaji       = $karyawan->satuan_gaji ?? 'Harian';
        }

        $tarifHarianTotal = $gajiPokokHarian + $uangMakan + $uangTransport;
        $hariKerja        = floatval($request->hari_kerja ?? 0);

        if ($satuanGaji === 'Bulanan') {
            $gajiUtama = $tarifHarianTotal;
        } else {
            $gajiUtama = $hariKerja * $tarifHarianTotal;
        }

        // 2. Presensi & Kinerja
        $jamLembur              = floatval($request->jam_lembur ?? 0);
        $banyakTarget           = intval($request->banyak_target ?? 0);
        $banyakTanggalMerah     = intval($request->banyak_tanggal_merah ?? 0);
        $banyakBirthdayService  = intval($request->banyak_birthday_service ?? 0);

        // 3. Kalkulasi Earnings (Pendapatan)
        $lembur               = $jamLembur * 10000;
        $bonusBirthdayService = $banyakBirthdayService * 5000;
        $bonusDll             = $request->has('bonus_dll') ? $cleanRupiah($request->bonus_dll) : 0;

        if ($satuanGaji === 'Harian') {
            $bonusTarget          = $banyakTarget * $tarifHarianTotal;
            $bonusTanggalMerah    = $banyakTanggalMerah * $tarifHarianTotal;
            $catatanTarget        = null;
            $catatanTanggalMerah  = null;
        } else {
            $bonusTarget          = $request->filled('manual_bonus_target') ? $cleanRupiah($request->manual_bonus_target) : ($banyakTarget > 0 ? $banyakTarget * $tarifHarianTotal : $cleanRupiah($request->bonus_target ?? 0));
            $bonusTanggalMerah    = $request->filled('manual_bonus_tanggal_merah') ? $cleanRupiah($request->manual_bonus_tanggal_merah) : ($banyakTanggalMerah > 0 ? $banyakTanggalMerah * $tarifHarianTotal : $cleanRupiah($request->bonus_tanggal_merah ?? 0));
            $catatanTarget        = $request->catatan_bonus_target;
            $catatanTanggalMerah  = $request->catatan_bonus_tanggal_merah;
        }

        $totalEarnings = $gajiUtama + $lembur + $bonusTarget + $bonusTanggalMerah + $bonusBirthdayService + $bonusDll;

        // 4. Kalkulasi Deductions (Pengurangan) - Otomatis sinkronkan potongan terlambat dari tabel Keterlambatan jika tidak diset
        if ($request->has('potongan_terlambat')) {
            $potonganTerlambat = $cleanRupiah($request->potongan_terlambat);
        } else {
            $qLate = Keterlambatan::where('karyawan_id', $karyawan->id);
            if ($request->tanggal_mulai && $request->tanggal_selesai) {
                $qLate->whereBetween('tanggal', [$request->tanggal_mulai, $request->tanggal_selesai]);
            } else {
                $qLate->whereRaw("DATE_FORMAT(tanggal, '%Y-%m') = ?", [$request->periode]);
            }
            $potonganTerlambat = floatval($qLate->sum('potongan'));
        }

        $potonganInventaris = $request->has('potongan_inventaris') ? $cleanRupiah($request->potongan_inventaris) : 0;
        $potonganKasbon     = $request->has('potongan_kasbon') ? $cleanRupiah($request->potongan_kasbon) : 0;
        $potonganDll        = $request->has('potongan_dll') ? $cleanRupiah($request->potongan_dll) : 0;

        $totalDeductions = $potonganTerlambat + $potonganInventaris + $potonganKasbon + $potonganDll;

        // 5. Gaji Bersih (Take Home Pay)
        $totalGajiBersih = $totalEarnings - $totalDeductions;

        $existingStatus = Penggajian::where('periode_bulan_tahun', $request->periode)->first()?->status ?? 'draft';

        // Jika ada record draft hasil auto-fill dengan hari_kerja = 0 untuk karyawan dan periode yang sama, timpa/hapus record draft tersebut agar tidak duplikat
        $emptyDraft = Penggajian::where('karyawan_id', $karyawan->id)
            ->where('periode_bulan_tahun', $request->periode)
            ->where('hari_kerja', 0)
            ->where('status', 'draft')
            ->where('status_jurnal', false)
            ->first();

        if ($emptyDraft) {
            $emptyDraft->delete();
        }

        Penggajian::create([
            'karyawan_id'                 => $karyawan->id,
            'outlet'                      => $karyawan->outlet ?? 'Gaharu',
            'satuan_gaji'                 => $satuanGaji,
            'satuan_gaji_2'               => $karyawan->satuan_gaji_2 ?? $satuanGaji,
            'pilihan_periode'             => $pilihanPeriode,
            'periode_bulan_tahun'         => $request->periode,
            'tanggal_mulai'               => $request->tanggal_mulai,
            'tanggal_selesai'             => $request->tanggal_selesai,
            'hari_kerja'                  => $hariKerja,
            'tarif_harian_total'          => $tarifHarianTotal,
            'gaji_utama'                  => $gajiUtama,
            'gaji_pokok'                  => $gajiPokokHarian,
            'tunjangan_transport'         => $uangTransport,
            'tunjangan_makan'             => $uangMakan,
            'jam_lembur'                  => $jamLembur,
            'lembur'                      => $lembur,
            'banyak_target'               => $banyakTarget,
            'bonus_target'                => $bonusTarget,
            'catatan_bonus_target'        => $catatanTarget,
            'banyak_tanggal_merah'        => $banyakTanggalMerah,
            'bonus_tanggal_merah'         => $bonusTanggalMerah,
            'catatan_bonus_tanggal_merah' => $catatanTanggalMerah,
            'banyak_birthday_service'     => $banyakBirthdayService,
            'bonus_birthday'              => $bonusBirthdayService,
            'bonus_dll'                   => $bonusDll,
            'potongan_terlambat'          => $potonganTerlambat,
            'potongan_inventaris'         => $potonganInventaris,
            'potongan_kasbon'             => $potonganKasbon,
            'potongan_dll'                => $potonganDll,
            'total_earnings'              => $totalEarnings,
            'total_deductions'            => $totalDeductions,
            'total_gaji_bersih'           => $totalGajiBersih,
            'status'                      => $existingStatus,
            'status_jurnal'               => false
        ]);

        return redirect()->route('penggajian.show-periode', ['periode' => $request->periode, 'outlet' => $karyawan->outlet ?? 'Gaharu'])
            ->with('success', "Data gaji ({$satuanGaji} Periode {$pilihanPeriode}) untuk {$karyawan->nama_karyawan} berhasil ditambahkan ke periode.");
    }


    /**
     * HALAMAN BARU: Menampilkan daftar karyawan khusus pada periode tertentu (Hasil klik tombol Detail Karyawan)
     */
    public function periodeDetail(Request $request)
    {
        $periode = $request->query('periode');
        $selectedOutlet = $this->getOutlet($request);

        // Ambil semua data karyawan yang ada di periode & outlet ini
        $rawPayrolls = Penggajian::with('karyawan')
            ->where('periode_bulan_tahun', $periode)
            ->where(function ($q) use ($selectedOutlet) {
                $q->where('outlet', $selectedOutlet)
                  ->orWhereHas('karyawan', function ($kq) use ($selectedOutlet) {
                      $kq->where('outlet', $selectedOutlet);
                  });
            })
            ->get();

        // Bersihkan duplikat otomatis di database: jika ada karyawan yang punya slip aktif (hari_kerja > 0)
        // dan juga punya slip kosong (hari_kerja == 0 & draft), gabungkan potongan terlambat ke slip aktif dan hapus slip kosong
        $groupedByKaryawan = $rawPayrolls->groupBy('karyawan_id');
        foreach ($groupedByKaryawan as $empId => $items) {
            if ($items->count() > 1) {
                $activeItem = $items->where('hari_kerja', '>', 0)->sortByDesc('id')->first();
                $zeroItems = $items->where('hari_kerja', '<=', 0)->where('status', 'draft')->where('status_jurnal', false);
                if ($activeItem && $zeroItems->isNotEmpty()) {
                    $extraLate = $zeroItems->sum('potongan_terlambat');
                    $zeroItemsIds = $zeroItems->pluck('id')->toArray();
                    Penggajian::whereIn('id', $zeroItemsIds)->delete();
                    
                    // Re-query needed if items deleted
                    $rawPayrolls = $rawPayrolls->reject(fn($p) => in_array($p->id, $zeroItemsIds));
                }
            }
        }

        // Otomatis sinkronkan potongan keterlambatan untuk slip draft / waiting approval di periode ini
        foreach ($rawPayrolls as $payroll) {
            if ($payroll->status !== 'approved') {
                $pMulai = $payroll->tanggal_mulai ? \Carbon\Carbon::parse($payroll->tanggal_mulai)->format('Y-m-d') : null;
                $pSelesai = $payroll->tanggal_selesai ? \Carbon\Carbon::parse($payroll->tanggal_selesai)->format('Y-m-d') : null;

                $qLate = Keterlambatan::where('karyawan_id', $payroll->karyawan_id);
                if ($pMulai && $pSelesai) {
                    $qLate->whereBetween('tanggal', [$pMulai, $pSelesai]);
                } else {
                    $qLate->whereRaw("DATE_FORMAT(tanggal, '%Y-%m') = ?", [$payroll->periode_bulan_tahun]);
                }
                $potonganTerlambat = (float) $qLate->sum('potongan');

                $earnings = (float) ($payroll->total_earnings > 0 ? $payroll->total_earnings : (
                    ($payroll->gaji_utama ?? 0) + ($payroll->lembur ?? 0) + ($payroll->bonus_target ?? 0) +
                    ($payroll->bonus_tanggal_merah ?? 0) + ($payroll->bonus_birthday ?? 0) + ($payroll->bonus_dll ?? 0)
                ));

                $deductions = $potonganTerlambat +
                              floatval($payroll->potongan_inventaris ?? 0) +
                              floatval($payroll->potongan_kasbon ?? 0) +
                              floatval($payroll->potongan_dll ?? 0);

                $thp = $earnings - $deductions;

                if ($payroll->potongan_terlambat != $potonganTerlambat || $payroll->total_deductions != $deductions || $payroll->total_gaji_bersih != $thp) {
                    $payroll->update([
                        'potongan_terlambat' => $potonganTerlambat,
                        'total_deductions'   => $deductions,
                        'total_gaji_bersih'  => $thp,
                    ]);
                    $payroll->potongan_terlambat = $potonganTerlambat;
                    $payroll->total_deductions = $deductions;
                    $payroll->total_gaji_bersih = $thp;
                }
            }
        }

        // Grouping: 1 BARIS PER KARYAWAN
        $payrolls = $rawPayrolls->groupBy('karyawan_id')->map(function ($items) {
            $first = $items->first();
            $primaryPayroll = $items->sortByDesc('hari_kerja')->first() ?? $first;
            
            $totalHariKerja = $items->sum('hari_kerja');
            $totalGajiUtama = $items->sum('gaji_utama');
            $totalLembur = $items->sum('lembur');
            $totalJamLembur = $items->sum('jam_lembur');
            $totalTarget = $items->sum('bonus_target');
            $totalBanyakTarget = $items->sum('banyak_target');
            $totalMerah = $items->sum('bonus_tanggal_merah');
            $totalBanyakMerah = $items->sum('banyak_tanggal_merah');
            $totalBirthday = $items->sum('bonus_birthday');
            $totalBanyakBirthday = $items->sum('banyak_birthday_service');
            $totalBonusDll = $items->sum('bonus_dll');

            $totalEarnings = $items->sum(function($p) {
                return $p->total_earnings > 0 ? (float)$p->total_earnings : (
                    (float)($p->gaji_utama ?? 0) + (float)($p->lembur ?? 0) + (float)($p->bonus_target ?? 0) +
                    (float)($p->bonus_tanggal_merah ?? 0) + (float)($p->bonus_birthday ?? 0) + (float)($p->bonus_dll ?? 0)
                );
            });

            $totalPotonganTerlambat = $items->sum('potongan_terlambat');
            $totalPotonganInventaris = $items->sum('potongan_inventaris');
            $totalPotonganKasbon = $items->sum('potongan_kasbon');
            $totalPotonganDll = $items->sum('potongan_dll');

            $totalDeductions = $items->sum(function($p) {
                return $p->total_deductions > 0 ? (float)$p->total_deductions : (
                    (float)($p->potongan_terlambat ?? 0) + (float)($p->potongan_inventaris ?? 0) +
                    (float)($p->potongan_kasbon ?? 0) + (float)($p->potongan_dll ?? 0)
                );
            });

            $takeHomePay = $totalEarnings - $totalDeductions;
            $isPaid = $items->every(fn($p) => $p->status_jurnal || $p->status === 'approved');

            // Hitung tarif harian representatif
            $tarifHarian = $primaryPayroll->tarif_harian_total > 0
                ? $primaryPayroll->tarif_harian_total
                : (($primaryPayroll->gaji_pokok ?? 0) + ($primaryPayroll->tunjangan_makan ?? 0) + ($primaryPayroll->tunjangan_transport ?? 0));

            return (object) [
                'id'                      => $primaryPayroll->id,
                'primary_payroll'         => $primaryPayroll,
                'items'                   => $items,
                'karyawan_id'             => $first->karyawan_id,
                'karyawan'                => $first->karyawan,
                'outlet'                  => $first->outlet,
                'periode_bulan_tahun'     => $first->periode_bulan_tahun,
                'hari_kerja'              => $totalHariKerja,
                'tarif_harian_total'      => $tarifHarian,
                'gaji_utama'              => $totalGajiUtama,
                'gaji_pokok'              => $primaryPayroll->gaji_pokok,
                'tunjangan_transport'     => $primaryPayroll->tunjangan_transport,
                'tunjangan_makan'         => $primaryPayroll->tunjangan_makan,
                'jam_lembur'              => $totalJamLembur,
                'lembur'                  => $totalLembur,
                'banyak_target'           => $totalBanyakTarget,
                'bonus_target'            => $totalTarget,
                'banyak_tanggal_merah'    => $totalBanyakMerah,
                'bonus_tanggal_merah'     => $totalMerah,
                'banyak_birthday_service' => $totalBanyakBirthday,
                'bonus_birthday'          => $totalBirthday,
                'bonus_dll'               => $totalBonusDll,
                'potongan_terlambat'      => $totalPotonganTerlambat,
                'potongan_inventaris'     => $totalPotonganInventaris,
                'potongan_kasbon'         => $totalPotonganKasbon,
                'potongan_dll'            => $totalPotonganDll,
                'total_earnings'          => $totalEarnings,
                'total_deductions'        => $totalDeductions,
                'total_gaji_bersih'       => $takeHomePay,
                'take_home_pay'           => $takeHomePay,
                'status'                  => $primaryPayroll->status,
                'status_jurnal'           => $isPaid,
                'is_paid'                 => $isPaid,
                'tanggal_mulai'           => $primaryPayroll->tanggal_mulai,
                'tanggal_selesai'         => $primaryPayroll->tanggal_selesai,
                'satuan_gaji'             => $primaryPayroll->satuan_gaji,
                'satuan_gaji_2'           => $primaryPayroll->satuan_gaji_2,
                'pilihan_periode'         => $primaryPayroll->pilihan_periode ?? 1,
            ];
        })->values();

        if ($payrolls->isEmpty()) {
            $currentStatus = 'draft';
        } else {
            $currentStatus = $payrolls->first()->status;
        }

        $payrollCounts = $rawPayrolls->groupBy('karyawan_id')->map->count();
        $allKaryawans = Karyawan::where('outlet', $selectedOutlet)->get()->map(function($k) use ($payrollCounts) {
            $k->payroll_count = $payrollCounts[$k->id] ?? 0;
            return $k;
        });
        $availableKaryawans = $allKaryawans;

        return view('penggajian.show-periode', compact('payrolls', 'periode', 'currentStatus', 'selectedOutlet', 'availableKaryawans', 'allKaryawans'));
    }

    /**
     * EXPORT EXCEL: Data Transfer Gaji Karyawan (Format Payroll Bank)
     * Kolom: REKENING | NOMINAL | EMAIL
     * Rekening & Nominal: tanpa titik, spasi, atau tanda baca
     */
    public function exportPayrollExcel(Request $request)
    {
        $periode = $request->query('periode');
        $selectedOutlet = $this->getOutlet($request);

        if (!$periode) {
            return back()->with('error', 'Periode tidak valid.');
        }

        // Ambil semua penggajian untuk periode & outlet ini
        $rawPayrolls = Penggajian::with('karyawan')
            ->where('periode_bulan_tahun', $periode)
            ->where(function ($q) use ($selectedOutlet) {
                $q->where('outlet', $selectedOutlet)
                  ->orWhereHas('karyawan', function ($kq) use ($selectedOutlet) {
                      $kq->where('outlet', $selectedOutlet);
                  });
            })
            ->get();

        // Group per karyawan, hitung take home pay total
        $rows = $rawPayrolls->groupBy('karyawan_id')->map(function ($items) {
            $first = $items->first();
            $karyawan = $first->karyawan;

            $totalEarnings = $items->sum(function($p) {
                return $p->total_earnings > 0 ? (float)$p->total_earnings : (
                    (float)($p->gaji_utama ?? 0) + (float)($p->lembur ?? 0) +
                    (float)($p->bonus_target ?? 0) + (float)($p->bonus_tanggal_merah ?? 0) +
                    (float)($p->bonus_birthday ?? 0) + (float)($p->bonus_dll ?? 0)
                );
            });

            $totalDeductions = $items->sum(function($p) {
                return $p->total_deductions > 0 ? (float)$p->total_deductions : (
                    (float)($p->potongan_terlambat ?? 0) + (float)($p->potongan_inventaris ?? 0) +
                    (float)($p->potongan_kasbon ?? 0) + (float)($p->potongan_dll ?? 0)
                );
            });

            $takeHomePay = $totalEarnings - $totalDeductions;

            // Bersihkan nomor rekening: hanya digit, hapus semua non-digit
            $rekening = preg_replace('/\D/', '', $karyawan->no_rekening ?? '');

            // Nominal tanpa desimal, tanpa titik/koma/spasi
            $nominal = (int) round($takeHomePay);

            return [
                'rekening' => $rekening,
                'nominal'  => $nominal,
                'email'    => $karyawan->email ?? '',
                'nama'     => $karyawan->nama_karyawan ?? '-',
            ];
        })->values()->filter(fn($r) => $r['rekening'] !== '' && $r['nominal'] > 0);

        // Build Excel
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Header row
        $sheet->setCellValue('A1', 'REKENING');
        $sheet->setCellValue('B1', 'NOMINAL');
        $sheet->setCellValue('C1', 'EMAIL');

        // Style header: bold
        $sheet->getStyle('A1:C1')->getFont()->setBold(true);
        $sheet->getStyle('A1:C1')->getFill()
              ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
              ->getStartColor()->setARGB('FFE2E8F0');

        // Data rows
        $rowNum = 2;
        foreach ($rows as $row) {
            // Set rekening as text to prevent scientific notation
            $sheet->setCellValueExplicit(
                'A' . $rowNum,
                $row['rekening'],
                \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING
            );
            // Nominal as plain integer (no formatting in cell)
            $sheet->setCellValueExplicit(
                'B' . $rowNum,
                (string) $row['nominal'],
                \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING
            );
            $sheet->setCellValue('C' . $rowNum, $row['email']);
            $rowNum++;
        }

        // Auto-size columns
        foreach (['A', 'B', 'C'] as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        // Nama file: Transfer_Gaji_Outlet_Periode.xlsx
        $periodeFormatted = \App\Models\Penggajian::formatPeriode($periode);
        $filename = 'Transfer_Gaji_' . $selectedOutlet . '_' . str_replace(' ', '_', $periodeFormatted) . '.xlsx';

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $filename, [
            'Content-Type'        => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Cache-Control'       => 'max-age=0',
        ]);
    }

    /**
     * AUTO-FILL SEMUA KARYAWAN AKTIF KE DALAM PERIODE BERDASARKAN TARIF HARIAN
     */
    public function autoFill(Request $request)
    {

        $periode = $request->input('periode');
        $selectedOutlet = $this->getOutlet($request);

        if (!$periode) {
            return back()->with('error', 'Periode penggajian tidak valid.');
        }

        $alreadyPaidIds = Penggajian::where('periode_bulan_tahun', $periode)
            ->where(function ($q) use ($selectedOutlet) {
                $q->where('outlet', $selectedOutlet)
                  ->orWhereHas('karyawan', function ($kq) use ($selectedOutlet) {
                      $kq->where('outlet', $selectedOutlet);
                  });
            })
            ->pluck('karyawan_id')
            ->toArray();

        $karyawans = Karyawan::where('outlet', $selectedOutlet)
            ->whereNotIn('id', $alreadyPaidIds)
            ->get();

        if ($karyawans->isEmpty()) {
            return back()->with('info', "Semua karyawan aktif outlet {$selectedOutlet} sudah terdaftar pada periode ini.");
        }

        $existingStatus = Penggajian::where('periode_bulan_tahun', $periode)
            ->where('outlet', $selectedOutlet)
            ->first()?->status ?? 'draft';

        $count = 0;
        foreach ($karyawans as $k) {
            $satuanGaji = $k->satuan_gaji ?? 'Harian';
            $gajiPokok = floatval($k->gaji_pokok ?? 0);
            $uangMakan = floatval($k->uang_makan ?? 0);
            $uangTransport = floatval($k->uang_transport ?? 0);
            $tarifHarian = $gajiPokok + $uangMakan + $uangTransport;

            // Otomatis hitung akumulasi denda keterlambatan bulan ini dari tabel Keterlambatan
            $potonganTerlambat = Keterlambatan::where('karyawan_id', $k->id)
                ->whereRaw("DATE_FORMAT(tanggal, '%Y-%m') = ?", [$periode])
                ->sum('potongan');

            $totalDeductions = floatval($potonganTerlambat);
            $gajiUtama = ($satuanGaji === 'Bulanan') ? $tarifHarian : 0;
            $totalEarnings = $gajiUtama;
            $totalGajiBersih = $totalEarnings - $totalDeductions;

            Penggajian::create([
                'karyawan_id'             => $k->id,
                'outlet'                  => $k->outlet ?? $selectedOutlet,
                'satuan_gaji'             => $satuanGaji,
                'satuan_gaji_2'           => $k->satuan_gaji_2 ?? $satuanGaji,
                'periode_bulan_tahun'     => $periode,
                'hari_kerja'              => 0,
                'tarif_harian_total'      => $tarifHarian,
                'gaji_utama'              => $gajiUtama,
                'gaji_pokok'              => $gajiPokok,
                'tunjangan_transport'     => $uangTransport,
                'tunjangan_makan'         => $uangMakan,
                'jam_lembur'              => 0,
                'lembur'                  => 0,
                'banyak_target'           => 0,
                'bonus_target'            => 0,
                'banyak_tanggal_merah'    => 0,
                'bonus_tanggal_merah'     => 0,
                'banyak_birthday_service' => 0,
                'bonus_birthday'          => 0,
                'bonus_dll'               => 0,
                'potongan_terlambat'      => $potonganTerlambat,
                'potongan_inventaris'     => 0,
                'potongan_kasbon'         => 0,
                'potongan_dll'            => 0,
                'total_earnings'          => $totalEarnings,
                'total_deductions'        => $totalDeductions,
                'total_gaji_bersih'       => $totalGajiBersih,
                'status'                  => $existingStatus,
                'status_jurnal'           => false,
            ]);
            $count++;
        }


        return redirect()->route('penggajian.show-periode', ['periode' => $periode, 'outlet' => $selectedOutlet])
            ->with('success', "Berhasil meng-autofill {$count} karyawan outlet {$selectedOutlet} ke periode {$periode}. Silakan sesuaikan jumlah hari kerja & komponen presensi.");
    }


    /**
     * PROSES AJUKAN APPROVAL (DARI DROPDOWN TITIK TIGA)
     */
    public function ajukanApproval(Request $request)
    {
        $periode = $request->periode;

        Penggajian::where('periode_bulan_tahun', $periode)
            ->where('status', 'draft')
            ->update(['status' => 'waiting approval']);

        return redirect()->back()->with('success', "Periode $periode berhasil diajukan ke Direktur Keuangan.");
    }

    /**
     * PROSES APPROVE DIREKTUR (DARI DROPDOWN TITIK TIGA)
     */
    public function approve(Request $request)
    {
        $periode = $request->periode;

        Penggajian::where('periode_bulan_tahun', $periode)
            ->where('status', 'waiting approval')
            ->update(['status' => 'approved']);

        return redirect()->back()->with('success', "Periode $periode telah berhasil disetujui (Approved).");
    }

    /**
     * PROSES POSTING JURNAL (DARI DROPDOWN TITIK TIGA)
     */
    public function kirimJurnalUmum(Request $request)
    {
    $periode = $request->periode; // Asumsi format periode misal: "2026-07" atau "2026-07-01"

    // 1. Ambil data penggajian
    $payrolls = Penggajian::where('periode_bulan_tahun', $periode)
        ->where('status', 'approved')
        ->where('status_jurnal', false)
        ->get();

    if ($payrolls->isEmpty()) {
        return redirect()->back()->with('error', 'Tidak ada data yang siap dijurnal atau periode sudah dijurnal.');
    }

    $totalGajiBersih = $payrolls->sum('total_gaji_bersih');

    // 2. Pencarian akun COA secara spesifik
    $akunBebanGaji = \App\Models\ChartOfAccount::where('kode', '6101')->first()
        ?? \App\Models\ChartOfAccount::where('kode', '6100')->first()
        ?? \App\Models\ChartOfAccount::where('nama', 'like', '%Beban Gaji%')->first();

    $akunKas = \App\Models\ChartOfAccount::where('kode', '1101')->first()
        ?? \App\Models\ChartOfAccount::where('kode', '1100')->first()
        ?? \App\Models\ChartOfAccount::where('nama', 'like', '%Kas di Bank%')->first();

    if (!$akunBebanGaji || !$akunKas) {
        return redirect()->back()->with('error', 'Gagal memposting. Akun Beban Gaji atau Kas tidak ditemukan di Chart of Accounts.');
    }

    // ====================================================================
    // LOGIKA TANGGAL OTOMATIS TANGGAL 25
    // ====================================================================
    // Mengubah variabel $periode menjadi tanggal 25 di bulan dan tahun periode tersebut
    // Contoh: jika $periode = "2026-07", maka $tanggalJurnal = "2026-07-25"
    $tanggalJurnal = \Carbon\Carbon::parse($periode)->setDateFrom(\Carbon\Carbon::parse($periode))->day(25)->toDateString();

    // 3. Eksekusi DB Transaction
    DB::transaction(function () use ($periode, $totalGajiBersih, $akunBebanGaji, $akunKas, $tanggalJurnal) {

        // Buat Header Jurnal
        $journal = Journal::create([
            'tanggal'     => $tanggalJurnal, // Menggunakan tanggal 25 yang sudah di-generate
            'deskripsi'   => "Pencatatan beban gaji karyawan periode " . $periode,
            'no_ref'      => 'JV-' . strtoupper(str_replace('-', '', $periode)) . '-' . rand(10, 99),
            'source_type' => 'jurnal_umum',
            'source_id'   => 0,
            'created_by'  => auth()->id() ?? 1,
            'status'      => 'approved', // Langsung approved (posted), tidak perlu draft
        ]);

        // Item baris DEBIT (Beban Gaji)
        JournalItem::create([
            'journal_id'   => $journal->id,
            'account_id'   => $akunBebanGaji->id,
            'debit'        => $totalGajiBersih,
            'kredit'       => 0,
            'journal_type' => 'jurnal_umum',
        ]);

        // Item baris KREDIT (Kas)
        JournalItem::create([
            'journal_id'   => $journal->id,
            'account_id'   => $akunKas->id,
            'debit'        => 0,
            'kredit'       => $totalGajiBersih,
            'journal_type' => 'jurnal_umum',
        ]);

        // Kunci status penggajian
        Penggajian::where('periode_bulan_tahun', $periode)
            ->where('status', 'approved')
            ->update([
                'status_jurnal' => true,
                'journal_id'    => $journal->id
            ]);
    });

    return redirect()->back()->with('success', "Total gaji periode $periode berhasil diposting dengan tanggal 25 ke Jurnal Umum.");
    }

    public function destroy(Penggajian $penggajian): RedirectResponse
    {
        if ($penggajian->status !== 'draft') {
            return redirect()->back()->with('error', 'Data tidak bisa dihapus karena sudah dalam proses approval.');
        }

        $penggajian->delete();
        return redirect()->back()->with('success', 'Data gaji karyawan berhasil dihapus.');
    }

    /**
     * Menampilkan form edit gaji untuk satu orang karyawan
     */
    /**
     * Mengarahkan mode edit ke halaman create dengan membawa data lama (Reusable Form)
     */
    public function edit($id): View
    {
        // 1. Ambil data penggajian yang ingin diedit
        $payroll = Penggajian::with('karyawan')->findOrFail($id);

        // 2. Proteksi: Jika sudah approved, tidak boleh diubah
        if ($payroll->status === 'approved') {
            return redirect()->back()->with('error', 'Data tidak bisa diedit karena periode ini sudah disetujui.');
        }

        // 3. Ambil semua data karyawan untuk dropdown
        $karyawans = Karyawan::all();

        // 4. Ambil target periode dari data lama agar form tahu periodenya
        $target_periode = $payroll->periode_bulan_tahun;

        // Ambil data akumulasi Keterlambatan bulan ini per karyawan
        $akumulasiTerlambatMap = Keterlambatan::whereRaw("DATE_FORMAT(tanggal, '%Y-%m') = ?", [$target_periode])
            ->groupBy('karyawan_id')
            ->selectRaw('karyawan_id, sum(potongan) as total_potongan, count(*) as total_kali')
            ->get()
            ->keyBy('karyawan_id');

        // 5. Ambil selectedOutlet
        $selectedOutlet = $payroll->outlet ?? $payroll->karyawan->outlet ?? 'Gaharu';

        // 6. BELOKKAN KE VIEW CREATE (Membawa variabel $payroll data lama)
        return view('penggajian.create', compact('payroll', 'karyawans', 'target_periode', 'akumulasiTerlambatMap', 'selectedOutlet'));
    }

    /**
     * Memproses pembaharuan nominal gaji yang diedit oleh HRD
     */
    public function update(Request $request, $id): RedirectResponse
    {
        $payroll = Penggajian::findOrFail($id);

        if ($payroll->status === 'approved') {
            return redirect()->back()->with('error', 'Perubahan ditolak karena periode sudah dikunci.');
        }

        $request->validate([
            'tanggal_mulai'           => 'nullable|date',
            'tanggal_selesai'         => 'nullable|date|after_or_equal:tanggal_mulai',
            'hari_kerja'              => 'nullable|numeric|min:0',
            'jam_lembur'              => 'nullable|numeric|min:0',
            'banyak_target'           => 'nullable|integer|min:0',
            'banyak_tanggal_merah'    => 'nullable|integer|min:0',
            'banyak_birthday_service' => 'nullable|integer|min:0',
            'bonus_dll'               => 'nullable|string',
            'potongan_terlambat'      => 'nullable|string',
            'potongan_inventaris'     => 'nullable|string',
            'potongan_kasbon'         => 'nullable|string',
            'potongan_dll'            => 'nullable|string',
        ]);

        $karyawan = Karyawan::findOrFail($payroll->karyawan_id);

        $cleanRupiah = function ($value) {
            if (is_null($value)) return 0;
            return (float) preg_replace('/[^0-9.]/', '', str_replace(',', '.', $value));
        };

        // 1. Data Dasar Berdasarkan Pilihan Periode Gaji (Periode 1 vs Periode 2)
        $pilihanPeriode = intval($request->pilihan_periode ?? ($payroll->pilihan_periode ?? 1));
        if ($pilihanPeriode === 2 && $karyawan && $karyawan->gaji_pokok_2 !== null) {
            $gajiPokokHarian  = floatval($karyawan->gaji_pokok_2);
            $uangMakan        = floatval($karyawan->uang_makan_2);
            $uangTransport    = floatval($karyawan->uang_transport_2);
            $satuanGaji       = $karyawan->satuan_gaji_2 ?? $karyawan->satuan_gaji ?? 'Harian';
        } else {
            $pilihanPeriode   = 1;
            $gajiPokokHarian  = floatval($karyawan->gaji_pokok ?? $payroll->gaji_pokok);
            $uangMakan        = floatval($karyawan->uang_makan ?? $payroll->tunjangan_makan);
            $uangTransport    = floatval($karyawan->uang_transport ?? $payroll->tunjangan_transport);
            $satuanGaji       = $karyawan->satuan_gaji ?? $payroll->satuan_gaji ?? 'Harian';
        }

        $tarifHarianTotal = $gajiPokokHarian + $uangMakan + $uangTransport;
        $hariKerja        = floatval($request->hari_kerja ?? $payroll->hari_kerja);

        if ($satuanGaji === 'Bulanan') {
            $gajiUtama = $tarifHarianTotal;
        } else {
            $gajiUtama = $hariKerja * $tarifHarianTotal;
        }

        // 2. Presensi & Kinerja (Pertahankan nilai lama jika form tidak mengirimkan field bonus)
        $jamLembur              = $request->has('jam_lembur') ? floatval($request->jam_lembur ?? 0) : floatval($payroll->jam_lembur ?? 0);
        $banyakTarget           = $request->has('banyak_target') ? intval($request->banyak_target ?? 0) : intval($payroll->banyak_target ?? 0);
        $banyakTanggalMerah     = $request->has('banyak_tanggal_merah') ? intval($request->banyak_tanggal_merah ?? 0) : intval($payroll->banyak_tanggal_merah ?? 0);
        $banyakBirthdayService  = $request->has('banyak_birthday_service') ? intval($request->banyak_birthday_service ?? 0) : intval($payroll->banyak_birthday_service ?? 0);

        // 3. Kalkulasi Earnings (Pendapatan)
        $lembur               = $jamLembur * 10000;
        $bonusBirthdayService = $banyakBirthdayService * 5000;
        $bonusDll             = $request->has('bonus_dll') ? $cleanRupiah($request->bonus_dll) : floatval($payroll->bonus_dll ?? 0);

        if ($satuanGaji === 'Harian') {
            $bonusTarget         = $banyakTarget * $tarifHarianTotal;
            $bonusTanggalMerah   = $banyakTanggalMerah * $tarifHarianTotal;
            $catatanTarget       = null;
            $catatanTanggalMerah = null;
        } else {
            $bonusTarget         = $request->filled('manual_bonus_target') ? $cleanRupiah($request->manual_bonus_target) : ($request->has('bonus_target') ? $cleanRupiah($request->bonus_target) : floatval($payroll->bonus_target ?? 0));
            $bonusTanggalMerah   = $request->filled('manual_bonus_tanggal_merah') ? $cleanRupiah($request->manual_bonus_tanggal_merah) : ($request->has('bonus_tanggal_merah') ? $cleanRupiah($request->bonus_tanggal_merah) : floatval($payroll->bonus_tanggal_merah ?? 0));
            $catatanTarget       = $request->catatan_bonus_target ?? $payroll->catatan_bonus_target;
            $catatanTanggalMerah = $request->catatan_bonus_tanggal_merah ?? $payroll->catatan_bonus_tanggal_merah;
        }

        $totalEarnings = $gajiUtama + $lembur + $bonusTarget + $bonusTanggalMerah + $bonusBirthdayService + $bonusDll;

        // 4. Kalkulasi Deductions (Pengurangan - Pertahankan nilai lama jika tidak dikirim)
        $potonganTerlambat  = $request->has('potongan_terlambat') ? $cleanRupiah($request->potongan_terlambat) : floatval($payroll->potongan_terlambat ?? 0);
        $potonganInventaris = $request->has('potongan_inventaris') ? $cleanRupiah($request->potongan_inventaris) : floatval($payroll->potongan_inventaris ?? 0);
        $potonganKasbon     = $request->has('potongan_kasbon') ? $cleanRupiah($request->potongan_kasbon) : floatval($payroll->potongan_kasbon ?? 0);
        $potonganDll        = $request->has('potongan_dll') ? $cleanRupiah($request->potongan_dll) : floatval($payroll->potongan_dll ?? 0);

        $totalDeductions = $potonganTerlambat + $potonganInventaris + $potonganKasbon + $potonganDll;

        // 5. Gaji Bersih (Take Home Pay)
        $totalGajiBersih = $totalEarnings - $totalDeductions;

        $payroll->update([
            'satuan_gaji'                 => $satuanGaji,
            'satuan_gaji_2'               => $karyawan->satuan_gaji_2 ?? $payroll->satuan_gaji_2 ?? $satuanGaji,
            'pilihan_periode'             => $pilihanPeriode,
            'tanggal_mulai'               => $request->tanggal_mulai,
            'tanggal_selesai'             => $request->tanggal_selesai,
            'hari_kerja'                  => $hariKerja,
            'tarif_harian_total'          => $tarifHarianTotal,
            'gaji_utama'                  => $gajiUtama,
            'gaji_pokok'                  => $gajiPokokHarian,
            'tunjangan_transport'         => $uangTransport,
            'tunjangan_makan'             => $uangMakan,
            'jam_lembur'                  => $jamLembur,
            'lembur'                      => $lembur,
            'banyak_target'               => $banyakTarget,
            'bonus_target'                => $bonusTarget,
            'catatan_bonus_target'        => $catatanTarget,
            'banyak_tanggal_merah'        => $banyakTanggalMerah,
            'bonus_tanggal_merah'         => $bonusTanggalMerah,
            'catatan_bonus_tanggal_merah' => $catatanTanggalMerah,
            'banyak_birthday_service'     => $banyakBirthdayService,
            'bonus_birthday'              => $bonusBirthdayService,
            'bonus_dll'                   => $bonusDll,
            'potongan_terlambat'          => $potonganTerlambat,
            'potongan_inventaris'         => $potonganInventaris,
            'potongan_kasbon'             => $potonganKasbon,
            'potongan_dll'                => $potonganDll,
            'total_earnings'              => $totalEarnings,
            'total_deductions'            => $totalDeductions,
            'total_gaji_bersih'           => $totalGajiBersih,
        ]);

        // Kembalikan ke halaman detail kelompok karyawan per periode dengan pesan sukses
        return redirect()->route('penggajian.show-periode', ['periode' => $payroll->periode_bulan_tahun])
            ->with('success', 'Data gaji ' . $payroll->karyawan->nama_karyawan . ' berhasil diperbarui.');
    }

    public function show(Request $request, $id)
    {
        $basePayroll = Penggajian::with('karyawan')->findOrFail($id);
        $karyawanId = $basePayroll->karyawan_id;
        $periodeMonth = $basePayroll->periode_bulan_tahun;

        // Ambil semua payroll karyawan ini di bulan tersebut
        $allEntries = Penggajian::with('karyawan')
            ->where('karyawan_id', $karyawanId)
            ->where('periode_bulan_tahun', $periodeMonth)
            ->orderBy('pilihan_periode', 'asc')
            ->get();

        $selectedP = $request->query('periode'); // 'all', '1', '2', or null

        if ($allEntries->count() > 1 && ($selectedP === 'all' || !$selectedP)) {
            // Tampilan gabungan (SEMUA PERIODE di bulan tersebut)
            $payroll = clone $basePayroll;
            $payroll->is_combined = true;
            $payroll->pilihan_periode = 'all';
            $payroll->entries = $allEntries;

            $payroll->hari_kerja = $allEntries->sum('hari_kerja');
            $payroll->gaji_utama = $allEntries->sum('gaji_utama');
            $payroll->lembur = $allEntries->sum('lembur');
            $payroll->jam_lembur = $allEntries->sum('jam_lembur');
            $payroll->bonus_target = $allEntries->sum('bonus_target');
            $payroll->banyak_target = $allEntries->sum('banyak_target');
            $payroll->bonus_tanggal_merah = $allEntries->sum('bonus_tanggal_merah');
            $payroll->banyak_tanggal_merah = $allEntries->sum('banyak_tanggal_merah');
            $payroll->bonus_birthday = $allEntries->sum('bonus_birthday');
            $payroll->banyak_birthday_service = $allEntries->sum('banyak_birthday_service');
            $payroll->bonus_dll = $allEntries->sum('bonus_dll');

            $payroll->potongan_terlambat = $allEntries->sum('potongan_terlambat');
            $payroll->potongan_inventaris = $allEntries->sum('potongan_inventaris');
            $payroll->potongan_kasbon = $allEntries->sum('potongan_kasbon');
            $payroll->potongan_dll = $allEntries->sum('potongan_dll');

            $payroll->total_earnings = $payroll->gaji_utama + $payroll->lembur + $payroll->bonus_target +
                $payroll->bonus_tanggal_merah + $payroll->bonus_birthday + $payroll->bonus_dll;
            $payroll->total_deductions = $payroll->potongan_terlambat + $payroll->potongan_inventaris +
                $payroll->potongan_kasbon + $payroll->potongan_dll;
            $payroll->total_gaji_bersih = $payroll->total_earnings - $payroll->total_deductions;

            // Rentang tanggal gabungan
            $minDate = $allEntries->min('tanggal_mulai');
            $maxDate = $allEntries->max('tanggal_selesai');
            $payroll->tanggal_mulai = $minDate;
            $payroll->tanggal_selesai = $maxDate;
        } elseif ($selectedP && in_array($selectedP, ['1', '2'])) {
            $matchEntry = $allEntries->firstWhere('pilihan_periode', intval($selectedP)) ?? $basePayroll;
            $payroll = $matchEntry;
            $payroll->is_combined = false;
            $payroll->entries = $allEntries;
        } else {
            $payroll = $basePayroll;
            $payroll->is_combined = false;
            $payroll->entries = $allEntries;
        }

        // Ambil rincian keterlambatan karyawan pada rentang slip atau bulan periode ini
        $queryKeterlambatan = Keterlambatan::where('karyawan_id', $payroll->karyawan_id);
        if ($payroll->tanggal_mulai && $payroll->tanggal_selesai) {
            $queryKeterlambatan->whereBetween('tanggal', [
                \Carbon\Carbon::parse($payroll->tanggal_mulai)->format('Y-m-d'),
                \Carbon\Carbon::parse($payroll->tanggal_selesai)->format('Y-m-d')
            ]);
        } else {
            $queryKeterlambatan->whereRaw("DATE_FORMAT(tanggal, '%Y-%m') = ?", [$payroll->periode_bulan_tahun]);
        }
        $listKeterlambatan = $queryKeterlambatan->orderBy('tanggal', 'asc')->get();

        return view('penggajian.show', compact('payroll', 'allEntries', 'listKeterlambatan', 'selectedP'));
    }

    /**
     * Download Slip Gaji sebagai PDF
     */
    public function cetakPdf(Request $request, $id)
    {
        $basePayroll = Penggajian::with('karyawan')->findOrFail($id);
        $karyawanId = $basePayroll->karyawan_id;
        $periodeMonth = $basePayroll->periode_bulan_tahun;

        $allEntries = Penggajian::with('karyawan')
            ->where('karyawan_id', $karyawanId)
            ->where('periode_bulan_tahun', $periodeMonth)
            ->orderBy('pilihan_periode', 'asc')
            ->get();

        $selectedP = $request->query('periode'); // 'all', '1', '2', or null

        if ($allEntries->count() > 1 && ($selectedP === 'all' || !$selectedP)) {
            $payroll = clone $basePayroll;
            $payroll->is_combined = true;
            $payroll->pilihan_periode = 'all';
            $payroll->entries = $allEntries;

            $payroll->hari_kerja = $allEntries->sum('hari_kerja');
            $payroll->gaji_utama = $allEntries->sum('gaji_utama');
            $payroll->lembur = $allEntries->sum('lembur');
            $payroll->jam_lembur = $allEntries->sum('jam_lembur');
            $payroll->bonus_target = $allEntries->sum('bonus_target');
            $payroll->banyak_target = $allEntries->sum('banyak_target');
            $payroll->bonus_tanggal_merah = $allEntries->sum('bonus_tanggal_merah');
            $payroll->banyak_tanggal_merah = $allEntries->sum('banyak_tanggal_merah');
            $payroll->bonus_birthday = $allEntries->sum('bonus_birthday');
            $payroll->banyak_birthday_service = $allEntries->sum('banyak_birthday_service');
            $payroll->bonus_dll = $allEntries->sum('bonus_dll');

            $payroll->potongan_terlambat = $allEntries->sum('potongan_terlambat');
            $payroll->potongan_inventaris = $allEntries->sum('potongan_inventaris');
            $payroll->potongan_kasbon = $allEntries->sum('potongan_kasbon');
            $payroll->potongan_dll = $allEntries->sum('potongan_dll');

            $payroll->total_earnings = $payroll->gaji_utama + $payroll->lembur + $payroll->bonus_target +
                $payroll->bonus_tanggal_merah + $payroll->bonus_birthday + $payroll->bonus_dll;
            $payroll->total_deductions = $payroll->potongan_terlambat + $payroll->potongan_inventaris +
                $payroll->potongan_kasbon + $payroll->potongan_dll;
            $payroll->total_gaji_bersih = $payroll->total_earnings - $payroll->total_deductions;

            $minDate = $allEntries->min('tanggal_mulai');
            $maxDate = $allEntries->max('tanggal_selesai');
            $payroll->tanggal_mulai = $minDate;
            $payroll->tanggal_selesai = $maxDate;
        } elseif ($selectedP && in_array($selectedP, ['1', '2'])) {
            $matchEntry = $allEntries->firstWhere('pilihan_periode', intval($selectedP)) ?? $basePayroll;
            $payroll = $matchEntry;
            $payroll->is_combined = false;
            $payroll->entries = $allEntries;
        } else {
            $payroll = $basePayroll;
            $payroll->is_combined = false;
            $payroll->entries = $allEntries;
        }

        $queryKeterlambatan = Keterlambatan::where('karyawan_id', $payroll->karyawan_id);
        if ($payroll->tanggal_mulai && $payroll->tanggal_selesai) {
            $queryKeterlambatan->whereBetween('tanggal', [
                \Carbon\Carbon::parse($payroll->tanggal_mulai)->format('Y-m-d'),
                \Carbon\Carbon::parse($payroll->tanggal_selesai)->format('Y-m-d')
            ]);
        } else {
            $queryKeterlambatan->whereRaw("DATE_FORMAT(tanggal, '%Y-%m') = ?", [$payroll->periode_bulan_tahun]);
        }
        $listKeterlambatan = $queryKeterlambatan->orderBy('tanggal', 'asc')->get();

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('penggajian.slip-pdf', compact('payroll', 'allEntries', 'listKeterlambatan', 'selectedP'))
            ->setPaper('a4', 'portrait');

        $outletName = $payroll->outlet ?? $payroll->karyawan->outlet ?? 'Gaharu';
        $namaKaryawan = \Illuminate\Support\Str::slug($payroll->karyawan->nama_karyawan ?? 'karyawan');
        $periodeSuffix = $payroll->is_combined ? 'Gabungan' : ('P' . ($payroll->pilihan_periode ?? '1'));
        $filename = "Slip_Gaji_{$namaKaryawan}_{$outletName}_{$payroll->periode_bulan_tahun}_{$periodeSuffix}.pdf";

        return $pdf->download($filename);
    }

    /**
     * PROSES BAYAR GAJI KARYAWAN TUNGGAL (BAYAR + OTOMATIS BUAT JURNAL)
     */
    public function bayarKaryawan($id): RedirectResponse
    {
        $payroll = Penggajian::with('karyawan')->findOrFail($id);

        if ($payroll->status_jurnal || $payroll->status === 'approved') {
            return redirect()->back()->with('info', 'Slip gaji karyawan ini sudah dibayar dan dijurnal.');
        }

        $akunBebanGaji = \App\Models\ChartOfAccount::where('kode', '6101')->first()
            ?? \App\Models\ChartOfAccount::where('kode', '6100')->first()
            ?? \App\Models\ChartOfAccount::where('nama', 'like', '%Beban Gaji%')->first();

        $akunKas = \App\Models\ChartOfAccount::where('kode', '1101')->first()
            ?? \App\Models\ChartOfAccount::where('kode', '1100')->first()
            ?? \App\Models\ChartOfAccount::where('nama', 'like', '%Kas di Bank%')->first();

        if (!$akunBebanGaji || !$akunKas) {
            return redirect()->back()->with('error', 'Gagal memposting. Akun Beban Gaji atau Kas tidak ditemukan di Chart of Accounts.');
        }

        $tanggalJurnal = $payroll->tanggal_selesai 
            ? \Carbon\Carbon::parse($payroll->tanggal_selesai)->toDateString() 
            : now()->toDateString();

        $namaKaryawan = $payroll->karyawan->nama_karyawan ?? 'Karyawan';
        $rentangKet = ($payroll->tanggal_mulai && $payroll->tanggal_selesai)
            ? " (" . \Carbon\Carbon::parse($payroll->tanggal_mulai)->format('d/m/Y') . " - " . \Carbon\Carbon::parse($payroll->tanggal_selesai)->format('d/m/Y') . ")"
            : "";

        DB::transaction(function () use ($payroll, $akunBebanGaji, $akunKas, $tanggalJurnal, $namaKaryawan, $rentangKet) {
            $journal = Journal::create([
                'tanggal'     => $tanggalJurnal,
                'deskripsi'   => "Pembayaran gaji karyawan {$namaKaryawan}{$rentangKet}",
                'no_ref'      => 'PY-' . $payroll->id . '-' . rand(100, 999),
                'source_type' => 'jurnal_umum',
                'source_id'   => $payroll->id,
                'created_by'  => auth()->id() ?? 1,
                'status'      => 'approved',
            ]);

            JournalItem::create([
                'journal_id'   => $journal->id,
                'account_id'   => $akunBebanGaji->id,
                'debit'        => $payroll->total_gaji_bersih,
                'kredit'       => 0,
                'journal_type' => 'jurnal_umum',
            ]);

            JournalItem::create([
                'journal_id'   => $journal->id,
                'account_id'   => $akunKas->id,
                'debit'        => 0,
                'kredit'       => $payroll->total_gaji_bersih,
                'journal_type' => 'jurnal_umum',
            ]);

            $payroll->update([
                'status'        => 'approved',
                'status_jurnal' => true,
                'journal_id'    => $journal->id,
            ]);
        });

        return redirect()->back()->with('success', "Gaji {$namaKaryawan}{$rentangKet} sebesar Rp " . number_format($payroll->total_gaji_bersih, 0, ',', '.') . " berhasil dibayar & dijurnal!");
    }

    /**
     * PROSES BAYAR SEMUA KARYAWAN DALAM PERIODE TERSEBUT (MASSAL)
     */
    public function bayarSemuaPeriode(Request $request, $periode): RedirectResponse
    {
        $payrolls = Penggajian::with('karyawan')
            ->where('periode_bulan_tahun', $periode)
            ->where('status_jurnal', false)
            ->get();

        if ($payrolls->isEmpty()) {
            return redirect()->back()->with('info', "Seluruh data gaji periode {$periode} sudah terbayar dan dijurnal.");
        }

        $akunBebanGaji = \App\Models\ChartOfAccount::where('kode', '6101')->first()
            ?? \App\Models\ChartOfAccount::where('kode', '6100')->first()
            ?? \App\Models\ChartOfAccount::where('nama', 'like', '%Beban Gaji%')->first();

        $akunKas = \App\Models\ChartOfAccount::where('kode', '1101')->first()
            ?? \App\Models\ChartOfAccount::where('kode', '1100')->first()
            ?? \App\Models\ChartOfAccount::where('nama', 'like', '%Kas di Bank%')->first();

        if (!$akunBebanGaji || !$akunKas) {
            return redirect()->back()->with('error', 'Gagal memposting. Akun Beban Gaji atau Kas tidak ditemukan di Chart of Accounts.');
        }

        $totalGaji = $payrolls->sum('total_gaji_bersih');
        $tanggalJurnal = \Carbon\Carbon::parse($periode . '-01')->endOfMonth()->toDateString();

        DB::transaction(function () use ($payrolls, $periode, $totalGaji, $akunBebanGaji, $akunKas, $tanggalJurnal) {
            $journal = Journal::create([
                'tanggal'     => $tanggalJurnal,
                'deskripsi'   => "Pencatatan pembayaran gaji massal periode {$periode}",
                'no_ref'      => 'PY-ALL-' . strtoupper(str_replace('-', '', $periode)) . '-' . rand(100, 999),
                'source_type' => 'jurnal_umum',
                'source_id'   => 0,
                'created_by'  => auth()->id() ?? 1,
                'status'      => 'approved',
            ]);

            JournalItem::create([
                'journal_id'   => $journal->id,
                'account_id'   => $akunBebanGaji->id,
                'debit'        => $totalGaji,
                'kredit'       => 0,
                'journal_type' => 'jurnal_umum',
            ]);

            JournalItem::create([
                'journal_id'   => $journal->id,
                'account_id'   => $akunKas->id,
                'debit'        => 0,
                'kredit'       => $totalGaji,
                'journal_type' => 'jurnal_umum',
            ]);

            foreach ($payrolls as $p) {
                $p->update([
                    'status'        => 'approved',
                    'status_jurnal' => true,
                    'journal_id'    => $journal->id,
                ]);
            }
        });

        return redirect()->back()->with('success', "Seluruh gaji periode {$periode} (Total Rp " . number_format($totalGaji, 0, ',', '.') . ") berhasil dibayar & diposting ke Jurnal Umum!");
    }

    /**
     * Hitung tarif harian & gaji utama berbasis fluktuasi rentang tanggal (periode 1 & periode 2)
     */
    private function calculateWeightedTariff($karyawan, $tglMulaiSlip, $tglSelesaiSlip, $targetPeriode, $hariKerja)
    {
        $gp1 = floatval($karyawan->gaji_pokok ?? 0);
        $um1 = floatval($karyawan->uang_makan ?? 0);
        $ut1 = floatval($karyawan->uang_transport ?? 0);
        $tarif1 = $gp1 + $um1 + $ut1;

        $hasP2 = ($karyawan->gaji_pokok_2 !== null);
        $gp2 = floatval($karyawan->gaji_pokok_2 ?? 0);
        $um2 = floatval($karyawan->uang_makan_2 ?? 0);
        $ut2 = floatval($karyawan->uang_transport_2 ?? 0);
        $tarif2 = $gp2 + $um2 + $ut2;

        $satuanGaji1 = $karyawan->satuan_gaji ?? 'Harian';
        $satuanGaji2 = $karyawan->satuan_gaji_2 ?? $satuanGaji1;

        if (!$hasP2) {
            $gajiUtama = ($satuanGaji1 === 'Bulanan') ? $tarif1 : ($tarif1 * $hariKerja);
            return [
                'gaji_pokok'         => $gp1,
                'uang_makan'         => $um1,
                'uang_transport'     => $ut1,
                'tarif_harian_total' => $tarif1,
                'gaji_utama'         => $gajiUtama,
            ];
        }

        if ($tglMulaiSlip && $tglSelesaiSlip) {
            $startDate = \Carbon\Carbon::parse($tglMulaiSlip);
            $endDate   = \Carbon\Carbon::parse($tglSelesaiSlip);
        } else {
            $carbonPeriode = \Carbon\Carbon::parse($targetPeriode . '-01');
            $startDate     = $carbonPeriode->copy()->startOfMonth();
            $endDate       = $carbonPeriode->copy()->endOfMonth();
        }

        if ($endDate->lt($startDate)) {
            $endDate = $startDate->copy();
        }

        $p2Start = $karyawan->tanggal_mulai_2 ? \Carbon\Carbon::parse($karyawan->tanggal_mulai_2) : null;
        $p2End   = $karyawan->tanggal_selesai_2 ? \Carbon\Carbon::parse($karyawan->tanggal_selesai_2) : null;

        $n1 = 0;
        $n2 = 0;
        $curr = $startDate->copy();

        while ($curr->lte($endDate)) {
            $dateStr = $curr->format('Y-m-d');
            $isP2 = false;
            if ($p2Start && $dateStr >= $p2Start->format('Y-m-d')) {
                if (!$p2End || $dateStr <= $p2End->format('Y-m-d')) {
                    $isP2 = true;
                }
            }

            if ($isP2) {
                $n2++;
            } else {
                $n1++;
            }
            $curr->addDay();
        }

        $nTotal = $n1 + $n2;
        if ($nTotal <= 0) {
            $nTotal = 1;
            $n1 = 1;
        }

        $prop1 = $n1 / $nTotal;
        $prop2 = $n2 / $nTotal;

        $gajiP1 = ($satuanGaji1 === 'Bulanan') ? ($tarif1 * $prop1) : ($tarif1 * ($hariKerja * $prop1));
        $gajiP2 = ($satuanGaji2 === 'Bulanan') ? ($tarif2 * $prop2) : ($tarif2 * ($hariKerja * $prop2));

        $gajiUtama = $gajiP1 + $gajiP2;

        $gpWeighted    = ($n1 * $gp1 + $n2 * $gp2) / $nTotal;
        $umWeighted    = ($n1 * $um1 + $n2 * $um2) / $nTotal;
        $utWeighted    = ($n1 * $ut1 + $n2 * $ut2) / $nTotal;
        $tarifWeighted = $gpWeighted + $umWeighted + $utWeighted;

        return [
            'gaji_pokok'         => round($gpWeighted, 2),
            'uang_makan'         => round($umWeighted, 2),
            'uang_transport'     => round($utWeighted, 2),
            'tarif_harian_total' => round($tarifWeighted, 2),
            'gaji_utama'         => round($gajiUtama, 2),
        ];
    }
}
