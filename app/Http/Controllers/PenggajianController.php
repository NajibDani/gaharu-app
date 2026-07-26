<?php

namespace App\Http\Controllers;

use App\Models\Penggajian;
use App\Models\Karyawan;
use App\Models\Journal;
use App\Models\JournalItem;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class PenggajianController extends Controller
{
    /**
     * TAMPILAN UTAMA: Mengirimkan data penggajian yang sudah di-group berdasarkan periode.
     * Ini digunakan untuk mengisi baris kotak periode di halaman depan.
     */
    public function index(Request $request)
    {
        $search = $request->query('search');

        // Paginate by unique periods
        $periodsQuery = Penggajian::select('periode_bulan_tahun')
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

        // Get all payrolls for the paginated periods
        $payrolls = Penggajian::with('karyawan')
            ->whereIn('periode_bulan_tahun', $periodNames)
            ->orderBy('periode_bulan_tahun', 'desc')
            ->get();

        $karyawans = Karyawan::all();

        return view('penggajian.index', compact('payrolls', 'periods', 'karyawans'));
    }

    public function create(Request $request): View
    {
        // Menangkap parameter target_periode dari URL agar form input tahu ini untuk periode mana
        $target_periode = $request->query('target_periode');
        
        // Ambil ID karyawan yang sudah diinput gajinya pada periode ini
        $alreadyPaidIds = Penggajian::where('periode_bulan_tahun', $target_periode)
            ->pluck('karyawan_id')
            ->toArray();
            
        // Filter karyawan yang belum diinput
        $karyawans = Karyawan::whereNotIn('id', $alreadyPaidIds)->get();

        return view('penggajian.create', compact('karyawans', 'target_periode'));
    }

    /**
     * MENYIMPAN GAJI PER KARYAWAN
     */
    public function store(Request $request)
    {
        $request->validate([
            'karyawan_id'          => 'required|exists:karyawan,id',
            'periode'              => 'required|string|max:50',
            'gaji_pokok'           => 'required|string',
            'tunjangan_transport'  => 'nullable|string',
            'tunjangan_makan'      => 'nullable|string',
            'lembur'               => 'nullable|string',
            'bonus_target'         => 'nullable|string',
            'bonus_tanggal_merah'  => 'nullable|string',
            'bonus_birthday'       => 'nullable|string',
            'bonus_dll'            => 'nullable|string',
            'potongan_inventaris'  => 'nullable|string',
            'potongan_terlambat'   => 'nullable|string',
        ]);

        $exists = Penggajian::where('karyawan_id', $request->karyawan_id)
            ->where('periode_bulan_tahun', $request->periode)
            ->exists();

        if ($exists) {
            return back()->withErrors(['karyawan_id' => 'Gaji karyawan ini untuk periode tersebut sudah diinput sebelumnya.'])->withInput();
        }

        $cleanRupiah = function ($value) {
            if (is_null($value)) return 0;
            return (int) preg_replace('/[^0-9]/', '', $value);
        };

        $gaji_pokok = $cleanRupiah($request->gaji_pokok);
        $tunjangan_transport = $cleanRupiah($request->tunjangan_transport);
        $tunjangan_makan = $cleanRupiah($request->tunjangan_makan);

        $lembur = $cleanRupiah($request->lembur);
        $bonus_target = $cleanRupiah($request->bonus_target);
        $bonus_tanggal_merah = $cleanRupiah($request->bonus_tanggal_merah);
        $bonus_birthday = $cleanRupiah($request->bonus_birthday);
        $bonus_dll = $cleanRupiah($request->bonus_dll);

        $potongan_inventaris = $cleanRupiah($request->potongan_inventaris);
        $potongan_terlambat = $cleanRupiah($request->potongan_terlambat);

        $total_tetap = $gaji_pokok + $tunjangan_transport + $tunjangan_makan;
        $total_tidak_tetap = $lembur + $bonus_target + $bonus_tanggal_merah + $bonus_birthday + $bonus_dll;
        $total_potongan = $potongan_inventaris + $potongan_terlambat;

        $total_gaji_bersih = ($total_tetap + $total_tidak_tetap) - $total_potongan;

        // Cek status terakhir dari periode ini di database agar data karyawan baru langsung menyesuaikan statusnya
        $existingStatus = Penggajian::where('periode_bulan_tahun', $request->periode)->first()?->status ?? 'draft';

        $payroll = Penggajian::create([
            'karyawan_id'           => $request->karyawan_id,
            'periode_bulan_tahun'   => $request->periode,
            'gaji_pokok'            => $gaji_pokok,
            'tunjangan_transport'   => $tunjangan_transport,
            'tunjangan_makan'       => $tunjangan_makan,
            'lembur'                => $lembur,
            'bonus_target'          => $bonus_target,
            'bonus_tanggal_merah'   => $bonus_tanggal_merah,
            'bonus_birthday'        => $bonus_birthday,
            'bonus_dll'             => $bonus_dll,
            'potongan_inventaris'   => $potongan_inventaris,
            'potongan_terlambat'    => $potongan_terlambat,
            'total_gaji_bersih'     => $total_gaji_bersih,
            'status'                => $existingStatus,
            'status_jurnal'         => false
        ]);

        // Setelah input 1 karyawan selesai, otomatis dialihkan kembali ke detail periode tersebut
        return redirect()->route('penggajian.show-periode', ['periode' => $request->periode])
            ->with('success', 'Data gaji karyawan berhasil ditambahkan ke periode.');
    }


    /**
     * HALAMAN BARU: Menampilkan daftar karyawan khusus pada periode tertentu (Hasil klik tombol Detail Karyawan)
     */
    public function periodeDetail(Request $request)
    {
        $periode = $request->query('periode');

        // Ambil semua data karyawan yang ada di periode ini
        $payrolls = Penggajian::with('karyawan')
            ->where('periode_bulan_tahun', $periode)
            ->get();

        if ($payrolls->isEmpty()) {
            // Jika periodenya baru dibuat kosong, kita kirim objek kosong namun tetap bawa variabel periodenya
            $currentStatus = 'draft';
        } else {
            $currentStatus = $payrolls->first()->status;
        }

        return view('penggajian.show-periode', compact('payrolls', 'periode', 'currentStatus'));
    }

    /**
     * AUTO-FILL SEMUA KARYAWAN AKTIF KE DALAM PERIODE
     */
    public function autoFill(Request $request)
    {
        $periode = $request->input('periode');
        if (!$periode) {
            return back()->with('error', 'Periode penggajian tidak valid.');
        }

        // Ambil ID karyawan yang sudah diinput gajinya pada periode ini
        $alreadyPaidIds = Penggajian::where('periode_bulan_tahun', $periode)
            ->pluck('karyawan_id')
            ->toArray();

        // Ambil semua karyawan aktif yang belum ada di periode ini
        $karyawans = Karyawan::whereNotIn('id', $alreadyPaidIds)->get();

        if ($karyawans->isEmpty()) {
            return back()->with('info', 'Semua karyawan aktif sudah terdaftar pada periode ini.');
        }

        // Cek status periode yang ada
        $existingStatus = Penggajian::where('periode_bulan_tahun', $periode)->first()?->status ?? 'draft';

        $count = 0;
        foreach ($karyawans as $k) {
            $gajiPokok = floatval($k->gaji_pokok ?? 0);
            Penggajian::create([
                'karyawan_id'           => $k->id,
                'periode_bulan_tahun'   => $periode,
                'gaji_pokok'            => $gajiPokok,
                'tunjangan_transport'   => 0,
                'tunjangan_makan'       => 0,
                'lembur'                => 0,
                'bonus_target'          => 0,
                'bonus_tanggal_merah'   => 0,
                'bonus_birthday'        => 0,
                'bonus_dll'             => 0,
                'potongan_inventaris'   => 0,
                'potongan_terlambat'    => 0,
                'total_gaji_bersih'     => $gajiPokok,
                'status'                => $existingStatus,
                'status_jurnal'         => false,
            ]);
            $count++;
        }

        return redirect()->route('penggajian.show-periode', ['periode' => $periode])
            ->with('success', "Berhasil meng-autofill {$count} karyawan ke periode {$periode}.");
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

        // 5. BELOKKAN KE VIEW CREATE (Membawa variabel $payroll data lama)
        return view('penggajian.create', compact('payroll', 'karyawans', 'target_periode'));
    }

    /**
     * Memproses pembaharuan nominal gaji yang diedit oleh HRD
     */
    public function update(Request $request, $id): RedirectResponse
    {
        $payroll = Penggajian::findOrFail($id);

        // Pastikan kembali data belum di-approve sebelum melakukan update
        if ($payroll->status === 'approved') {
            return redirect()->back()->with('error', 'Perubahan ditolak karena periode sudah dikunci.');
        }

        $cleanRupiah = function ($value) {
            return (int) preg_replace('/[^0-9]/', '', $value);
        };

        // 1. Ambil & bersihkan nilai nominal baru dari inputan form
        $gaji_pokok = $cleanRupiah($request->gaji_pokok);
        $tunjangan_transport = $cleanRupiah($request->tunjangan_transport);
        $tunjangan_makan = $cleanRupiah($request->tunjangan_makan);

        $lembur = $cleanRupiah($request->lembur);
        $bonus_target = $cleanRupiah($request->bonus_target);
        $bonus_tanggal_merah = $cleanRupiah($request->bonus_tanggal_merah);
        $bonus_birthday = $cleanRupiah($request->bonus_birthday);
        $bonus_dll = $cleanRupiah($request->bonus_dll);

        $potongan_inventaris = $cleanRupiah($request->potongan_inventaris);
        $potongan_terlambat = $cleanRupiah($request->potongan_terlambat);

        // 2. Hitung ulang total gaji bersih di backend
        $total_tetap = $gaji_pokok + $tunjangan_transport + $tunjangan_makan;
        $total_tidak_tetap = $lembur + $bonus_target + $bonus_tanggal_merah + $bonus_birthday + $bonus_dll;
        $total_potongan = $potongan_inventaris + $potongan_terlambat;

        $total_gaji_bersih = ($total_tetap + $total_tidak_tetap) - $total_potongan;

        // 3. Simpan pembaruan data ke dalam baris tabel penggajian
        $payroll->update([
            'gaji_pokok'            => $gaji_pokok,
            'tunjangan_transport'   => $tunjangan_transport,
            'tunjangan_makan'       => $tunjangan_makan,
            'lembur'                => $lembur,
            'bonus_target'          => $bonus_target,
            'bonus_tanggal_merah'   => $bonus_tanggal_merah,
            'bonus_birthday'        => $bonus_birthday,
            'bonus_dll'             => $bonus_dll,
            'potongan_inventaris'   => $potongan_inventaris,
            'potongan_terlambat'    => $potongan_terlambat,
            'total_gaji_bersih'     => $total_gaji_bersih,
        ]);

        // Kembalikan ke halaman detail kelompok karyawan per periode dengan pesan sukses
        return redirect()->route('penggajian.show-periode', ['periode' => $payroll->periode_bulan_tahun])
            ->with('success', 'Data gaji ' . $payroll->karyawan->nama_karyawan . ' berhasil diperbarui.');
    }

    public function show($id)
    {
        // Ambil data penggajian satu karyawan beserta relasi datanya
        $payroll = Penggajian::with('karyawan')->findOrFail($id);

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
            ($payroll->potongan_terlambat ?? 0);

        // Hitung Take Home Pay (Gaji Bersih Akhir)
        $total_gaji_bersih = ($total_tetap + $total_tidak_tetap) - $total_potongan;

        // Kirim semua variabel perhitungan ke view show
        return view('penggajian.show', compact(
            'payroll',
            'total_tetap',
            'total_tidak_tetap',
            'total_potongan',
            'total_gaji_bersih'
        ));
    }
}
