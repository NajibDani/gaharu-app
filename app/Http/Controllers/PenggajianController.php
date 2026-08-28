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

        // Cek duplikat: izinkan jika rentang tanggal berbeda (probation split)
        $duplicateQuery = Penggajian::where('karyawan_id', $request->karyawan_id)
            ->where('periode_bulan_tahun', $request->periode);

        if ($request->tanggal_mulai && $request->tanggal_selesai) {
            // Cek overlap rentang tanggal
            $duplicateQuery->where(function ($q) use ($request) {
                $q->where(function ($q2) use ($request) {
                    $q2->where('tanggal_mulai', '<=', $request->tanggal_selesai)
                       ->where('tanggal_selesai', '>=', $request->tanggal_mulai);
                });
            });
        }

        if ($duplicateQuery->exists()) {
            return back()->withErrors(['karyawan_id' => 'Gaji karyawan ini untuk periode/rentang tanggal tersebut sudah diinput sebelumnya.'])->withInput();
        }

        $karyawan = Karyawan::findOrFail($request->karyawan_id);

        $cleanRupiah = function ($value) {
            if (is_null($value)) return 0;
            return (float) preg_replace('/[^0-9.]/', '', str_replace(',', '.', $value));
        };

        // 1. Data Dasar Harian & Gaji Utama (Kalkulasi Berbasis Fluktuasi Periode Gaji Karyawan)
        $hariKerja = floatval($request->hari_kerja ?? 0);
        $calcTariff = $this->calculateWeightedTariff($karyawan, $request->tanggal_mulai, $request->tanggal_selesai, $request->periode, $hariKerja);

        $gajiPokokHarian  = $calcTariff['gaji_pokok'];
        $uangMakan        = $calcTariff['uang_makan'];
        $uangTransport    = $calcTariff['uang_transport'];
        $tarifHarianTotal = $calcTariff['tarif_harian_total'];
        $gajiUtama        = $calcTariff['gaji_utama'];

        // 2. Presensi & Kinerja
        $jamLembur              = floatval($request->jam_lembur ?? 0);
        $banyakTarget           = intval($request->banyak_target ?? 0);
        $banyakTanggalMerah     = intval($request->banyak_tanggal_merah ?? 0);
        $banyakBirthdayService  = intval($request->banyak_birthday_service ?? 0);

        // 3. Kalkulasi Earnings (Pendapatan)
        $lembur               = $jamLembur * 10000;
        $bonusTarget          = $banyakTarget * $tarifHarianTotal;
        $bonusTanggalMerah    = $banyakTanggalMerah * $tarifHarianTotal;
        $bonusBirthdayService = $banyakBirthdayService * 5000;
        $bonusDll             = $cleanRupiah($request->bonus_dll);

        $totalEarnings = $gajiUtama + $lembur + $bonusTarget + $bonusTanggalMerah + $bonusBirthdayService + $bonusDll;

        // 4. Kalkulasi Deductions (Pengurangan)
        $potonganTerlambat  = $cleanRupiah($request->potongan_terlambat);
        $potonganInventaris = $cleanRupiah($request->potongan_inventaris);
        $potonganKasbon     = $cleanRupiah($request->potongan_kasbon);
        $potonganDll        = $cleanRupiah($request->potongan_dll);

        $totalDeductions = $potonganTerlambat + $potonganInventaris + $potonganKasbon + $potonganDll;

        // 5. Gaji Bersih (Take Home Pay)
        $totalGajiBersih = $totalEarnings - $totalDeductions;

        $existingStatus = Penggajian::where('periode_bulan_tahun', $request->periode)->first()?->status ?? 'draft';

        Penggajian::create([
            'karyawan_id'             => $karyawan->id,
            'outlet'                  => $karyawan->outlet ?? 'Gaharu',
            'periode_bulan_tahun'     => $request->periode,
            'tanggal_mulai'           => $request->tanggal_mulai,
            'tanggal_selesai'         => $request->tanggal_selesai,
            'hari_kerja'              => $hariKerja,
            'tarif_harian_total'      => $tarifHarianTotal,
            'gaji_utama'              => $gajiUtama,
            'gaji_pokok'              => $gajiPokokHarian,
            'tunjangan_transport'     => $uangTransport,
            'tunjangan_makan'         => $uangMakan,
            'jam_lembur'              => $jamLembur,
            'lembur'                  => $lembur,
            'banyak_target'           => $banyakTarget,
            'bonus_target'            => $bonusTarget,
            'banyak_tanggal_merah'    => $banyakTanggalMerah,
            'bonus_tanggal_merah'     => $bonusTanggalMerah,
            'banyak_birthday_service' => $banyakBirthdayService,
            'bonus_birthday'          => $bonusBirthdayService,
            'bonus_dll'               => $bonusDll,
            'potongan_terlambat'      => $potonganTerlambat,
            'potongan_inventaris'     => $potonganInventaris,
            'potongan_kasbon'         => $potonganKasbon,
            'potongan_dll'            => $potonganDll,
            'total_earnings'          => $totalEarnings,
            'total_deductions'        => $totalDeductions,
            'total_gaji_bersih'       => $totalGajiBersih,
            'status'                  => $existingStatus,
            'status_jurnal'           => false
        ]);

        return redirect()->route('penggajian.show-periode', ['periode' => $request->periode, 'outlet' => $karyawan->outlet ?? 'Gaharu'])
            ->with('success', "Data gaji {$karyawan->nama_karyawan} berhasil ditambahkan ke periode.");
    }


    /**
     * HALAMAN BARU: Menampilkan daftar karyawan khusus pada periode tertentu (Hasil klik tombol Detail Karyawan)
     */
    public function periodeDetail(Request $request)
    {
        $periode = $request->query('periode');
        $selectedOutlet = $this->getOutlet($request);

        // Ambil semua data karyawan yang ada di periode & outlet ini
        $payrolls = Penggajian::with('karyawan')
            ->where('periode_bulan_tahun', $periode)
            ->where(function ($q) use ($selectedOutlet) {
                $q->where('outlet', $selectedOutlet)
                  ->orWhereHas('karyawan', function ($kq) use ($selectedOutlet) {
                      $kq->where('outlet', $selectedOutlet);
                  });
            })
            ->get();

        // Otomatis sinkronkan potongan keterlambatan untuk slip draft / waiting approval di periode ini
        foreach ($payrolls as $payroll) {
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

        if ($payrolls->isEmpty()) {
            $currentStatus = 'draft';
        } else {
            $currentStatus = $payrolls->first()->status;
        }

        return view('penggajian.show-periode', compact('payrolls', 'periode', 'currentStatus', 'selectedOutlet'));
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
            $gajiPokok = floatval($k->gaji_pokok ?? 0);
            $uangMakan = floatval($k->uang_makan ?? 0);
            $uangTransport = floatval($k->uang_transport ?? 0);
            $tarifHarian = $gajiPokok + $uangMakan + $uangTransport;

            // Otomatis hitung akumulasi denda keterlambatan bulan ini dari tabel Keterlambatan
            $potonganTerlambat = Keterlambatan::where('karyawan_id', $k->id)
                ->whereRaw("DATE_FORMAT(tanggal, '%Y-%m') = ?", [$periode])
                ->sum('potongan');

            $totalDeductions = floatval($potonganTerlambat);
            $totalGajiBersih = 0 - $totalDeductions;

            Penggajian::create([
                'karyawan_id'             => $k->id,
                'outlet'                  => $k->outlet ?? $selectedOutlet,
                'periode_bulan_tahun'     => $periode,
                'hari_kerja'              => 0,
                'tarif_harian_total'      => $tarifHarian,
                'gaji_utama'              => 0,
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
                'total_earnings'          => 0,
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

        // 1. Data Dasar Harian & Gaji Utama (Kalkulasi Berbasis Fluktuasi Periode Gaji Karyawan)
        $hariKerja = floatval($request->hari_kerja ?? 0);
        $calcTariff = $this->calculateWeightedTariff($karyawan, $request->tanggal_mulai, $request->tanggal_selesai, $payroll->periode_bulan_tahun, $hariKerja);

        $gajiPokokHarian  = $calcTariff['gaji_pokok'];
        $uangMakan        = $calcTariff['uang_makan'];
        $uangTransport    = $calcTariff['uang_transport'];
        $tarifHarianTotal = $calcTariff['tarif_harian_total'];
        $gajiUtama        = $calcTariff['gaji_utama'];

        // 2. Presensi & Kinerja
        $jamLembur              = floatval($request->jam_lembur ?? 0);
        $banyakTarget           = intval($request->banyak_target ?? 0);
        $banyakTanggalMerah     = intval($request->banyak_tanggal_merah ?? 0);
        $banyakBirthdayService  = intval($request->banyak_birthday_service ?? 0);

        // 3. Kalkulasi Earnings (Pendapatan)
        $lembur               = $jamLembur * 10000;
        $bonusTarget          = $banyakTarget * $tarifHarianTotal;
        $bonusTanggalMerah    = $banyakTanggalMerah * $tarifHarianTotal;
        $bonusBirthdayService = $banyakBirthdayService * 5000;
        $bonusDll             = $cleanRupiah($request->bonus_dll);

        $totalEarnings = $gajiUtama + $lembur + $bonusTarget + $bonusTanggalMerah + $bonusBirthdayService + $bonusDll;

        // 4. Kalkulasi Deductions (Pengurangan)
        $potonganTerlambat  = $cleanRupiah($request->potongan_terlambat);
        $potonganInventaris = $cleanRupiah($request->potongan_inventaris);
        $potonganKasbon     = $cleanRupiah($request->potongan_kasbon);
        $potonganDll        = $cleanRupiah($request->potongan_dll);

        $totalDeductions = $potonganTerlambat + $potonganInventaris + $potonganKasbon + $potonganDll;

        // 5. Gaji Bersih (Take Home Pay)
        $totalGajiBersih = $totalEarnings - $totalDeductions;

        $payroll->update([
            'tanggal_mulai'           => $request->tanggal_mulai,
            'tanggal_selesai'         => $request->tanggal_selesai,
            'hari_kerja'              => $hariKerja,
            'tarif_harian_total'      => $tarifHarianTotal,
            'gaji_utama'              => $gajiUtama,
            'gaji_pokok'              => $gajiPokokHarian,
            'tunjangan_transport'     => $uangTransport,
            'tunjangan_makan'         => $uangMakan,
            'jam_lembur'              => $jamLembur,
            'lembur'                  => $lembur,
            'banyak_target'           => $banyakTarget,
            'bonus_target'            => $bonusTarget,
            'banyak_tanggal_merah'    => $banyakTanggalMerah,
            'bonus_tanggal_merah'     => $bonusTanggalMerah,
            'banyak_birthday_service' => $banyakBirthdayService,
            'bonus_birthday'          => $bonusBirthdayService,
            'bonus_dll'               => $bonusDll,
            'potongan_terlambat'      => $potonganTerlambat,
            'potongan_inventaris'     => $potonganInventaris,
            'potongan_kasbon'         => $potonganKasbon,
            'potongan_dll'            => $potonganDll,
            'total_earnings'          => $totalEarnings,
            'total_deductions'        => $totalDeductions,
            'total_gaji_bersih'       => $totalGajiBersih,
        ]);

        // Kembalikan ke halaman detail kelompok karyawan per periode dengan pesan sukses
        return redirect()->route('penggajian.show-periode', ['periode' => $payroll->periode_bulan_tahun])
            ->with('success', 'Data gaji ' . $payroll->karyawan->nama_karyawan . ' berhasil diperbarui.');
    }

    public function show($id)
    {
        // Ambil data penggajian satu karyawan beserta relasi datanya
        $payroll = Penggajian::with('karyawan')->findOrFail($id);

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

        // Hitung akumulasi Subtotal Penerimaan Tetap
        $total_tetap = ($payroll->gaji_pokok ?? 0) +
            ($payroll->tunjangan_transport ?? 0) +
            ($payroll->tunjangan_makan ?? 0);

        // Hitung akumulasi Subtotal Penerimaan Tidak Tetap (Bonus & Lembur)
        $total_tidak_tetap = ($payroll->lembur ?? 0) +
            ($payroll->bonus_target ?? 0) +
            ($payroll->bonus_tanggal_merah ?? 0) +
            ($payroll->bonus_birthday ?? 0) +
            ($payroll->bonus_dll ?? 0);

        // Hitung akumulasi Subtotal Potongan
        $total_potongan = ($payroll->potongan_inventaris ?? 0) +
            ($payroll->potongan_terlambat ?? 0) +
            ($payroll->potongan_kasbon ?? 0) +
            ($payroll->potongan_dll ?? 0);

        // Hitung Take Home Pay (Gaji Bersih Akhir)
        $total_gaji_bersih = ($total_tetap + $total_tidak_tetap) - $total_potongan;

        // Kirim semua variabel perhitungan ke view show
        return view('penggajian.show', compact(
            'payroll',
            'listKeterlambatan',
            'total_tetap',
            'total_tidak_tetap',
            'total_potongan',
            'total_gaji_bersih'
        ));
    }

    /**
     * Download Slip Gaji sebagai PDF
     */
    public function cetakPdf($id)
    {
        $payroll = Penggajian::with('karyawan')->findOrFail($id);

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

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('penggajian.slip-pdf', compact('payroll', 'listKeterlambatan'))
            ->setPaper('a4', 'portrait');

        $outletName = $payroll->outlet ?? $payroll->karyawan->outlet ?? 'Gaharu';
        $namaKaryawan = \Illuminate\Support\Str::slug($payroll->karyawan->nama_karyawan ?? 'karyawan');
        $filename = "Slip_Gaji_{$namaKaryawan}_{$outletName}_{$payroll->periode_bulan_tahun}.pdf";

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

        if (!$hasP2) {
            $gajiUtama = $tarif1 * $hariKerja;
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

        $hariP1 = $hariKerja * $prop1;
        $hariP2 = $hariKerja * $prop2;

        $gajiUtama = ($hariP1 * $tarif1) + ($hariP2 * $tarif2);

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
