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
     * Ambil daftar periode bulan-tahun yang tersedia untuk outlet
     */
    public function getAvailablePeriodes(string $selectedOutlet, ?string $currentPeriode = null): array
    {
        $periodes = Penggajian::where(function ($q) use ($selectedOutlet) {
                $q->where('outlet', $selectedOutlet)
                  ->orWhereHas('karyawan', function ($kq) use ($selectedOutlet) {
                      $kq->where('outlet', $selectedOutlet);
                  });
            })
            ->select('periode_bulan_tahun')
            ->groupBy('periode_bulan_tahun')
            ->orderBy('periode_bulan_tahun', 'desc')
            ->pluck('periode_bulan_tahun')
            ->toArray();

        $keterlambatanPeriodes = \App\Models\Keterlambatan::selectRaw("DATE_FORMAT(tanggal, '%Y-%m') as periode")
            ->groupBy('periode')
            ->orderBy('periode', 'desc')
            ->pluck('periode')
            ->toArray();

        $all = array_values(array_unique(array_merge($periodes, $keterlambatanPeriodes)));
        rsort($all);

        if ($currentPeriode && !in_array($currentPeriode, $all)) {
            array_unshift($all, $currentPeriode);
        }
        $now = date('Y-m');
        if (!in_array($now, $all)) {
            array_unshift($all, $now);
        }

        return $all;
    }

    /**
     * TAMPILAN UTAMA: Langsung arahkan ke halaman kelola gaji pokok periode aktif
     */
    public function index(Request $request)
    {
        $selectedOutlet = $this->getOutlet($request);
        $periodes = $this->getAvailablePeriodes($selectedOutlet);
        $periode = $request->query('periode') ?? ($periodes[0] ?? date('Y-m'));

        return redirect()->route('penggajian.show-periode', [
            'periode' => $periode,
            'outlet'  => $selectedOutlet,
        ]);
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
            'pengembalian_deposit'    => 'nullable|string',
            'bonus_dll'               => 'nullable|string',
            'potongan_terlambat'      => 'nullable|string',
            'potongan_inventaris'     => 'nullable|string',
            'potongan_kasbon'         => 'nullable|string',
            'potongan_deposit'        => 'nullable|string',
            'potongan_dll'            => 'nullable|string',
            'catatan_potongan_dll'    => 'nullable|string|max:255',
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
        $pengembalianDeposit  = $request->has('pengembalian_deposit') ? $cleanRupiah($request->pengembalian_deposit) : 0;
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

        $totalEarnings = $gajiUtama + $lembur + $bonusTarget + $bonusTanggalMerah + $bonusBirthdayService + $pengembalianDeposit + $bonusDll;

        // 4. Kalkulasi Deductions (Pengurangan) - Otomatis sinkronkan potongan terlambat dari tabel Keterlambatan jika tidak diset
        if ($request->has('potongan_terlambat')) {
            $potonganTerlambat = $cleanRupiah($request->potongan_terlambat);
        } else {
            $pMulai = $request->tanggal_mulai ?: (($pilihanPeriode === 2) ? ($karyawan->tanggal_mulai_2 ?? $karyawan->tanggal_mulai) : $karyawan->tanggal_mulai);
            $pSelesai = $request->tanggal_selesai ?: (($pilihanPeriode === 2) ? ($karyawan->tanggal_selesai_2 ?? $karyawan->tanggal_selesai) : $karyawan->tanggal_selesai);
            
            $qLate = Keterlambatan::where('karyawan_id', $karyawan->id);
            if ($pMulai && $pSelesai) {
                $qLate->whereBetween('tanggal', [
                    \Carbon\Carbon::parse($pMulai)->format('Y-m-d'),
                    \Carbon\Carbon::parse($pSelesai)->format('Y-m-d')
                ]);
            } elseif ($pMulai) {
                $qLate->where('tanggal', '>=', \Carbon\Carbon::parse($pMulai)->format('Y-m-d'))
                      ->whereRaw("DATE_FORMAT(tanggal, '%Y-%m') = ?", [$request->periode]);
            } elseif ($pSelesai) {
                $qLate->where('tanggal', '<=', \Carbon\Carbon::parse($pSelesai)->format('Y-m-d'))
                      ->whereRaw("DATE_FORMAT(tanggal, '%Y-%m') = ?", [$request->periode]);
            } else {
                $qLate->whereRaw("DATE_FORMAT(tanggal, '%Y-%m') = ?", [$request->periode]);
            }
            $potonganTerlambat = floatval($qLate->sum('potongan'));
        }

        $potonganInventaris = $request->has('potongan_inventaris') ? $cleanRupiah($request->potongan_inventaris) : 0;
        $potonganKasbon     = $request->has('potongan_kasbon') ? $cleanRupiah($request->potongan_kasbon) : 0;
        $potonganDeposit    = $request->has('potongan_deposit') ? $cleanRupiah($request->potongan_deposit) : 0;
        $potonganDll        = $request->has('potongan_dll') ? $cleanRupiah($request->potongan_dll) : 0;

        $totalDeductions = $potonganTerlambat + $potonganInventaris + $potonganKasbon + $potonganDeposit + $potonganDll;

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
            'pengembalian_deposit'        => $pengembalianDeposit,
            'bonus_dll'                   => $bonusDll,
            'potongan_terlambat'          => $potonganTerlambat,
            'potongan_inventaris'         => $potonganInventaris,
            'potongan_kasbon'             => $potonganKasbon,
            'potongan_deposit'            => $potonganDeposit,
            'potongan_dll'                => $potonganDll,
            'catatan_potongan_dll'        => $request->catatan_potongan_dll,
            'total_earnings'              => $totalEarnings,
            'total_deductions'            => $totalDeductions,
            'total_gaji_bersih'           => $totalGajiBersih,
            'status'                      => $existingStatus,
            'status_jurnal'               => false
        ]);

        // Sinkronkan juga rentang tanggal berlaku ke Master Data Karyawan (Pengaturan Gaji)
        if ($request->has('tanggal_mulai') || $request->has('tanggal_selesai')) {
            if ($pilihanPeriode === 2) {
                $karyawan->update([
                    'tanggal_mulai_2'   => $request->tanggal_mulai,
                    'tanggal_selesai_2' => $request->tanggal_selesai,
                ]);
            } else {
                $karyawan->update([
                    'tanggal_mulai'   => $request->tanggal_mulai,
                    'tanggal_selesai' => $request->tanggal_selesai,
                ]);
            }
        }

        return redirect()->route('penggajian.show-periode', ['periode' => $request->periode, 'outlet' => $karyawan->outlet ?? 'Gaharu'])
            ->with('success', "Data gaji ({$satuanGaji} Periode {$pilihanPeriode}) untuk {$karyawan->nama_karyawan} berhasil ditambahkan ke periode.");
    }


    /**
     * HALAMAN BARU: Menampilkan daftar karyawan khusus pada periode tertentu (Hasil klik tombol Detail Karyawan)
     */
    public function periodeDetail(Request $request)
    {
        $selectedOutlet = $this->getOutlet($request);
        $periodes = $this->getAvailablePeriodes($selectedOutlet, $request->query('periode'));
        $periode = $request->query('periode') ?? ($periodes[0] ?? date('Y-m'));

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

        // Bersihkan duplikat otomatis di database & bersihkan slip draft periode 2 jika di master data periode 2 gajinya 0/null
        $groupedByKaryawan = $rawPayrolls->groupBy('karyawan_id');
        foreach ($groupedByKaryawan as $empId => $items) {
            $kw = $items->first()->karyawan;
            if ($kw) {
                $p2Total = floatval($kw->gaji_pokok_2 ?? 0) + floatval($kw->uang_makan_2 ?? 0) + floatval($kw->uang_transport_2 ?? 0);
                $hasP2Master = ($kw->gaji_pokok_2 !== null && $p2Total > 0);
                if (!$hasP2Master) {
                    $p2Drafts = $items->where('pilihan_periode', 2)->where('status', '!=', 'approved')->where('status_jurnal', false);
                    if ($p2Drafts->isNotEmpty()) {
                        $p2DraftIds = $p2Drafts->pluck('id')->toArray();
                        Penggajian::whereIn('id', $p2DraftIds)->delete();
                        $rawPayrolls = $rawPayrolls->reject(fn($p) => in_array($p->id, $p2DraftIds));
                        $items = $items->reject(fn($p) => in_array($p->id, $p2DraftIds));
                    }
                }
            }

            // Bersihkan duplikat item dengan pilihan_periode yang sama pada karyawan yang sama di bulan ini
            $byPeriode = $items->groupBy(fn($p) => (int)($p->pilihan_periode ?? 1));
            foreach ($byPeriode as $pNum => $pItems) {
                if ($pItems->count() > 1) {
                    $keepItem = $pItems->where('status', 'approved')->first()
                        ?? $pItems->where('hari_kerja', '>', 0)->sortByDesc('id')->first()
                        ?? $pItems->sortByDesc('id')->first();

                    $deleteIds = $pItems->where('id', '!=', $keepItem->id)->where('status', '!=', 'approved')->where('status_jurnal', false)->pluck('id')->toArray();
                    if (!empty($deleteIds)) {
                        Penggajian::whereIn('id', $deleteIds)->delete();
                        $rawPayrolls = $rawPayrolls->reject(fn($p) => in_array($p->id, $deleteIds));
                    }
                }
            }
        }

        // Otomatis sinkronkan komponen gaji master dan potongan keterlambatan untuk slip draft / waiting approval di periode ini
        foreach ($rawPayrolls as $payroll) {
            if ($payroll->status !== 'approved' && !$payroll->status_jurnal && $payroll->karyawan) {
                $kw = $payroll->karyawan;
                $pNum = (int)($payroll->pilihan_periode ?? 1);
                $p2Total = floatval($kw->gaji_pokok_2 ?? 0) + floatval($kw->uang_makan_2 ?? 0) + floatval($kw->uang_transport_2 ?? 0);
                $hasP2Master = ($kw->gaji_pokok_2 !== null && $p2Total > 0);
                if ($pNum === 2 && $hasP2Master) {
                    $masterGp = floatval($kw->gaji_pokok_2);
                    $masterUm = floatval($kw->uang_makan_2);
                    $masterUt = floatval($kw->uang_transport_2);
                    $masterSat = $kw->satuan_gaji_2 ?? $kw->satuan_gaji ?? 'Harian';
                    $masterMulai = $kw->tanggal_mulai_2 ? \Carbon\Carbon::parse($kw->tanggal_mulai_2)->format('Y-m-d') : null;
                    $masterSelesai = $kw->tanggal_selesai_2 ? \Carbon\Carbon::parse($kw->tanggal_selesai_2)->format('Y-m-d') : null;
                } else {
                    $masterGp = floatval($kw->gaji_pokok);
                    $masterUm = floatval($kw->uang_makan);
                    $masterUt = floatval($kw->uang_transport);
                    $masterSat = $kw->satuan_gaji ?? 'Harian';
                    $masterMulai = $kw->tanggal_mulai ? \Carbon\Carbon::parse($kw->tanggal_mulai)->format('Y-m-d') : null;
                    $masterSelesai = $kw->tanggal_selesai ? \Carbon\Carbon::parse($kw->tanggal_selesai)->format('Y-m-d') : null;
                }
                $masterTarif = $masterGp + $masterUm + $masterUt;

                $hk = floatval($payroll->hari_kerja ?? 0);
                $gajiUtama = ($masterSat === 'Bulanan') ? $masterTarif : ($hk * $masterTarif);

                $pMulai = $payroll->tanggal_mulai ? \Carbon\Carbon::parse($payroll->tanggal_mulai)->format('Y-m-d') : $masterMulai;
                $pSelesai = $payroll->tanggal_selesai ? \Carbon\Carbon::parse($payroll->tanggal_selesai)->format('Y-m-d') : $masterSelesai;

                $qLate = Keterlambatan::where('karyawan_id', $payroll->karyawan_id);
                if ($pMulai && $pSelesai) {
                    $qLate->whereBetween('tanggal', [$pMulai, $pSelesai]);
                } elseif ($pMulai) {
                    $qLate->where('tanggal', '>=', $pMulai)->whereRaw("DATE_FORMAT(tanggal, '%Y-%m') = ?", [$payroll->periode_bulan_tahun]);
                } elseif ($pSelesai) {
                    $qLate->where('tanggal', '<=', $pSelesai)->whereRaw("DATE_FORMAT(tanggal, '%Y-%m') = ?", [$payroll->periode_bulan_tahun]);
                } else {
                    $qLate->whereRaw("DATE_FORMAT(tanggal, '%Y-%m') = ?", [$payroll->periode_bulan_tahun]);
                }
                $potonganTerlambat = (float) $qLate->sum('potongan');

                $bTarget = intval($payroll->banyak_target ?? 0);
                $bMerah = intval($payroll->banyak_tanggal_merah ?? 0);
                $bonusTarget = ($masterSat === 'Harian' && $bTarget > 0) ? ($bTarget * $masterTarif) : floatval($payroll->bonus_target ?? 0);
                $bonusTanggalMerah = ($masterSat === 'Harian' && $bMerah > 0) ? ($bMerah * $masterTarif) : floatval($payroll->bonus_tanggal_merah ?? 0);

                $lembur = floatval($payroll->lembur ?? 0);
                $bonusBday = floatval($payroll->bonus_birthday ?? 0);
                $depKembali = floatval($payroll->pengembalian_deposit ?? 0);
                $bonusDll = floatval($payroll->bonus_dll ?? 0);
                $totalBonus = $lembur + $bonusTarget + $bonusTanggalMerah + $bonusBday + $depKembali + $bonusDll;

                $earnings = $gajiUtama + $totalBonus;

                $deductions = $potonganTerlambat +
                              floatval($payroll->potongan_inventaris ?? 0) +
                              floatval($payroll->potongan_kasbon ?? 0) +
                              floatval($payroll->potongan_deposit ?? 0) +
                              floatval($payroll->potongan_dll ?? 0);

                $thp = $earnings - $deductions;

                $needSync = (
                    abs((float)$payroll->tarif_harian_total - $masterTarif) > 0.01 ||
                    abs((float)$payroll->gaji_pokok - $masterGp) > 0.01 ||
                    abs((float)$payroll->tunjangan_makan - $masterUm) > 0.01 ||
                    abs((float)$payroll->tunjangan_transport - $masterUt) > 0.01 ||
                    $payroll->satuan_gaji !== $masterSat ||
                    abs((float)$payroll->gaji_utama - $gajiUtama) > 0.01 ||
                    abs((float)$payroll->potongan_terlambat - $potonganTerlambat) > 0.01 ||
                    abs((float)$payroll->total_deductions - $deductions) > 0.01 ||
                    abs((float)$payroll->total_gaji_bersih - $thp) > 0.01 ||
                    (!$payroll->tanggal_mulai && $masterMulai) ||
                    (!$payroll->tanggal_selesai && $masterSelesai)
                );

                if ($needSync) {
                    $payroll->update([
                        'satuan_gaji'        => $masterSat,
                        'satuan_gaji_2'      => $kw->satuan_gaji_2 ?? $masterSat,
                        'gaji_pokok'         => $masterGp,
                        'tunjangan_makan'    => $masterUm,
                        'tunjangan_transport'=> $masterUt,
                        'tarif_harian_total' => $masterTarif,
                        'gaji_utama'         => $gajiUtama,
                        'tanggal_mulai'      => $pMulai,
                        'tanggal_selesai'    => $pSelesai,
                        'bonus_target'       => $bonusTarget,
                        'bonus_tanggal_merah'=> $bonusTanggalMerah,
                        'total_earnings'     => $earnings,
                        'potongan_terlambat' => $potonganTerlambat,
                        'total_deductions'   => $deductions,
                        'total_gaji_bersih'  => $thp,
                    ]);
                    $payroll->satuan_gaji = $masterSat;
                    $payroll->satuan_gaji_2 = $kw->satuan_gaji_2 ?? $masterSat;
                    $payroll->gaji_pokok = $masterGp;
                    $payroll->tunjangan_makan = $masterUm;
                    $payroll->tunjangan_transport = $masterUt;
                    $payroll->tarif_harian_total = $masterTarif;
                    $payroll->gaji_utama = $gajiUtama;
                    $payroll->tanggal_mulai = $pMulai;
                    $payroll->tanggal_selesai = $pSelesai;
                    $payroll->bonus_target = $bonusTarget;
                    $payroll->bonus_tanggal_merah = $bonusTanggalMerah;
                    $payroll->total_earnings = $earnings;
                    $payroll->potongan_terlambat = $potonganTerlambat;
                    $payroll->total_deductions = $deductions;
                    $payroll->total_gaji_bersih = $thp;
                }
            }
        }

        $karyawanIds = $rawPayrolls->pluck('karyawan_id')->unique();
        $hasPotDeposit = \Illuminate\Support\Facades\Schema::hasColumn('penggajian', 'potongan_deposit');
        $hasRetDeposit = \Illuminate\Support\Facades\Schema::hasColumn('penggajian', 'pengembalian_deposit');

        $allPotonganDeposit = $hasPotDeposit
            ? Penggajian::whereIn('karyawan_id', $karyawanIds)
                ->where('periode_bulan_tahun', '<', $periode)
                ->groupBy('karyawan_id')
                ->selectRaw('karyawan_id, SUM(potongan_deposit) as total_pot_deposit')
                ->pluck('total_pot_deposit', 'karyawan_id')
            : collect();

        $allReturnedOther = $hasRetDeposit
            ? Penggajian::whereIn('karyawan_id', $karyawanIds)
                ->where('periode_bulan_tahun', '<', $periode)
                ->groupBy('karyawan_id')
                ->selectRaw('karyawan_id, SUM(pengembalian_deposit) as total_ret_deposit')
                ->pluck('total_ret_deposit', 'karyawan_id')
            : collect();

        // Grouping: 1 BARIS PER KARYAWAN
        $payrolls = $rawPayrolls->groupBy('karyawan_id')->map(function ($items) use ($allPotonganDeposit, $allReturnedOther) {
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
            $totalPengembalianDeposit = $items->sum('pengembalian_deposit');
            $totalBonusDll = $items->sum('bonus_dll');

            $saldoDeposit = max(0, floatval($allPotonganDeposit->get($first->karyawan_id, 0)) - floatval($allReturnedOther->get($first->karyawan_id, 0)));

            $totalEarnings = $items->sum(function($p) {
                return $p->total_earnings > 0 ? (float)$p->total_earnings : (
                    (float)($p->gaji_utama ?? 0) + (float)($p->lembur ?? 0) + (float)($p->bonus_target ?? 0) +
                    (float)($p->bonus_tanggal_merah ?? 0) + (float)($p->bonus_birthday ?? 0) + (float)($p->pengembalian_deposit ?? 0) + (float)($p->bonus_dll ?? 0)
                );
            });

            $totalPotonganTerlambat = $items->sum('potongan_terlambat');
            $totalPotonganInventaris = $items->sum('potongan_inventaris');
            $totalPotonganKasbon = $items->sum('potongan_kasbon');
            $totalPotonganDeposit = $items->sum('potongan_deposit');
            $totalPotonganDll = $items->sum('potongan_dll');

            $totalDeductions = $totalPotonganTerlambat + $totalPotonganInventaris + $totalPotonganKasbon + $totalPotonganDeposit + $totalPotonganDll;

            $takeHomePay = $totalEarnings - $totalDeductions;
            $isPaid = $items->every(fn($p) => (bool)$p->status_jurnal);

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
                'pengembalian_deposit'    => $totalPengembalianDeposit,
                'saldo_deposit'           => $saldoDeposit,
                'bonus_dll'               => $totalBonusDll,
                'catatan_bonus_dll'       => $primaryPayroll->catatan_bonus_dll ?? '',
                'potongan_terlambat'      => $totalPotonganTerlambat,
                'potongan_inventaris'     => $totalPotonganInventaris,
                'potongan_kasbon'         => $totalPotonganKasbon,
                'potongan_deposit'        => $totalPotonganDeposit,
                'potongan_dll'            => $totalPotonganDll,
                'catatan_potongan_dll'    => $primaryPayroll->catatan_potongan_dll ?? '',
                'catatan_bonus_target'    => $primaryPayroll->catatan_bonus_target ?? '',
                'catatan_bonus_tanggal_merah' => $primaryPayroll->catatan_bonus_tanggal_merah ?? '',
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
                'tipe_pembayaran'         => $primaryPayroll->tipe_pembayaran ?? $first->tipe_pembayaran ?? null,
            ];
        })->sortBy([
            fn ($a, $b) => ($a->karyawan->urutan ?? 999999) <=> ($b->karyawan->urutan ?? 999999),
            fn ($a, $b) => ($a->karyawan->id ?? 0) <=> ($b->karyawan->id ?? 0),
        ])->values();

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
        $periodes = $this->getAvailablePeriodes($selectedOutlet, $periode);

        return view('penggajian.show-periode', compact('payrolls', 'periode', 'periodes', 'currentStatus', 'selectedOutlet', 'availableKaryawans', 'allKaryawans'));
    }

    /**
     * EXPORT EXCEL: Data Transfer Gaji Karyawan (Format Payroll Bank)
     * Kolom: REKENING | NOMINAL | EMAIL
     * Rekening & Nominal: tanpa titik, spasi, atau tanda baca
     */
    public function exportPayrollExcel(Request $request)
    {
        $periode = $request->input('periode') ?? $request->query('periode');
        $selectedOutlet = $this->getOutlet($request);

        if (!$periode) {
            return back()->with('error', 'Periode tidak valid.');
        }

        // Ambil data penggajian untuk periode & outlet ini
        $query = Penggajian::with('karyawan')
            ->where('periode_bulan_tahun', $periode)
            ->where(function ($q) use ($selectedOutlet) {
                $q->where('outlet', $selectedOutlet)
                  ->orWhereHas('karyawan', function ($kq) use ($selectedOutlet) {
                      $kq->where('outlet', $selectedOutlet);
                  });
            });

        // Filter by selected payroll IDs jika user memilih seleksi tertentu dari modal
        $payrollIds = $request->input('payroll_ids') ?? $request->query('payroll_ids') ?? $request->input('selected_ids') ?? $request->query('selected_ids');
        if (!empty($payrollIds)) {
            if (is_string($payrollIds)) {
                $payrollIds = explode(',', $payrollIds);
            }
            $payrollIds = array_filter(array_map('intval', (array)$payrollIds));
            if (!empty($payrollIds)) {
                $query->whereIn('id', $payrollIds);
            }
        }

        $rawPayrolls = $query->get();

        // Group per karyawan, hitung take home pay total dari item yang dipilih
        $rows = $rawPayrolls->groupBy('karyawan_id')->map(function ($items) {
            $first = $items->first();
            $karyawan = $first->karyawan;
            if (!$karyawan) return null;

            $totalEarnings = $items->sum(function($p) {
                return $p->total_earnings > 0 ? (float)$p->total_earnings : (
                    (float)($p->gaji_utama ?? 0) + (float)($p->lembur ?? 0) +
                    (float)($p->bonus_target ?? 0) + (float)($p->bonus_tanggal_merah ?? 0) +
                    (float)($p->bonus_birthday ?? 0) + (float)($p->pengembalian_deposit ?? 0) + (float)($p->bonus_dll ?? 0)
                );
            });

            $totalDeductions = $items->sum(function($p) {
                return $p->total_deductions > 0 ? (float)$p->total_deductions : (
                    (float)($p->potongan_terlambat ?? 0) + (float)($p->potongan_inventaris ?? 0) +
                    (float)($p->potongan_kasbon ?? 0) + (float)($p->potongan_deposit ?? 0) + (float)($p->potongan_dll ?? 0)
                );
            });

            $takeHomePay = $totalEarnings - $totalDeductions;

            // Bersihkan nomor rekening: hanya digit, hapus semua non-digit
            $rekening = preg_replace('/\D/', '', $karyawan->no_rekening ?? '');

            // Nominal tanpa desimal, tanpa titik/koma/spasi
            $nominal = (int) round($takeHomePay);

            return [
                'karyawan_id' => $karyawan->id,
                'urutan'      => $karyawan->urutan ?? 999999,
                'rekening'    => $rekening,
                'nominal'     => $nominal,
                'email'       => $karyawan->email ?? '',
                'nama'        => $karyawan->nama_karyawan ?? '-',
            ];
        })->filter(fn($r) => !empty($r) && $r['rekening'] !== '' && $r['nominal'] > 0);

        // Urutkan baris agar persis sama dengan urutan di formulir hitung gaji
        $orderedKaryawanIds = $request->input('ordered_karyawan_ids') ?? $request->query('ordered_karyawan_ids');
        if (!empty($orderedKaryawanIds)) {
            if (is_string($orderedKaryawanIds)) {
                $orderedKaryawanIds = explode(',', $orderedKaryawanIds);
            }
            $orderedMap = array_flip(array_values(array_filter(array_map('intval', (array)$orderedKaryawanIds))));
            $rows = $rows->sortBy(function ($row) use ($orderedMap) {
                $kid = (int)($row['karyawan_id'] ?? 0);
                return $orderedMap[$kid] ?? 999999;
            })->values();
        } else {
            $rows = $rows->sortBy([
                fn ($a, $b) => ($a['urutan'] ?? 999999) <=> ($b['urutan'] ?? 999999),
                fn ($a, $b) => ($a['karyawan_id'] ?? 0) <=> ($b['karyawan_id'] ?? 0),
            ])->values();
        }

        if ($rows->isEmpty()) {
            return back()->with('error', 'Tidak ada data transfer gaji yang valid untuk diekspor (pastikan nomor rekening karyawan terisi dan nominal > 0).');
        }

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
        $filterTag = $request->input('filter_label') ? '_' . preg_replace('/[^a-zA-Z0-9]/', '', $request->input('filter_label')) : '';
        $filename = 'Transfer_Gaji' . $filterTag . '_' . $selectedOutlet . '_' . str_replace(' ', '_', $periodeFormatted) . '.xlsx';

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

            // Otomatis hitung akumulasi denda keterlambatan bulan ini dari tabel Keterlambatan sesuai tanggal berlaku
            $pMulai = $k->tanggal_mulai ? \Carbon\Carbon::parse($k->tanggal_mulai)->format('Y-m-d') : null;
            $pSelesai = $k->tanggal_selesai ? \Carbon\Carbon::parse($k->tanggal_selesai)->format('Y-m-d') : null;

            $qLate = Keterlambatan::where('karyawan_id', $k->id);
            if ($pMulai && $pSelesai) {
                $qLate->whereBetween('tanggal', [$pMulai, $pSelesai]);
            } elseif ($pMulai) {
                $qLate->where('tanggal', '>=', $pMulai)->whereRaw("DATE_FORMAT(tanggal, '%Y-%m') = ?", [$periode]);
            } elseif ($pSelesai) {
                $qLate->where('tanggal', '<=', $pSelesai)->whereRaw("DATE_FORMAT(tanggal, '%Y-%m') = ?", [$periode]);
            } else {
                $qLate->whereRaw("DATE_FORMAT(tanggal, '%Y-%m') = ?", [$periode]);
            }
            $potonganTerlambat = (float) $qLate->sum('potongan');

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
                'pengembalian_deposit'    => 0,
                'bonus_dll'               => 0,
                'potongan_terlambat'      => $potonganTerlambat,
                'potongan_inventaris'     => 0,
                'potongan_kasbon'         => 0,
                'potongan_deposit'        => 0,
                'potongan_dll'            => 0,
                'catatan_potongan_dll'    => null,
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
            'pengembalian_deposit'    => 'nullable|string',
            'bonus_dll'               => 'nullable|string',
            'potongan_terlambat'      => 'nullable|string',
            'potongan_inventaris'     => 'nullable|string',
            'potongan_kasbon'         => 'nullable|string',
            'potongan_deposit'        => 'nullable|string',
            'potongan_dll'            => 'nullable|string',
            'catatan_potongan_dll'    => 'nullable|string|max:255',
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
        $pengembalianDeposit  = $request->has('pengembalian_deposit') ? $cleanRupiah($request->pengembalian_deposit) : floatval($payroll->pengembalian_deposit ?? 0);
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

        $totalEarnings = $gajiUtama + $lembur + $bonusTarget + $bonusTanggalMerah + $bonusBirthdayService + $pengembalianDeposit + $bonusDll;

        // 4. Kalkulasi Deductions (Pengurangan - Pertahankan nilai lama jika tidak dikirim)
        $potonganTerlambat  = $request->has('potongan_terlambat') ? $cleanRupiah($request->potongan_terlambat) : floatval($payroll->potongan_terlambat ?? 0);
        $potonganInventaris = $request->has('potongan_inventaris') ? $cleanRupiah($request->potongan_inventaris) : floatval($payroll->potongan_inventaris ?? 0);
        $potonganKasbon     = $request->has('potongan_kasbon') ? $cleanRupiah($request->potongan_kasbon) : floatval($payroll->potongan_kasbon ?? 0);
        $potonganDeposit    = $request->has('potongan_deposit') ? $cleanRupiah($request->potongan_deposit) : floatval($payroll->potongan_deposit ?? 0);
        $potonganDll        = $request->has('potongan_dll') ? $cleanRupiah($request->potongan_dll) : floatval($payroll->potongan_dll ?? 0);
        $catatanPotonganDll = $request->has('catatan_potongan_dll') ? $request->catatan_potongan_dll : $payroll->catatan_potongan_dll;

        $totalDeductions = $potonganTerlambat + $potonganInventaris + $potonganKasbon + $potonganDeposit + $potonganDll;

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
            'pengembalian_deposit'        => $pengembalianDeposit,
            'bonus_dll'                   => $bonusDll,
            'potongan_terlambat'          => $potonganTerlambat,
            'potongan_inventaris'         => $potonganInventaris,
            'potongan_kasbon'             => $potonganKasbon,
            'potongan_deposit'            => $potonganDeposit,
            'potongan_dll'                => $potonganDll,
            'catatan_potongan_dll'        => $catatanPotonganDll,
            'total_earnings'              => $totalEarnings,
            'total_deductions'            => $totalDeductions,
            'total_gaji_bersih'           => $totalGajiBersih,
        ]);

        // Sinkronkan juga rentang tanggal berlaku ke Master Data Karyawan (Pengaturan Gaji)
        if ($request->has('tanggal_mulai') || $request->has('tanggal_selesai')) {
            if ($pilihanPeriode === 2) {
                $karyawan->update([
                    'tanggal_mulai_2'   => $request->tanggal_mulai,
                    'tanggal_selesai_2' => $request->tanggal_selesai,
                ]);
            } else {
                $karyawan->update([
                    'tanggal_mulai'   => $request->tanggal_mulai,
                    'tanggal_selesai' => $request->tanggal_selesai,
                ]);
            }
        }

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
            $payroll->pengembalian_deposit = $allEntries->sum('pengembalian_deposit');
            $payroll->bonus_dll = $allEntries->sum('bonus_dll');
            $payroll->catatan_bonus_dll = $allEntries->pluck('catatan_bonus_dll')->filter()->unique()->implode(', ');

            $payroll->potongan_terlambat = $allEntries->sum('potongan_terlambat');
            $payroll->potongan_inventaris = $allEntries->sum('potongan_inventaris');
            $payroll->potongan_kasbon = $allEntries->sum('potongan_kasbon');
            $payroll->potongan_deposit = $allEntries->sum('potongan_deposit');
            $payroll->potongan_dll = $allEntries->sum('potongan_dll');
            $payroll->catatan_potongan_dll = $allEntries->pluck('catatan_potongan_dll')->filter()->unique()->implode(', ');

            $payroll->total_earnings = $payroll->gaji_utama + $payroll->lembur + $payroll->bonus_target +
                $payroll->bonus_tanggal_merah + $payroll->bonus_birthday + $payroll->pengembalian_deposit + $payroll->bonus_dll;
            $payroll->total_deductions = $payroll->potongan_terlambat + $payroll->potongan_inventaris +
                $payroll->potongan_kasbon + $payroll->potongan_deposit + $payroll->potongan_dll;
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
        $pMulai = $payroll->tanggal_mulai;
        $pSelesai = $payroll->tanggal_selesai;
        if (!$pMulai && !$pSelesai && $payroll->karyawan && !$payroll->is_combined) {
            $pNum = (int)($payroll->pilihan_periode ?? 1);
            if ($pNum === 2) {
                $pMulai = $payroll->karyawan->tanggal_mulai_2 ?? $payroll->karyawan->tanggal_mulai;
                $pSelesai = $payroll->karyawan->tanggal_selesai_2 ?? $payroll->karyawan->tanggal_selesai;
            } else {
                $pMulai = $payroll->karyawan->tanggal_mulai;
                $pSelesai = $payroll->karyawan->tanggal_selesai;
            }
        }

        $queryKeterlambatan = Keterlambatan::where('karyawan_id', $payroll->karyawan_id);
        if ($pMulai && $pSelesai) {
            $queryKeterlambatan->whereBetween('tanggal', [
                \Carbon\Carbon::parse($pMulai)->format('Y-m-d'),
                \Carbon\Carbon::parse($pSelesai)->format('Y-m-d')
            ]);
        } elseif ($pMulai) {
            $queryKeterlambatan->where('tanggal', '>=', \Carbon\Carbon::parse($pMulai)->format('Y-m-d'))
                              ->whereRaw("DATE_FORMAT(tanggal, '%Y-%m') = ?", [$payroll->periode_bulan_tahun]);
        } elseif ($pSelesai) {
            $queryKeterlambatan->where('tanggal', '<=', \Carbon\Carbon::parse($pSelesai)->format('Y-m-d'))
                              ->whereRaw("DATE_FORMAT(tanggal, '%Y-%m') = ?", [$payroll->periode_bulan_tahun]);
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
            $payroll->pengembalian_deposit = $allEntries->sum('pengembalian_deposit');
            $payroll->bonus_dll = $allEntries->sum('bonus_dll');
            $payroll->catatan_bonus_dll = $allEntries->pluck('catatan_bonus_dll')->filter()->unique()->implode(', ');

            $payroll->potongan_terlambat = $allEntries->sum('potongan_terlambat');
            $payroll->potongan_inventaris = $allEntries->sum('potongan_inventaris');
            $payroll->potongan_kasbon = $allEntries->sum('potongan_kasbon');
            $payroll->potongan_deposit = $allEntries->sum('potongan_deposit');
            $payroll->potongan_dll = $allEntries->sum('potongan_dll');
            $payroll->catatan_potongan_dll = $allEntries->pluck('catatan_potongan_dll')->filter()->unique()->implode(', ');

            $payroll->total_earnings = $payroll->gaji_utama + $payroll->lembur + $payroll->bonus_target +
                $payroll->bonus_tanggal_merah + $payroll->bonus_birthday + $payroll->pengembalian_deposit + $payroll->bonus_dll;
            $payroll->total_deductions = $payroll->potongan_terlambat + $payroll->potongan_inventaris +
                $payroll->potongan_kasbon + $payroll->potongan_deposit + $payroll->potongan_dll;
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

        $pMulai = $payroll->tanggal_mulai;
        $pSelesai = $payroll->tanggal_selesai;
        if (!$pMulai && !$pSelesai && $payroll->karyawan && !$payroll->is_combined) {
            $pNum = (int)($payroll->pilihan_periode ?? 1);
            if ($pNum === 2) {
                $pMulai = $payroll->karyawan->tanggal_mulai_2 ?? $payroll->karyawan->tanggal_mulai;
                $pSelesai = $payroll->karyawan->tanggal_selesai_2 ?? $payroll->karyawan->tanggal_selesai;
            } else {
                $pMulai = $payroll->karyawan->tanggal_mulai;
                $pSelesai = $payroll->karyawan->tanggal_selesai;
            }
        }

        $queryKeterlambatan = Keterlambatan::where('karyawan_id', $payroll->karyawan_id);
        if ($pMulai && $pSelesai) {
            $queryKeterlambatan->whereBetween('tanggal', [
                \Carbon\Carbon::parse($pMulai)->format('Y-m-d'),
                \Carbon\Carbon::parse($pSelesai)->format('Y-m-d')
            ]);
        } elseif ($pMulai) {
            $queryKeterlambatan->where('tanggal', '>=', \Carbon\Carbon::parse($pMulai)->format('Y-m-d'))
                              ->whereRaw("DATE_FORMAT(tanggal, '%Y-%m') = ?", [$payroll->periode_bulan_tahun]);
        } elseif ($pSelesai) {
            $queryKeterlambatan->where('tanggal', '<=', \Carbon\Carbon::parse($pSelesai)->format('Y-m-d'))
                              ->whereRaw("DATE_FORMAT(tanggal, '%Y-%m') = ?", [$payroll->periode_bulan_tahun]);
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
     * Tampilan Slip Gaji Publik (Dapat diakses oleh karyawan melalui link WhatsApp tanpa login)
     */
    public function publicSlipView(Request $request, $id)
    {
        $basePayroll = Penggajian::with('karyawan')->findOrFail($id);
        $selectedP = $request->query('periode'); // 'all', '1', '2', or null
        $token = $request->query('token');

        // Validasi Token Keamanan (jika tidak login)
        $expectedToken = Penggajian::generateSlipToken($basePayroll->id, $selectedP);
        $expectedTokenNull = Penggajian::generateSlipToken($basePayroll->id, null);
        $expectedTokenAll = Penggajian::generateSlipToken($basePayroll->id, 'all');

        if (!auth()->check() && $token !== $expectedToken && $token !== $expectedTokenNull && $token !== $expectedTokenAll) {
            abort(403, 'Akses Slip Gaji tidak valid atau tautan kedaluwarsa.');
        }

        $karyawanId = $basePayroll->karyawan_id;
        $periodeMonth = $basePayroll->periode_bulan_tahun;

        $allEntries = Penggajian::with('karyawan')
            ->where('karyawan_id', $karyawanId)
            ->where('periode_bulan_tahun', $periodeMonth)
            ->orderBy('pilihan_periode', 'asc')
            ->get();

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
            $payroll->pengembalian_deposit = $allEntries->sum('pengembalian_deposit');
            $payroll->bonus_dll = $allEntries->sum('bonus_dll');
            $payroll->catatan_bonus_dll = $allEntries->pluck('catatan_bonus_dll')->filter()->unique()->implode(', ');

            $payroll->potongan_terlambat = $allEntries->sum('potongan_terlambat');
            $payroll->potongan_inventaris = $allEntries->sum('potongan_inventaris');
            $payroll->potongan_kasbon = $allEntries->sum('potongan_kasbon');
            $payroll->potongan_deposit = $allEntries->sum('potongan_deposit');
            $payroll->potongan_dll = $allEntries->sum('potongan_dll');
            $payroll->catatan_potongan_dll = $allEntries->pluck('catatan_potongan_dll')->filter()->unique()->implode(', ');

            $payroll->total_earnings = $payroll->gaji_utama + $payroll->lembur + $payroll->bonus_target +
                $payroll->bonus_tanggal_merah + $payroll->bonus_birthday + $payroll->pengembalian_deposit + $payroll->bonus_dll;
            $payroll->total_deductions = $payroll->potongan_terlambat + $payroll->potongan_inventaris +
                $payroll->potongan_kasbon + $payroll->potongan_deposit + $payroll->potongan_dll;
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

        $pMulai = $payroll->tanggal_mulai;
        $pSelesai = $payroll->tanggal_selesai;
        if (!$pMulai && !$pSelesai && $payroll->karyawan && !$payroll->is_combined) {
            $pNum = (int)($payroll->pilihan_periode ?? 1);
            if ($pNum === 2) {
                $pMulai = $payroll->karyawan->tanggal_mulai_2 ?? $payroll->karyawan->tanggal_mulai;
                $pSelesai = $payroll->karyawan->tanggal_selesai_2 ?? $payroll->karyawan->tanggal_selesai;
            } else {
                $pMulai = $payroll->karyawan->tanggal_mulai;
                $pSelesai = $payroll->karyawan->tanggal_selesai;
            }
        }

        $queryKeterlambatan = Keterlambatan::where('karyawan_id', $payroll->karyawan_id);
        if ($pMulai && $pSelesai) {
            $queryKeterlambatan->whereBetween('tanggal', [
                \Carbon\Carbon::parse($pMulai)->format('Y-m-d'),
                \Carbon\Carbon::parse($pSelesai)->format('Y-m-d')
            ]);
        } elseif ($pMulai) {
            $queryKeterlambatan->where('tanggal', '>=', \Carbon\Carbon::parse($pMulai)->format('Y-m-d'))
                              ->whereRaw("DATE_FORMAT(tanggal, '%Y-%m') = ?", [$payroll->periode_bulan_tahun]);
        } elseif ($pSelesai) {
            $queryKeterlambatan->where('tanggal', '<=', \Carbon\Carbon::parse($pSelesai)->format('Y-m-d'))
                              ->whereRaw("DATE_FORMAT(tanggal, '%Y-%m') = ?", [$payroll->periode_bulan_tahun]);
        } else {
            $queryKeterlambatan->whereRaw("DATE_FORMAT(tanggal, '%Y-%m') = ?", [$payroll->periode_bulan_tahun]);
        }
        $listKeterlambatan = $queryKeterlambatan->orderBy('tanggal', 'asc')->get();

        return view('penggajian.public-slip', compact('payroll', 'allEntries', 'listKeterlambatan', 'selectedP', 'token'));
    }

    /**
     * Download Slip Gaji PDF Publik tanpa login
     */
    public function publicSlipPdf(Request $request, $id)
    {
        $basePayroll = Penggajian::with('karyawan')->findOrFail($id);
        $selectedP = $request->query('periode');
        $token = $request->query('token');

        $expectedToken = Penggajian::generateSlipToken($basePayroll->id, $selectedP);
        $expectedTokenNull = Penggajian::generateSlipToken($basePayroll->id, null);
        $expectedTokenAll = Penggajian::generateSlipToken($basePayroll->id, 'all');

        if (!auth()->check() && $token !== $expectedToken && $token !== $expectedTokenNull && $token !== $expectedTokenAll) {
            abort(403, 'Akses Slip Gaji tidak valid atau tautan kedaluwarsa.');
        }

        return $this->cetakPdf($request, $id);
    }

    /**
     * PROSES BAYAR GAJI KARYAWAN TUNGGAL (BAYAR + OTOMATIS BUAT JURNAL)
     */
    public function bayarKaryawan(Request $request, $id): RedirectResponse
    {
        $payroll = Penggajian::with('karyawan')->findOrFail($id);

        if ($payroll->status_jurnal) {
            return redirect()->back()->with('info', 'Slip gaji karyawan ini sudah dibayar dan dijurnal.');
        }

        $tipe = $request->input('tipe') ?? $request->query('tipe');
        $tipePembayaran = ($tipe === 'tengah_bulan' || $tipe === 'p1') ? 'tengah_bulan' : 'akhir_bulan';

        $akunBebanGaji = \App\Models\ChartOfAccount::where('kode', '6101')->first()
            ?? \App\Models\ChartOfAccount::where('kode', '6100')->first()
            ?? \App\Models\ChartOfAccount::where('nama', 'like', '%Beban Gaji%')->first();

        $akunKas = \App\Models\ChartOfAccount::where('kode', '1101')->first()
            ?? \App\Models\ChartOfAccount::where('kode', '1100')->first()
            ?? \App\Models\ChartOfAccount::where('nama', 'like', '%Kas di Bank%')->first();

        if (!$akunBebanGaji || !$akunKas) {
            return redirect()->back()->with('error', 'Gagal memposting. Akun Beban Gaji atau Kas tidak ditemukan di Chart of Accounts.');
        }

        if ($tipePembayaran === 'tengah_bulan') {
            $tanggalJurnal = $payroll->tanggal_selesai 
                ? \Carbon\Carbon::parse($payroll->tanggal_selesai)->toDateString() 
                : \Carbon\Carbon::parse($payroll->periode_bulan_tahun . '-15')->toDateString();
            $labelTipe = "tengah bulan";
        } else {
            $tanggalJurnal = $payroll->tanggal_selesai 
                ? \Carbon\Carbon::parse($payroll->tanggal_selesai)->toDateString() 
                : \Carbon\Carbon::parse($payroll->periode_bulan_tahun . '-01')->endOfMonth()->toDateString();
            $labelTipe = "akhir bulan";
        }

        $namaKaryawan = $payroll->karyawan->nama_karyawan ?? 'Karyawan';
        $rentangKet = ($payroll->tanggal_mulai && $payroll->tanggal_selesai)
            ? " (" . \Carbon\Carbon::parse($payroll->tanggal_mulai)->format('d/m/Y') . " - " . \Carbon\Carbon::parse($payroll->tanggal_selesai)->format('d/m/Y') . ")"
            : "";

        DB::transaction(function () use ($payroll, $akunBebanGaji, $akunKas, $tanggalJurnal, $namaKaryawan, $rentangKet, $tipePembayaran, $labelTipe) {
            $journal = Journal::create([
                'tanggal'     => $tanggalJurnal,
                'deskripsi'   => "Pembayaran gaji {$labelTipe} karyawan {$namaKaryawan}{$rentangKet}",
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
                'status'          => 'approved',
                'status_jurnal'   => true,
                'journal_id'      => $journal->id,
                'tipe_pembayaran' => $tipePembayaran,
            ]);
        });

        return redirect()->back()->with('success', "Gaji {$labelTipe} {$namaKaryawan}{$rentangKet} sebesar Rp " . number_format($payroll->total_gaji_bersih, 0, ',', '.') . " berhasil dibayar & dijurnal!");
    }

    /**
     * PROSES BAYAR SEMUA KARYAWAN DALAM PERIODE TERSEBUT (MASSAL)
     */
    public function bayarSemuaPeriode(Request $request, $periode): RedirectResponse
    {
        $tipe = $request->input('tipe') ?? $request->query('tipe') ?? 'all';
        $selectedOutlet = $this->getOutlet($request);

        $query = Penggajian::with('karyawan')
            ->where('periode_bulan_tahun', $periode)
            ->where('status_jurnal', false)
            ->where(function ($q) use ($selectedOutlet) {
                $q->where('outlet', $selectedOutlet)
                  ->orWhereHas('karyawan', function ($kq) use ($selectedOutlet) {
                      $kq->where('outlet', $selectedOutlet);
                  });
            });

        $allPayrolls = $query->get();

        if ($tipe === 'tengah_bulan' || $tipe === 'p1') {
            // Filter hanya slip P1 untuk karyawan yang memiliki 2 periode
            $payrolls = $allPayrolls->filter(function($p) {
                $kw = $p->karyawan;
                if (!$kw) return false;
                $p2Total = floatval($kw->gaji_pokok_2 ?? 0) + floatval($kw->uang_makan_2 ?? 0) + floatval($kw->uang_transport_2 ?? 0);
                $hasP2 = ($kw->gaji_pokok_2 !== null && $p2Total > 0);
                return (int)($p->pilihan_periode ?? 1) === 1 && $hasP2;
            });
            $tipeLabel = "Gaji Tengah Bulan (P1)";
            $setTipe = 'tengah_bulan';
        } elseif ($tipe === 'akhir_bulan' || $tipe === 'p2') {
            // Filter slip P2 untuk karyawan 2 periode ATAU slip karyawan single period / bulanan
            $payrolls = $allPayrolls->filter(function($p) {
                $kw = $p->karyawan;
                if (!$kw) return true;
                $p2Total = floatval($kw->gaji_pokok_2 ?? 0) + floatval($kw->uang_makan_2 ?? 0) + floatval($kw->uang_transport_2 ?? 0);
                $hasP2 = ($kw->gaji_pokok_2 !== null && $p2Total > 0);
                if ($hasP2) {
                    return (int)($p->pilihan_periode ?? 1) === 2;
                } else {
                    return true;
                }
            });
            $tipeLabel = "Gaji Akhir Bulan (P2 & Bulanan)";
            $setTipe = 'akhir_bulan';
        } else {
            $payrolls = $allPayrolls;
            $tipeLabel = "Seluruh Gaji";
            $setTipe = 'akhir_bulan';
        }

        if ($payrolls->isEmpty()) {
            return redirect()->back()->with('info', "Tidak ada data {$tipeLabel} yang belum terbayar di periode {$periode}.");
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

        $totalGaji = $payrolls->sum(function($p) {
            if ($p->total_gaji_bersih > 0) return (float)$p->total_gaji_bersih;
            $earn = (float)($p->total_earnings > 0 ? $p->total_earnings : ($p->gaji_utama + $p->lembur + $p->bonus_target + $p->bonus_tanggal_merah + $p->bonus_birthday + $p->pengembalian_deposit + $p->bonus_dll));
            $ded = (float)($p->total_deductions > 0 ? $p->total_deductions : ($p->potongan_terlambat + $p->potongan_inventaris + $p->potongan_kasbon + $p->potongan_deposit + $p->potongan_dll));
            return max(0, $earn - $ded);
        });

        $tanggalJurnal = ($tipe === 'tengah_bulan' || $tipe === 'p1')
            ? \Carbon\Carbon::parse($periode . '-15')->toDateString()
            : \Carbon\Carbon::parse($periode . '-01')->endOfMonth()->toDateString();

        DB::transaction(function () use ($payrolls, $periode, $totalGaji, $akunBebanGaji, $akunKas, $tanggalJurnal, $tipeLabel, $setTipe) {
            $journal = Journal::create([
                'tanggal'     => $tanggalJurnal,
                'deskripsi'   => "Pencatatan pembayaran {$tipeLabel} periode {$periode}",
                'no_ref'      => 'PY-' . strtoupper(substr(preg_replace('/[^a-zA-Z]/', '', $tipeLabel), 0, 4)) . '-' . strtoupper(str_replace('-', '', $periode)) . '-' . rand(100, 999),
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
                    'status'          => 'approved',
                    'status_jurnal'   => true,
                    'journal_id'      => $journal->id,
                    'tipe_pembayaran' => $setTipe,
                ]);
            }
        });

        return redirect()->back()->with('success', "Pembayaran {$tipeLabel} periode {$periode} (Total Rp " . number_format($totalGaji, 0, ',', '.') . ") berhasil diproses & diposting ke Jurnal Umum!");
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

        $gp2 = floatval($karyawan->gaji_pokok_2 ?? 0);
        $um2 = floatval($karyawan->uang_makan_2 ?? 0);
        $ut2 = floatval($karyawan->uang_transport_2 ?? 0);
        $tarif2 = $gp2 + $um2 + $ut2;
        $hasP2 = ($karyawan->gaji_pokok_2 !== null && $tarif2 > 0);

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

    /**
     * Batch update hari kerja / gaji pokok untuk seluruh karyawan langsung dari tabel
     */
    public function batchUpdateGajiPokok(Request $request)
    {
        $items = $request->input('items', []);
        $periode = $request->input('periode');
        $outlet = $request->input('outlet', 'Gaharu');

        if (empty($items)) {
            if ($request->wantsJson() || $request->ajax()) {
                return response()->json(['success' => false, 'message' => 'Tidak ada data yang dikirim.'], 400);
            }
            return redirect()->back()->with('error', 'Tidak ada data yang dikirim.');
        }

        $updatedCount = 0;

        foreach ($items as $item) {
            $karyawanId = $item['karyawan_id'] ?? null;
            $payrollId  = $item['id'] ?? null;
            $hariKerja  = floatval($item['hari_kerja'] ?? 0);

            $payroll = null;
            if ($payrollId) {
                $payroll = Penggajian::find($payrollId);
            } elseif ($karyawanId && $periode) {
                $payroll = Penggajian::where('karyawan_id', $karyawanId)
                    ->where('periode_bulan_tahun', $periode)
                    ->where('pilihan_periode', $item['pilihan_periode'] ?? 1)
                    ->first();
            }

            if ($payroll && $payroll->status === 'approved') continue;

            $karyawan = $payroll ? $payroll->karyawan : ($karyawanId ? Karyawan::find($karyawanId) : null);
            if (!$karyawan) continue;

            $pilihanPeriode = intval($item['pilihan_periode'] ?? ($payroll->pilihan_periode ?? 1));
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
            $gajiUtama = ($satuanGaji === 'Bulanan') ? $tarifHarianTotal : ($hariKerja * $tarifHarianTotal);

            // Helper pembersih rupiah
            $cleanRupiah = function ($value) {
                if (is_numeric($value)) return (float)$value;
                if (empty($value)) return 0.0;
                return (float) preg_replace('/[^0-9.]/', '', str_replace(',', '.', (string)$value));
            };

            // 1. Perhitungan Bonus & Lembur (jika dikirim dari formulir gabungan)
            $jamLembur             = array_key_exists('jam_lembur', $item) ? floatval($item['jam_lembur']) : floatval($payroll->jam_lembur ?? 0);
            $lembur                = $jamLembur * 10000;
            $banyakBirthdayService = array_key_exists('banyak_birthday_service', $item) ? intval($item['banyak_birthday_service']) : intval($payroll->banyak_birthday_service ?? 0);
            $bonusBirthdayService  = $banyakBirthdayService * 5000;
            $pengembalianDeposit   = array_key_exists('pengembalian_deposit', $item) ? $cleanRupiah($item['pengembalian_deposit']) : (float)($payroll->pengembalian_deposit ?? 0);
            $bonusDll              = array_key_exists('bonus_dll', $item) ? $cleanRupiah($item['bonus_dll']) : (float)($payroll->bonus_dll ?? 0);
            $catatanBonusDll       = array_key_exists('catatan_bonus_dll', $item) ? $item['catatan_bonus_dll'] : ($payroll->catatan_bonus_dll ?? null);

            if ($satuanGaji === 'Harian') {
                $banyakTarget        = array_key_exists('banyak_target', $item) ? intval($item['banyak_target']) : intval($payroll->banyak_target ?? 0);
                $banyakTanggalMerah  = array_key_exists('banyak_tanggal_merah', $item) ? intval($item['banyak_tanggal_merah']) : intval($payroll->banyak_tanggal_merah ?? 0);
                $bonusTarget         = $banyakTarget * $tarifHarianTotal;
                $bonusTanggalMerah   = $banyakTanggalMerah * $tarifHarianTotal;
                $catatanTarget       = null;
                $catatanTanggalMerah = null;
            } else {
                $banyakTarget        = 0;
                $banyakTanggalMerah  = 0;
                $bonusTarget         = array_key_exists('bonus_target', $item) ? $cleanRupiah($item['bonus_target']) : (float)($payroll->bonus_target ?? 0);
                $bonusTanggalMerah   = array_key_exists('bonus_tanggal_merah', $item) ? $cleanRupiah($item['bonus_tanggal_merah']) : (float)($payroll->bonus_tanggal_merah ?? 0);
                $catatanTarget       = array_key_exists('catatan_bonus_target', $item) ? $item['catatan_bonus_target'] : ($payroll->catatan_bonus_target ?? null);
                $catatanTanggalMerah = array_key_exists('catatan_bonus_tanggal_merah', $item) ? $item['catatan_bonus_tanggal_merah'] : ($payroll->catatan_bonus_tanggal_merah ?? null);
            }

            // 2. Perhitungan Potongan & Pengurangan (jika dikirim dari formulir gabungan)
            $potonganInventaris = array_key_exists('potongan_inventaris', $item) ? $cleanRupiah($item['potongan_inventaris']) : (float)($payroll->potongan_inventaris ?? 0);
            $potonganKasbon     = array_key_exists('potongan_kasbon', $item) ? $cleanRupiah($item['potongan_kasbon']) : (float)($payroll->potongan_kasbon ?? 0);
            $potonganDeposit    = array_key_exists('potongan_deposit', $item) ? $cleanRupiah($item['potongan_deposit']) : (float)($payroll->potongan_deposit ?? 0);
            $potonganDll        = array_key_exists('potongan_dll', $item) ? $cleanRupiah($item['potongan_dll']) : (float)($payroll->potongan_dll ?? 0);
            $catatanPotonganDll = array_key_exists('catatan_potongan_dll', $item) ? $item['catatan_potongan_dll'] : ($payroll->catatan_potongan_dll ?? null);

            if ($payroll) {
                $potonganTerlambat = floatval($payroll->potongan_terlambat ?? 0);
                $totalEarnings   = $gajiUtama + $lembur + $bonusTarget + $bonusTanggalMerah + $bonusBirthdayService + $pengembalianDeposit + $bonusDll;
                $totalDeductions = $potonganTerlambat + $potonganInventaris + $potonganKasbon + $potonganDeposit + $potonganDll;
                $totalGajiBersih = $totalEarnings - $totalDeductions;

                $payroll->update([
                    'hari_kerja'                  => $hariKerja,
                    'pilihan_periode'             => $pilihanPeriode,
                    'satuan_gaji'                 => $satuanGaji,
                    'gaji_pokok'                  => $gajiPokokHarian,
                    'tunjangan_makan'             => $uangMakan,
                    'tunjangan_transport'         => $uangTransport,
                    'tarif_harian_total'          => $tarifHarianTotal,
                    'gaji_utama'                  => $gajiUtama,
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
                    'pengembalian_deposit'        => $pengembalianDeposit,
                    'bonus_dll'                   => $bonusDll,
                    'catatan_bonus_dll'           => $catatanBonusDll,
                    'potongan_inventaris'         => $potonganInventaris,
                    'potongan_kasbon'             => $potonganKasbon,
                    'potongan_deposit'            => $potonganDeposit,
                    'potongan_dll'                => $potonganDll,
                    'catatan_potongan_dll'        => $catatanPotonganDll,
                    'total_earnings'              => $totalEarnings,
                    'total_deductions'            => $totalDeductions,
                    'total_gaji_bersih'           => $totalGajiBersih,
                ]);
            } else {
                // Buat record baru jika belum ada
                $pMulai = ($pilihanPeriode === 2) ? ($karyawan->tanggal_mulai_2 ?? $karyawan->tanggal_mulai) : $karyawan->tanggal_mulai;
                $pSelesai = ($pilihanPeriode === 2) ? ($karyawan->tanggal_selesai_2 ?? $karyawan->tanggal_selesai) : $karyawan->tanggal_selesai;

                $qLate = Keterlambatan::where('karyawan_id', $karyawan->id);
                if ($pMulai && $pSelesai) {
                    $qLate->whereBetween('tanggal', [
                        \Carbon\Carbon::parse($pMulai)->format('Y-m-d'),
                        \Carbon\Carbon::parse($pSelesai)->format('Y-m-d')
                    ]);
                } elseif ($pMulai) {
                    $qLate->where('tanggal', '>=', \Carbon\Carbon::parse($pMulai)->format('Y-m-d'))
                          ->whereRaw("DATE_FORMAT(tanggal, '%Y-%m') = ?", [$periode]);
                } elseif ($pSelesai) {
                    $qLate->where('tanggal', '<=', \Carbon\Carbon::parse($pSelesai)->format('Y-m-d'))
                          ->whereRaw("DATE_FORMAT(tanggal, '%Y-%m') = ?", [$periode]);
                } else {
                    $qLate->whereRaw("DATE_FORMAT(tanggal, '%Y-%m') = ?", [$periode]);
                }
                $potonganTerlambat = floatval($qLate->sum('potongan'));
                $totalEarnings   = $gajiUtama + $lembur + $bonusTarget + $bonusTanggalMerah + $bonusBirthdayService + $pengembalianDeposit + $bonusDll;
                $totalDeductions = $potonganTerlambat + $potonganInventaris + $potonganKasbon + $potonganDeposit + $potonganDll;
                $totalGajiBersih = $totalEarnings - $totalDeductions;

                Penggajian::create([
                    'karyawan_id'                 => $karyawan->id,
                    'outlet'                      => $karyawan->outlet ?? $outlet,
                    'satuan_gaji'                 => $satuanGaji,
                    'satuan_gaji_2'               => $karyawan->satuan_gaji_2 ?? $satuanGaji,
                    'pilihan_periode'             => $pilihanPeriode,
                    'periode_bulan_tahun'         => $periode,
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
                    'pengembalian_deposit'        => $pengembalianDeposit,
                    'bonus_dll'                   => $bonusDll,
                    'catatan_bonus_dll'           => $catatanBonusDll,
                    'potongan_terlambat'          => $potonganTerlambat,
                    'potongan_inventaris'         => $potonganInventaris,
                    'potongan_kasbon'             => $potonganKasbon,
                    'potongan_deposit'            => $potonganDeposit,
                    'potongan_dll'                => $potonganDll,
                    'catatan_potongan_dll'        => $catatanPotonganDll,
                    'total_earnings'              => $totalEarnings,
                    'total_deductions'            => $totalDeductions,
                    'total_gaji_bersih'           => $totalGajiBersih,
                    'status'                      => 'draft',
                    'status_jurnal'               => false
                ]);
            }

            $updatedCount++;
        }

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => "Berhasil menyimpan gaji pokok / hari kerja untuk {$updatedCount} karyawan."
            ]);
        }

        return redirect()->route('penggajian.periode', ['periode' => $periode, 'outlet' => $outlet])
            ->with('success', "Seluruh waktu kerja ({$updatedCount} karyawan) berhasil disimpan secara bersamaan.");
    }
}
