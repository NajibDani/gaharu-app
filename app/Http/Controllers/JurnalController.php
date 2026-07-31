<?php

namespace App\Http\Controllers;

use App\Models\Journal;
use App\Models\ChartOfAccount;
use App\Models\JournalItem;
use App\Models\JurnalPenyesuaian;
use App\Models\Pembelian;
use App\Models\PenjualanPos;
use App\Models\Pesanan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class JurnalController extends Controller
{
    public function index()
    {
        // Menyaring header jurnal yang memiliki item bertipe 'jurnal_umum'
        // dan melakukan eager loading hanya pada item yang tipenya sesuai
        $jurnals = Journal::whereHas('details', function ($query) {
            $query->where('journal_type', 'jurnal_umum');
        })
            ->with(['details' => function ($query) {
                $query->where('journal_type', 'jurnal_umum')->with('coa');
            }])
            ->orderBy('tanggal', 'desc')
            ->get();

        return view('jurnal.index', compact('jurnals'));
    }

    public function create()
    {
        $coas = ChartOfAccount::all();
        $latestClosing = Journal::where('source_type', 'closing')->orderBy('tanggal', 'desc')->first();
        $latestClosingDate = $latestClosing ? $latestClosing->tanggal : null;
        return view('jurnal.create', compact('coas', 'latestClosingDate'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'tanggal' => 'required|date',
            'deskripsi' => 'required|string',
            'details' => 'required|array|min:2',
            'details.*.account_id' => 'required|exists:chart_of_accounts,id',
            'details.*.debit' => 'required|numeric|min:0',
            'details.*.kredit' => 'required|numeric|min:0',
        ]);

        $tanggalJurnal = $request->tanggal;
        $alertMessage = null;

        $latestClosing = Journal::where('source_type', 'closing')
            ->orderBy('tanggal', 'desc')
            ->first();

        if ($latestClosing) {
            $closingDate = \Carbon\Carbon::parse($latestClosing->tanggal)->endOfMonth();
            $targetDate = \Carbon\Carbon::parse($request->tanggal);
            
            if ($targetDate->lte($closingDate)) {
                // Periode ini atau bulan sebelumnya sudah ditutup.
                // Geser ke 1 bulan setelah closing terbaru.
                $tanggalJurnal = $closingDate->addMonth()->startOfMonth()->toDateString();
                $alertMessage = "Karena periode akuntansi s/d " . \Carbon\Carbon::parse($latestClosing->tanggal)->translatedFormat('F Y') . " sudah ditutup, jurnal ini otomatis dicatat pada awal periode berjalan selanjutnya tanggal " . \Carbon\Carbon::parse($tanggalJurnal)->translatedFormat('d M Y') . ".";
            }
        }

        try {
            DB::beginTransaction();

            // Simpan Header dengan source_type menjadi 'jurnal_umum'
            $jurnal = Journal::create([
                'tanggal' => $tanggalJurnal,
                'deskripsi' => $request->deskripsi,
                'no_ref' => $request->no_ref ?? 'JR-' . time(),
                'source_type' => 'jurnal_umum',
                'source_id' => 0,
                'created_by' => Auth::id(),
            ]);

            $totalDebit = 0;
            $totalKredit = 0;

            // Simpan Detail dengan menyertakan journal_type
            foreach ($request->details as $item) {
                $jurnal->details()->create([
                    'account_id'   => $item['account_id'],
                    'journal_type' => 'jurnal_umum',
                    'debit'        => $item['debit'],
                    'kredit'       => $item['kredit'],
                ]);

                $totalDebit += $item['debit'];
                $totalKredit += $item['kredit'];
            }

            // Validasi Balance
            if (number_format($totalDebit, 2, '.', '') !== number_format($totalKredit, 2, '.', '')) {
                throw new \Exception("Total Debit dan Kredit tidak seimbang!");
            }

            DB::commit();
            
            $successMsg = 'Jurnal berhasil disimpan!';
            if ($alertMessage) {
                $successMsg .= ' ' . $alertMessage;
            }
            return redirect()->route('jurnal.index')->with('success', $successMsg);
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withErrors(['error' => $e->getMessage()])->withInput();
        }
    }

    public function approveBatch(Request $request)
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        // Memastikan hanya menyetujui jurnal draft yang itemnya bertipe 'jurnal_umum'
        $updatedCount = Journal::where('status', 'draft')
            ->whereBetween('tanggal', [$request->start_date, $request->end_date])
            ->whereHas('details', function ($query) {
                $query->where('journal_type', 'jurnal_umum');
            })
            ->update(['status' => 'approved']);

        if ($updatedCount > 0) {
            return redirect()->back()->with('success', "Berhasil memposting {$updatedCount} jurnal ke Buku Besar!");
        }

        return redirect()->back()->with('error', 'Tidak ada jurnal berstatus draft yang ditemukan pada rentang tanggal tersebut.');
    }

    public function show($id)
    {
        $jurnal = Journal::with(['details' => function ($query) {
            $query->where('journal_type', 'jurnal_umum')->with('coa');
        }])
            ->whereHas('details', function ($query) {
                $query->where('journal_type', 'jurnal_umum');
            })
            ->findOrFail($id);

        // DIPERBAIKI: dari 'jurnal umum' menjadi 'jurnal_umum'
        $items = JournalItem::where('journal_id', $id)
            ->where('journal_type', 'jurnal_umum')
            ->get();

        return view('jurnal.show', compact('jurnal', 'items'));
    }

    public function edit($id)
    {
        // Mengambil data jurnal beserta relasi detail bertipe 'jurnal_umum'
        $jurnal = Journal::with(['details' => function ($query) {
            $query->where('journal_type', 'jurnal_umum')->with('coa');
        }])
            ->whereHas('details', function ($query) {
                $query->where('journal_type', 'jurnal_umum');
            })
            ->findOrFail($id);

        // Mengambil daftar akun/COA untuk pilihan dropdown
        $coas = ChartOfAccount::all();

        // Mengambil tanggal closing terbaru untuk proteksi periode
        $latestClosing = Journal::where('source_type', 'closing')->orderBy('tanggal', 'desc')->first();
        $latestClosingDate = $latestClosing ? $latestClosing->tanggal : null;

        return view('jurnal.edit', compact('jurnal', 'coas', 'latestClosingDate'));
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'tanggal' => 'required|date',
            'deskripsi' => 'required|string',
            'details' => 'required|array|min:2',
            'details.*.account_id' => 'required|exists:chart_of_accounts,id',
            'details.*.debit' => 'required|numeric|min:0',
            'details.*.kredit' => 'required|numeric|min:0',
        ]);

        $jurnal = Journal::findOrFail($id);

        $tanggalJurnal = $request->tanggal;
        $alertMessage = null;

        // Pengecekan status closing akuntansi
        $latestClosing = Journal::where('source_type', 'closing')
            ->orderBy('tanggal', 'desc')
            ->first();

        if ($latestClosing) {
            $closingDate = \Carbon\Carbon::parse($latestClosing->tanggal)->endOfMonth();
            $targetDate = \Carbon\Carbon::parse($request->tanggal);

            if ($targetDate->lte($closingDate)) {
                $tanggalJurnal = $closingDate->addMonth()->startOfMonth()->toDateString();
                $alertMessage = "Karena periode akuntansi s/d " . \Carbon\Carbon::parse($latestClosing->tanggal)->translatedFormat('F Y') . " sudah ditutup, perubahan jurnal otomatis dicatat pada awal periode berjalan selanjutnya tanggal " . \Carbon\Carbon::parse($tanggalJurnal)->translatedFormat('d M Y') . ".";
            }
        }

        try {
            DB::beginTransaction();

            // Update data header jurnal
            $jurnal->update([
                'tanggal' => $tanggalJurnal,
                'deskripsi' => $request->deskripsi,
            ]);

            // Hapus detail jurnal lama bertipe 'jurnal_umum' untuk memperbarui dengan detail baru
            $jurnal->details()->where('journal_type', 'jurnal_umum')->delete();

            $totalDebit = 0;
            $totalKredit = 0;

            // Simpan ulang detail jurnal
            foreach ($request->details as $item) {
                $jurnal->details()->create([
                    'account_id'   => $item['account_id'],
                    'journal_type' => 'jurnal_umum',
                    'debit'        => $item['debit'],
                    'kredit'       => $item['kredit'],
                ]);

                $totalDebit += $item['debit'];
                $totalKredit += $item['kredit'];
            }

            // Validasi Keseimbangan Debit & Kredit
            if (number_format($totalDebit, 2, '.', '') !== number_format($totalKredit, 2, '.', '')) {
                throw new \Exception("Total Debit dan Kredit tidak seimbang!");
            }

            DB::commit();

            $successMsg = 'Jurnal berhasil diperbarui!';
            if ($alertMessage) {
                $successMsg .= ' ' . $alertMessage;
            }

            return redirect()->route('jurnal.index')->with('success', $successMsg);
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withErrors(['error' => $e->getMessage()])->withInput();
        }
    }

    public function closingPage()
    {
        // Menampilkan halaman form penutupan dan daftar riwayat khusus closing
        $closings = Journal::where('source_type', 'closing')
            ->orderBy('tanggal', 'desc')
            ->get();

        return view('closing.index', compact('closings'));
    }

    public function closePeriod(Request $request)
    {
        $request->validate([
            'bulan' => 'required|integer|between:1,12',
            'tahun' => 'required|integer',
        ]);

        $bulan = sprintf('%02d', $request->bulan);
        $tahun = $request->tahun;

        $startOfMonth = "$tahun-$bulan-01";
        $endOfMonth = date('Y-m-t', strtotime($startOfMonth));

        try {
            DB::beginTransaction();

            // 1. Validasi apakah periode ini sudah pernah diclosing sebelumnya
            $alreadyClosed = Journal::where('source_type', 'closing')
                ->whereMonth('tanggal', $bulan)
                ->whereYear('tanggal', $tahun)
                ->exists();

            if ($alreadyClosed) {
                return back()->withErrors(['error' => 'Periode akuntansi ini sudah ditutup sebelumnya!']);
            }

            // 2. Ambil Akun Laba Ditahan (Muara Akhir Ekuitas)
            $labaDitahan = ChartOfAccount::where('kode', '3103')->firstOrFail();
            $tanggalClosing = $endOfMonth;

            // 3. Buat Header Jurnal Penutup di tabel 'journals'
            $journal = Journal::create([
                'tanggal'     => $tanggalClosing,
                'deskripsi'   => "Jurnal Penutup Periode " . date('F', mktime(0, 0, 0, (int)$bulan, 1)) . " $tahun",
                'no_ref'      => 'CLS-' . time(),
                'source_type' => 'closing', 
                'source_id'   => 0,
                'status'      => 'approved', 
                'created_by'  => Auth::id(),
            ]);

            // --- PERBAIKAN MAPPING TABEL (MANUAL VS OTOMATIS) ---
            $tabelManual = [
                'jurnal_umum'                        => 'journals',
                \App\Models\JurnalPenyesuaian::class => 'jurnal_penyesuaian',
                'jurnal_penyesuaian'                 => 'jurnal_penyesuaian',
            ];

            $tabelOtomatis = [
                'jurnal_penjualan_pos' => 'jurnal_penjualan_pos', 
                'jurnal_penjualan_b2b' => 'jurnal_penjualan_b2b', 
                'penjualan_b2b'        => 'jurnal_penjualan_b2b',
                'jurnal_pembelian'     => 'jurnal_pembelian',     
            ];

            // 4. Ambil data mutasi berjalan secara bulk menggunakan query agregasi polimorfik
            $mutasiBalances = \App\Models\JournalItem::where('journal_type', '!=', 'opening')
                ->where('journal_type', '!=', 'closing') 
                ->where(function ($q) use ($startOfMonth, $endOfMonth, $tabelManual, $tabelOtomatis) {
                    
                    // Jalur Jurnal Manual (Harus Approved)
                    foreach ($tabelManual as $type => $tableName) {
                        $q->orWhere(function ($queryManual) use ($type, $tableName, $startOfMonth, $endOfMonth) {
                            $queryManual->where('journal_type', $type)
                                ->whereExists(function ($sub) use ($tableName, $startOfMonth, $endOfMonth) {
                                    $sub->select(DB::raw(1))
                                        ->from($tableName)
                                        ->whereColumn("$tableName.id", 'journal_items.journal_id')
                                        ->whereBetween('tanggal', [$startOfMonth, $endOfMonth])
                                        ->where('status', 'approved');
                                });
                        });
                    }

                    // Jalur Jurnal Otomatis (Langsung Dihitung)
                    foreach ($tabelOtomatis as $type => $tableName) {
                        $q->orWhere(function ($queryOtomatis) use ($type, $tableName, $startOfMonth, $endOfMonth) {
                            $queryOtomatis->where('journal_type', $type)
                                ->whereExists(function ($sub) use ($tableName, $startOfMonth, $endOfMonth) {
                                    $sub->select(DB::raw(1))
                                        ->from($tableName)
                                        ->whereColumn("$tableName.id", 'journal_items.journal_id')
                                        ->whereBetween('tanggal', [$startOfMonth, $endOfMonth]);
                                });
                        });
                    }
                })
                ->select('account_id')
                ->selectRaw('SUM(debit) as total_debit, SUM(kredit) as total_kredit')
                ->groupBy('account_id')
                ->get()
                ->keyBy('account_id');

            $totalPendapatan = 0;
            $totalHppDanBeban = 0;
            $itemsToInsert = [];

            // Mengambil semua akun COA Pendapatan (4), HPP (5), dan Beban (6)
            $coas = ChartOfAccount::where('kode', 'like', '4%')
                ->orWhere('kode', 'like', '5%')
                ->orWhere('kode', 'like', '6%')
                ->get();

            foreach ($coas as $coa) {
                $raw = $mutasiBalances->get($coa->id);
                if (!$raw) continue;

                $debit = (float) $raw->total_debit;
                $kredit = (float) $raw->total_kredit;

                if (str_starts_with($coa->kode, '4')) {
                    // Pendapatan (Saldo Normal: Kredit) -> Saldo Penutupan didebit sebesar net kredit
                    $neto = $kredit - $debit;
                    if ($neto != 0) {
                        $itemsToInsert[] = [
                            'journal_id'   => $journal->id,
                            'account_id'   => $coa->id,
                            'journal_type' => 'closing',
                            'debit'        => $neto > 0 ? $neto : 0,
                            'kredit'       => $neto < 0 ? abs($neto) : 0,
                        ];
                        $totalPendapatan += $neto;
                    }
                } else {
                    // HPP & Beban (Saldo Normal: Debit) -> Saldo Penutupan dikredit sebesar net debit
                    $neto = $debit - $kredit;
                    if ($neto != 0) {
                        $itemsToInsert[] = [
                            'journal_id'   => $journal->id,
                            'account_id'   => $coa->id,
                            'journal_type' => 'closing',
                            'debit'        => $neto < 0 ? abs($neto) : 0,
                            'kredit'       => $neto > 0 ? $neto : 0,
                        ];
                        $totalHppDanBeban += $neto;
                    }
                }
            }

            // 5. Hitung Laba/Rugi Bersih & Tembak ke Laba Ditahan
            $labaBersih = $totalPendapatan - $totalHppDanBeban;

            if ($labaBersih != 0) {
                $itemsToInsert[] = [
                    'journal_id'   => $journal->id,
                    'account_id'   => $labaDitahan->id,
                    'journal_type' => 'closing',
                    'debit'        => $labaBersih < 0 ? abs($labaBersih) : 0,
                    'kredit'       => $labaBersih > 0 ? $labaBersih : 0,
                ];
            }

            // Bulk insert semua detail baris jurnal penutupan ke 'journal_items'
            if (!empty($itemsToInsert)) {
                DB::table('journal_items')->insert($itemsToInsert);
            }

            DB::commit();
            return redirect()->route('closing.index')->with('success', 'Periode berhasil ditutup!');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withErrors(['error' => 'Gagal menutup periode: ' . $e->getMessage()]);
        }
    }
    // Menampilkan Daftar Jurnal Penyesuaian
    public function adjustmentIndex()
    {
        // Mengambil data dari tabel baru (jurnal_penyesuaian) beserta detail akunnya
        $adjustments = JurnalPenyesuaian::with('details.coa')
            ->orderBy('tanggal', 'desc')
            ->get();

        // Mengarah ke resources/views/adjustment/index.blade.php
        return view('adjustment.index', compact('adjustments'));
    }

    /**
     * Menampilkan Form Create Penyesuaian
     */
    public function adjustmentPage()
    {
        $coas = ChartOfAccount::orderBy('kode', 'asc')->get();
        $latestClosing = Journal::where('source_type', 'closing')->orderBy('tanggal', 'desc')->first();
        $latestClosingDate = $latestClosing ? $latestClosing->tanggal : null;

        // Mengarah ke resources/views/adjustment/create.blade.php
        return view('adjustment.create', compact('coas', 'latestClosingDate'));
    }

    /**
     * Menyimpan data Jurnal Penyesuaian
     */
    public function adjustmentStore(Request $request)
    {
        $request->validate([
            'tanggal' => 'required|date',
            'no_ref' => 'required|string|max:100',
            'deskripsi' => 'required|string|max:255',
            'details' => 'required|array|min:2',
            'details.*.account_id' => 'required|exists:chart_of_accounts,id',
            'details.*.debit' => 'required|numeric|min:0',
            'details.*.kredit' => 'required|numeric|min:0',
            // Tambahkan validasi action untuk menangkap tombol draft/post
            'action' => 'required|in:draft,post',
        ]);

        $tanggalJurnal = $request->tanggal;
        $alertMessage = null;

        $latestClosing = Journal::where('source_type', 'closing')
            ->orderBy('tanggal', 'desc')
            ->first();

        if ($latestClosing) {
            $closingDate = \Carbon\Carbon::parse($latestClosing->tanggal)->endOfMonth();
            $targetDate = \Carbon\Carbon::parse($request->tanggal);
            
            if ($targetDate->lte($closingDate)) {
                // Periode ini atau bulan sebelumnya sudah ditutup.
                // Geser ke 1 bulan setelah closing terbaru.
                $tanggalJurnal = $closingDate->addMonth()->startOfMonth()->toDateString();
                $alertMessage = "Karena periode akuntansi s/d " . \Carbon\Carbon::parse($latestClosing->tanggal)->translatedFormat('F Y') . " sudah ditutup, jurnal penyesuaian ini otomatis dicatat pada awal periode berjalan selanjutnya tanggal " . \Carbon\Carbon::parse($tanggalJurnal)->translatedFormat('d M Y') . ".";
            }
        }

        try {
            DB::beginTransaction();

            // 1. Simpan Header ke tabel: jurnal_penyesuaian dengan penambahan kolom status
            $jurnal = JurnalPenyesuaian::create([
                'tanggal'     => $tanggalJurnal,
                'deskripsi'   => "[AJP] " . $request->deskripsi,
                'no_ref'      => $request->no_ref,
                'source_type' => 'adjustment',
                'source_id'   => 0,
                'created_by'  => Auth::id(),
                // Skema Hybrid: tentukan status berdasarkan tombol yang diklik
                'status'      => $request->action === 'post' ? 'approved' : 'draft',
            ]);

            // 2. Simpan Detail ke tabel journal_items dengan menyertakan nama class model pada journal_type
            foreach ($request->details as $item) {
                // Skip jika debit dan kredit sama-sama nol
                if ($item['debit'] == 0 && $item['kredit'] == 0) continue;

                $jurnal->details()->create([
                    'account_id'   => $item['account_id'],
                    'debit'        => $item['debit'],
                    'kredit'       => $item['kredit'],
                    // Diseragamkan menggunakan namespace class model JurnalPenyesuaian
                    'journal_type' => \App\Models\JurnalPenyesuaian::class,
                ]);
            }

            // Refresh model untuk mendapatkan details yang baru disimpan
            $jurnal->load('details');

            // 3. Validasi Balance
            if (round($jurnal->details->sum('debit'), 2) != round($jurnal->details->sum('kredit'), 2)) {
                throw new \Exception("Total Debit (" . $jurnal->details->sum('debit') . ") dan Kredit (" . $jurnal->details->sum('kredit') . ") tidak seimbang!");
            }

            DB::commit();

            $pesan = $jurnal->status === 'approved'
                ? 'Jurnal Penyesuaian berhasil disimpan dan diposting ke Buku Besar!'
                : 'Jurnal Penyesuaian berhasil disimpan sebagai Draft!';

            if ($alertMessage) {
                $pesan .= ' ' . $alertMessage;
            }

            return redirect()->route('adjustment.index')->with('success', $pesan);
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', $e->getMessage())->withInput();
        }
    }

    public function adjustmentApprove($id)
    {
        $jurnal = JurnalPenyesuaian::findOrFail($id);
        $jurnal->update(['status' => 'approved']);

        return redirect()->back()->with('success', 'Jurnal berhasil diposting ke Buku Besar!');
    }

    public function adjustmentApproveBatch(Request $request)
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        $updatedCount = JurnalPenyesuaian::where('status', 'draft')
            ->whereBetween('tanggal', [$request->start_date, $request->end_date])
            ->update(['status' => 'approved']);

        if ($updatedCount > 0) {
            return redirect()->back()->with('success', "Berhasil memposting {$updatedCount} jurnal penyesuaian ke Buku Besar!");
        }

        return redirect()->back()->with('error', 'Tidak ada jurnal penyesuaian berstatus draft yang ditemukan pada rentang tanggal tersebut.');
    }

    public function adjustmentShow($id)
    {
        // Ambil data jurnal beserta relasi details.coa tanpa membatasi journal_type
        $jurnal = JurnalPenyesuaian::with('details.coa')->findOrFail($id);

        return view('adjustment.show', compact('jurnal'));
    }

    /**
     * Menampilkan Form Edit Jurnal Penyesuaian
     */
    public function adjustmentEdit($id)
    {
        // Ambil data jurnal beserta relasi details.coa
        $jurnal = JurnalPenyesuaian::with('details.coa')->findOrFail($id);

        $coas = ChartOfAccount::orderBy('kode', 'asc')->get();

        $latestClosing = Journal::where('source_type', 'closing')->orderBy('tanggal', 'desc')->first();
        $latestClosingDate = $latestClosing ? $latestClosing->tanggal : null;

        return view('adjustment.edit', compact('jurnal', 'coas', 'latestClosingDate'));
    }

    /**
     * Memperbarui Data Jurnal Penyesuaian
     */
    public function adjustmentUpdate(Request $request, $id)
    {
        $request->validate([
            'tanggal' => 'required|date',
            'deskripsi' => 'required|string|max:255',
            'details' => 'required|array|min:2',
            'details.*.account_id' => 'required|exists:chart_of_accounts,id',
            'details.*.debit' => 'required|numeric|min:0',
            'details.*.kredit' => 'required|numeric|min:0',
            'action' => 'required|in:draft,post',
        ]);

        $jurnal = JurnalPenyesuaian::findOrFail($id);

        $tanggalJurnal = $request->tanggal;
        $alertMessage = null;

        // Pengecekan status closing akuntansi
        $latestClosing = Journal::where('source_type', 'closing')
            ->orderBy('tanggal', 'desc')
            ->first();

        if ($latestClosing) {
            $closingDate = \Carbon\Carbon::parse($latestClosing->tanggal)->endOfMonth();
            $targetDate = \Carbon\Carbon::parse($request->tanggal);

            if ($targetDate->lte($closingDate)) {
                $tanggalJurnal = $closingDate->addMonth()->startOfMonth()->toDateString();
                $alertMessage = "Karena periode akuntansi s/d " . \Carbon\Carbon::parse($latestClosing->tanggal)->translatedFormat('F Y') . " sudah ditutup, jurnal penyesuaian otomatis dicatat pada awal periode berjalan selanjutnya tanggal " . \Carbon\Carbon::parse($tanggalJurnal)->translatedFormat('d M Y') . ".";
            }
        }

        try {
            DB::beginTransaction();

            // Sihkan awalan [AJP] jika user mengetiknya ganda
            $deskripsi = Str::startsWith($request->deskripsi, '[AJP]') 
                ? $request->deskripsi 
                : "[AJP] " . $request->deskripsi;

            // 1. Update Header Jurnal Penyesuaian
            $jurnal->update([
                'tanggal'   => $tanggalJurnal,
                'deskripsi' => $deskripsi,
                'status'    => $request->action === 'post' ? 'approved' : 'draft',
            ]);

            // 2. Hapus detail lama yang bertipe JurnalPenyesuaian
            $jurnal->details()->where('journal_type', \App\Models\JurnalPenyesuaian::class)->delete();

            // 3. Simpan detail baru
            foreach ($request->details as $item) {
                if ($item['debit'] == 0 && $item['kredit'] == 0) continue;

                $jurnal->details()->create([
                    'account_id'   => $item['account_id'],
                    'debit'        => $item['debit'],
                    'kredit'       => $item['kredit'],
                    'journal_type' => \App\Models\JurnalPenyesuaian::class,
                ]);
            }

            $jurnal->load('details');

            // 4. Validasi Balance
            if (round($jurnal->details->sum('debit'), 2) != round($jurnal->details->sum('kredit'), 2)) {
                throw new \Exception("Total Debit (" . $jurnal->details->sum('debit') . ") dan Kredit (" . $jurnal->details->sum('kredit') . ") tidak seimbang!");
            }

            DB::commit();

            $pesan = $jurnal->status === 'approved'
                ? 'Jurnal Penyesuaian berhasil diperbarui dan diposting ke Buku Besar!'
                : 'Jurnal Penyesuaian berhasil diperbarui sebagai Draft!';

            if ($alertMessage) {
                $pesan .= ' ' . $alertMessage;
            }

            return redirect()->route('adjustment.index')->with('success', $pesan);
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', $e->getMessage())->withInput();
        }
    }

    public function pembelianIndex()
    {
        // 1. Ambil jurnal yang sudah tersimpan
        $jurnalTersimpan = DB::table('jurnal_pembelian')
            ->select('source_type', 'source_id', 'tahap')
            ->get();

        $pembelianJurnalMap = $jurnalTersimpan->where('source_type', 'pembelian')
            ->groupBy('source_id')
            ->map(fn($rows) => $rows->pluck('tahap')->toArray());

        $penerimaanJurnalMap = $jurnalTersimpan->where('source_type', 'penerimaan_pembelian')
            ->pluck('source_id')
            ->toArray();

        $pembeliansBelum = collect();

        // 2. Queue for Pembelian (DP dan Pelunasan)
        $semuaPembelian = \App\Models\Pembelian::with(['supplier', 'gudang'])
            ->orderBy('tanggal', 'desc')
            ->get();

        foreach ($semuaPembelian as $p) {
            $persenDP = floatval($p->persen_dp ?? 0);
            $isLunas  = (bool) $p->is_lunas;
            $sudahAda = $pembelianJurnalMap->get($p->id, []);

            // DP
            if ($persenDP > 0 && !in_array('dp', $sudahAda)) {
                $clone = clone $p;
                $clone->tahap_selanjutnya = 'dp';
                $clone->total_keluar = $this->hitungTotalKeluar($p, 'dp');
                $clone->queue_type = 'pembelian';
                $clone->label_no_invoice = $p->kode_pembelian;
                $pembeliansBelum->push($clone);
            }

            // Pelunasan
            if ($persenDP > 0 && $isLunas && !in_array('pelunasan', $sudahAda)) {
                $clone = clone $p;
                $clone->tahap_selanjutnya = 'pelunasan';
                $clone->total_keluar = $this->hitungTotalKeluar($p, 'pelunasan');
                $clone->queue_type = 'pembelian';
                $clone->label_no_invoice = $p->kode_pembelian;
                $pembeliansBelum->push($clone);
            }
        }

        // 3. Queue for Penerimaan Pembelian (Reklas Persediaan / COD)
        $semuaPenerimaan = \App\Models\PenerimaanPembelian::with(['pembelian.supplier', 'pembelian.gudang', 'details'])
            ->orderBy('tanggal', 'desc')
            ->get();

        foreach ($semuaPenerimaan as $rcv) {
            if (in_array($rcv->id, $penerimaanJurnalMap)) {
                continue; // Sudah dijurnal
            }

            $p = $rcv->pembelian;
            if (!$p) continue;

            $metode = $p->metode_pembayaran;
            $tahapSelanjutnya = ($metode === 'cod') ? 'cod' : 'reklas_lunas';

            // Hitung total penerimaan ini (DPP + PPN 10% jika COD)
            $dppReceipt = 0;
            foreach ($rcv->details as $det) {
                $dppReceipt += floatval($det->qty) * floatval($det->harga_per_qty);
            }
            $totalKeluar = ($tahapSelanjutnya === 'cod') ? ($dppReceipt + round($dppReceipt * 0.10, 2)) : $dppReceipt;

            // Kloning objek pembelian agar viewnya tetap sama, tapi id-nya diganti id penerimaan!
            $clone = clone $p;
            $clone->id = $rcv->id; // GANTI ID KE PENERIMAAN!
            $clone->tahap_selanjutnya = $tahapSelanjutnya;
            $clone->total_keluar = $totalKeluar;
            $clone->queue_type = 'penerimaan_pembelian';
            $clone->label_no_invoice = $p->kode_pembelian . ' (' . $rcv->no_penerimaan . ')';
            $pembeliansBelum->push($clone);
        }

        // 4. Riwayat Jurnal (termasuk yang digabung ke penerimaan_pembelian)
        $jurnalsSudah = DB::table('jurnal_pembelian')
            ->join('journal_items', function ($join) {
                $join->on('journal_items.journal_id', '=', 'jurnal_pembelian.id')
                    ->where('journal_items.journal_type', '=', 'jurnal_pembelian');
            })
            ->select(
                'jurnal_pembelian.id',
                'jurnal_pembelian.tanggal',
                'jurnal_pembelian.no_ref',
                'jurnal_pembelian.deskripsi',
                'jurnal_pembelian.tahap',
                'jurnal_pembelian.source_type',
                'jurnal_pembelian.source_id',
                DB::raw('SUM(journal_items.debit) as total_transaksi')
            )
            ->groupBy(
                'jurnal_pembelian.id',
                'jurnal_pembelian.tanggal',
                'jurnal_pembelian.no_ref',
                'jurnal_pembelian.deskripsi',
                'jurnal_pembelian.tahap',
                'jurnal_pembelian.source_type',
                'jurnal_pembelian.source_id'
            )
            ->orderBy('jurnal_pembelian.tanggal', 'desc')
            ->orderBy('jurnal_pembelian.id', 'desc')
            ->paginate(10);

        return view('jurnal-pembelian.index', compact('pembeliansBelum', 'jurnalsSudah'));
    }

    private function tahapSeharusnyaAda($pembelian): array
    {
        $persenDP   = floatval($pembelian->presen_dp ?? $pembelian->persen_dp ?? 0);
        $isDiterima = (bool) $pembelian->is_diterima;
        $isLunas    = (bool) $pembelian->is_lunas;

        $tahap = [];

        if ($persenDP > 0) {
            $tahap[] = 'dp';

            if ($isLunas) {
                $tahap[] = 'pelunasan';
            }

            // Jurnal persediaan (reklas_lunas / gabungan) hanya boleh muncul jika barang SUDAH DITERIMA
            if ($isDiterima) {
                if ($isLunas) {
                    $tahap[] = 'reklas_lunas';
                } else {
                    $tahap[] = 'gabungan';
                }
            }
        } else {
            // Untuk COD, Jurnal persediaan hanya boleh muncul jika barang SUDAH DITERIMA
            if ($isDiterima) {
                $tahap[] = 'cod';
            }
        }

        return $tahap;
    }

    // PERBAIKAN: Menambahkan parameter kedua $tahapBerikutnya langsung ke fungsi
    private function hitungTotalKeluar($pembelian, $tahapBerikutnya = null): float
    {
        $dppTotal     = floatval($pembelian->total) - floatval($pembelian->tax_service ?? 0);
        $persenDP     = floatval($pembelian->persen_dp ?? 0);
        $dpMurni      = floatval($pembelian->nominal_dp ?? ($dppTotal * ($persenDP / 100)));
        $ppnDP        = 0; // Kasus 1: PPN DP dihilangkan
        $kasDP        = $dpMurni + $ppnDP;

        $sisaDpp      = max(0, $dppTotal - $dpMurni);
        $ppnPelunasan = floatval($pembelian->tax_service ?? 0); // Kasus 2: PPN Pelunasan mengambil tax pembelian
        $kasPelunasan = $sisaDpp + $ppnPelunasan;

        $tahapClean = trim(strtolower((string)$tahapBerikutnya));

        if ($tahapClean === 'dp') {
            return $kasDP;
        }

        if ($tahapClean === 'pelunasan' || $tahapClean === 'gabungan') {
            return $kasPelunasan;
        }

        if ($tahapClean === 'reklas_lunas') {
            // Cek apakah sudah ada jurnal pelunasan
            $hasPelunasanJournal = DB::table('jurnal_pembelian')
                ->where('source_id', $pembelian->id)
                ->where('source_type', 'pembelian')
                ->where('tahap', 'pelunasan')
                ->exists();

            if ($hasPelunasanJournal) {
                return 0; // Reklas murni tidak mengeluarkan kas lagi
            } else {
                return $kasPelunasan; // Gabungan pelunasan + penerimaan barang
            }
        }

        if ($tahapClean === 'cod') {
            return $dppTotal + $ppnPelunasan; // Kasus 4: PPN mengambil tax di pembelian
        }

        return 0;
    }

    public function pembelianCreate(Request $request, $id)
    {
        $tahapReq = trim(strtolower((string)$request->query('tahap')));

        if (in_array($tahapReq, ['reklas_lunas', 'cod'])) {
            $penerimaan = \App\Models\PenerimaanPembelian::with(['pembelian.supplier', 'details.barang'])->findOrFail($id);
            $pembelian = $penerimaan->pembelian;
            $tahap = $tahapReq;
        } else {
            $pembelian = \App\Models\Pembelian::with(['supplier', 'details.barang'])->findOrFail($id);
            $tahap = $tahapReq;
        }

        $coas = \App\Models\ChartOfAccount::orderBy('kode', 'asc')->get();

        // Deteksi apakah pembelian berisi barang operasional / perlengkapan (bukan bahan baku)
        $hasOperational = false;
        foreach ($pembelian->details as $det) {
            if ($det->barang && ($det->barang->is_operational || !$det->barang->is_bahan_baku)) {
                $hasOperational = true;
                break;
            }
        }

        $debitCoaCode = $hasOperational ? '1501' : '1301'; // 1501 = Perlengkapan Operasional dan ATK, 1301 = Persediaan Bahan Baku

        $idKasBank        = DB::table('chart_of_accounts')->where('kode', '1101')->value('id') ?? 15;
        $idPersediaan     = DB::table('chart_of_accounts')->where('kode', $debitCoaCode)->value('id') ?? ($hasOperational ? 27 : 19);
        $idUangMukaPemb   = DB::table('chart_of_accounts')->where('kode', '1202')->value('id') ?? 17;
        $idPPNMasukan     = DB::table('chart_of_accounts')->where('kode', '1203')->value('id') ?? 18;

        $defaultDetails = [];

        if (in_array($tahap, ['reklas_lunas', 'cod'])) {
            // DPP total dihitung dari item penerimaan
            $dppTotal = 0;
            foreach ($penerimaan->details as $det) {
                $dppTotal += floatval($det->qty) * floatval($det->harga_per_qty);
            }

            if ($tahap === 'reklas_lunas') {
                // Cek apakah sudah ada jurnal pelunasan
                $hasPelunasanJournal = DB::table('jurnal_pembelian')
                    ->where('source_id', $pembelian->id)
                    ->where('source_type', 'pembelian')
                    ->where('tahap', 'pelunasan')
                    ->exists();

                if ($hasPelunasanJournal) {
                    // Skema Reklas Murni (Pelunasan terpisah sudah dilakukan)
                    $defaultDetails = [
                        ['account_id' => $idPersediaan, 'debit' => $dppTotal, 'kredit' => 0],
                        ['account_id' => $idUangMukaPemb, 'debit' => 0, 'kredit' => $dppTotal]
                    ];
                } else {
                    // Skema Pelunasan + Penerimaan Barang
                    $pembelianDppTotal = floatval($pembelian->total) - floatval($pembelian->tax_service ?? 0);
                    $dpMurni = floatval($pembelian->nominal_dp ?? ($pembelianDppTotal * (floatval($pembelian->persen_dp ?? 0) / 100)));
                    $sisaDpp = max(0, $pembelianDppTotal - $dpMurni);
                    $ppnTotal = floatval($pembelian->tax_service ?? 0);
                    $totalKasPelunasan = $sisaDpp + $ppnTotal;

                    $defaultDetails = [
                        ['account_id' => $idPersediaan, 'debit' => $dppTotal, 'kredit' => 0],
                        ['account_id' => $idPPNMasukan, 'debit' => $ppnTotal, 'kredit' => 0],
                        ['account_id' => $idUangMukaPemb, 'debit' => 0, 'kredit' => $dpMurni],
                        ['account_id' => $idKasBank, 'debit' => 0, 'kredit' => $totalKasPelunasan]
                    ];
                }
            } else {
                // cod
                $ppnTotal = floatval($pembelian->tax_service ?? 0); // Kasus 4: PPN mengambil tax di pembelian
                $totalContracts = $dppTotal + $ppnTotal;

                $defaultDetails = [
                    ['account_id' => $idPersediaan, 'debit' => $dppTotal, 'kredit' => 0],
                    ['account_id' => $idPPNMasukan, 'debit' => $ppnTotal, 'kredit' => 0],
                    ['account_id' => $idKasBank, 'debit' => 0, 'kredit' => $totalContracts]
                ];
            }
        } else {
            $dppTotal = floatval($pembelian->total) - floatval($pembelian->tax_service ?? 0);
            $persenDP   = floatval($pembelian->persen_dp ?? 0);
            $dpMurni    = floatval($pembelian->nominal_dp ?? ($dppTotal * ($persenDP / 100)));
            $ppnDP      = 0; // Kasus 1: PPN DP dihilangkan
            $kasDP      = $dpMurni + $ppnDP;

            $sisaDpp      = max(0, $dppTotal - $dpMurni);
            $ppnPelunasan = floatval($pembelian->tax_service ?? 0); // Kasus 2: PPN mengambil tax di pembelian
            $kasPelunasan = $sisaDpp + $ppnPelunasan;

            if ($tahap === 'dp') {
                $defaultDetails = [
                    ['account_id' => $idUangMukaPemb, 'debit' => $dpMurni, 'kredit' => 0],
                    ['account_id' => $idKasBank, 'debit' => 0, 'kredit' => $kasDP]
                ];
            } else {
                // pelunasan
                $defaultDetails = [
                    ['account_id' => $idUangMukaPemb, 'debit' => $sisaDpp, 'kredit' => 0],
                    ['account_id' => $idPPNMasukan, 'debit' => $ppnPelunasan, 'kredit' => 0],
                    ['account_id' => $idKasBank, 'debit' => 0, 'kredit' => $kasPelunasan]
                ];
            }
        }

        // Simpan referensi no_penerimaan atau no_pembelian ke deskripsi/ref
        $labelRef = in_array($tahap, ['reklas_lunas', 'cod']) ? $penerimaan->no_penerimaan : $pembelian->kode_pembelian;
        // $descJurnal = 'Pembukuan jurnal khusus pembelian (' . strtoupper($tahap) . ') atas No. Invoice: ' . $pembelian->kode_pembelian;
        $descJurnal = $pembelian->kode_pembelian;
        if (in_array($tahap, ['reklas_lunas', 'cod'])) {
            $descJurnal .= ' [No. Penerimaan: ' . $penerimaan->no_penerimaan . ']';
        }
        $descJurnal .= ' [Supplier: ' . ($pembelian->supplier->nama ?? '-') . ']';

        return view('jurnal-pembelian.create', compact('pembelian', 'coas', 'defaultDetails', 'tahap', 'labelRef', 'descJurnal', 'id'));
    }

    public function prosesJurnalPembelian(Request $request, $id)
    {
        // 1. Validasi data form
        $request->validate([
            'tanggal'              => 'required|date',
            'deskripsi'            => 'required|string',
            'no_ref'               => 'nullable|string',
            'tahap'                => 'required|in:dp,pelunasan,reklas_lunas,gabungan,cod',
            'details'              => 'required|array|min:2',
            'details.*.account_id' => 'required|exists:chart_of_accounts,id',
            'details.*.debit'      => 'required|numeric|min:0',
            'details.*.kredit'     => 'required|numeric|min:0',
        ]);

        $tahap = $request->input('tahap');
        if (in_array($tahap, ['reklas_lunas', 'cod'])) {
            $penerimaan = \App\Models\PenerimaanPembelian::findOrFail($id);
            $pembelian = $penerimaan->pembelian;
            $sourceType = 'penerimaan_pembelian';
            $sourceId = $penerimaan->id;
        } else {
            $pembelian = \App\Models\Pembelian::findOrFail($id);
            $sourceType = 'pembelian';
            $sourceId = $pembelian->id;
        }

        // 1a. Validasi penutupan periode: Blokir pencatatan jurnal pada periode yang sudah ditutup
        if (\App\Models\Journal::isPeriodClosed($request->tanggal)) {
            return back()->with('error', 'Periode akuntansi tanggal ' . date('d/m/Y', strtotime($request->tanggal)) . ' sudah ditutup buku. Tidak dapat menambah jurnal pada periode yang sudah ditutup.')->withInput();
        }

        // 1b. Validasi backend: Blokir pengakuan Persediaan (13xx) jika barang belum diterima
        if ($sourceType === 'pembelian' && !$pembelian->is_diterima && in_array($tahap, ['reklas_lunas', 'cod'])) {
            $persediaanCoaIds = \App\Models\ChartOfAccount::where('parent_id', 18)->orWhere('parent_id', 19)->orWhere('kode', 'like', '13%')->pluck('id')->toArray();
            foreach ($request->details as $item) {
                if (in_array($item['account_id'], $persediaanCoaIds) && floatval($item['debit'] ?? 0) > 0) {
                    return back()
                        ->with('error', "Nominal Persediaan (13xx) tidak dapat dicatat karena barang pada Pembelian '{$pembelian->kode_pembelian}' belum diterima. Silakan lakukan penerimaan barang terlebih dahulu di Menu Pembelian.")
                        ->withInput();
                }
            }
        }

        try {
            DB::beginTransaction();

            // Validasi Keseimbangan Saldo (Balance Check)
            $totalDebit = 0;
            $totalKredit = 0;
            foreach ($request->details as $item) {
                $totalDebit += floatval($item['debit'] ?? 0);
                $totalKredit += floatval($item['kredit'] ?? 0);
            }

            if (round($totalDebit, 2) != round($totalKredit, 2)) {
                throw new \Exception("Total Debit (Rp " . number_format($totalDebit) . ") dan Kredit (Rp " . number_format($totalKredit) . ") tidak seimbang!");
            }

            // 2. Simpan Header LANGSUNG ke tabel jurnal_pembelian dan ambil ID-nya
            $jurnalPembelianId = DB::table('jurnal_pembelian')->insertGetId([
                'tanggal'     => $request->tanggal,
                'deskripsi'   => $request->deskripsi,
                'no_ref'      => $request->no_ref ?? 'JR-PEMB-' . time(),
                'source_type' => $sourceType,
                'source_id'   => $sourceId, // Mengunci ID pembelian / penerimaan agar hilang dari antrean atas
                'tahap'       => $tahap, // Tahap jurnal: dp, pelunasan, reklas, gabungan, atau cod
                'created_by'  => Auth::id() ?? 1,
            ]);

            // 3. Simpan baris detail debit/kredit ke tabel journal_items
            foreach ($request->details as $item) {
                if (floatval($item['debit']) == 0 && floatval($item['kredit']) == 0) {
                    continue;
                }

                DB::table('journal_items')->insert([
                    'journal_id'   => $jurnalPembelianId,
                    'journal_type' => 'jurnal_pembelian',
                    'account_id'   => $item['account_id'],
                    'debit'        => $item['debit'],
                    'kredit'       => $item['kredit'],
                ]);
            }

            DB::commit();
            return redirect()
                ->route('jurnal-pembelian.index')
                ->with('success', 'Jurnal pembelian (' . strtoupper($tahap) . ') berhasil disimpan!');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Terjadi kesalahan: ' . $e->getMessage())->withInput();
        }
    }

    public function pembelianShow($id)
    {
        $jurnal = DB::table('jurnal_pembelian')->where('id', $id)->first();

        if (!$jurnal) {
            return redirect()->route('jurnal-pembelian.index')->with('error', 'Data riwayat tidak ditemukan.');
        }

        $details = DB::table('journal_items')
            ->leftJoin('chart_of_accounts', 'journal_items.account_id', '=', 'chart_of_accounts.id')
            ->where('journal_items.journal_id', $id)
            ->where('journal_items.journal_type', 'jurnal_pembelian')
            ->select('journal_items.debit', 'journal_items.kredit', 'chart_of_accounts.kode', 'chart_of_accounts.nama')
            ->orderBy('journal_items.debit', 'desc')
            ->get();

        $totalDebit = $details->sum('debit');
        $totalKredit = $details->sum('kredit');

        return view('jurnal-pembelian.show', compact('jurnal', 'details', 'totalDebit', 'totalKredit'));
    }

    public function penjualanposIndex()
    {
        $user = auth()->user();

        // 1. Ambil semua ID transaksi POS yang sudah pernah dijurnal
        $sudahDijurnal = DB::table('jurnal_penjualan_pos')
            ->where('source_type', 'penjualan_pos')
            ->pluck('source_id')
            ->toArray();

        // 2. Antrean Atas: Tarik data transaksi POS harian yang BELUM dijurnal dan sudah disetujui (status = 'SUKSES')
        $queryBelum = DB::table('penjualan_pos')
            ->leftJoin('master_gudang', 'penjualan_pos.gudang_id', '=', 'master_gudang.id')
            ->whereNotIn('penjualan_pos.id', $sudahDijurnal)
            ->where('penjualan_pos.status', 'SUKSES');

        if ($user && $user->gudang_id) {
            $queryBelum->where('penjualan_pos.gudang_id', $user->gudang_id);
        }

        $penjualanPosBelum = $queryBelum->select(
                'penjualan_pos.id',
                'penjualan_pos.tanggal',
                'penjualan_pos.kode_transaksi',
                'penjualan_pos.total',
                DB::raw("COALESCE(master_gudang.nama, 'Gudang') as nama_outlet")
            )
            ->orderBy('penjualan_pos.tanggal', 'desc')
            ->get();

        // 3. Riwayat Bawah: Ringkas menjadi satu baris per dokumen (Group By No. Ref)
        $querySudah = DB::table('jurnal_penjualan_pos')
            ->join('journal_items', function ($join) {
                $join->on('journal_items.journal_id', '=', 'jurnal_penjualan_pos.id')
                    ->where('journal_items.journal_type', '=', 'jurnal_penjualan_pos');
            })
            ->leftJoin('penjualan_pos', 'jurnal_penjualan_pos.source_id', '=', 'penjualan_pos.id')
            ->where('jurnal_penjualan_pos.source_type', 'penjualan_pos');

        if ($user && $user->gudang_id) {
            $querySudah->where('penjualan_pos.gudang_id', $user->gudang_id);
        }

        $jurnalsSudah = $querySudah->select(
                'jurnal_penjualan_pos.id',
                'jurnal_penjualan_pos.tanggal',
                'jurnal_penjualan_pos.no_ref',
                'jurnal_penjualan_pos.deskripsi',
                DB::raw('SUM(journal_items.debit) as total_debit'),
                DB::raw('SUM(journal_items.kredit) as total_kredit')
            )
            ->groupBy(
                'jurnal_penjualan_pos.id',
                'jurnal_penjualan_pos.tanggal',
                'jurnal_penjualan_pos.no_ref',
                'jurnal_penjualan_pos.deskripsi'
            )
            ->orderBy('jurnal_penjualan_pos.tanggal', 'desc')
            ->orderBy('jurnal_penjualan_pos.id', 'desc')
            ->paginate(10);

        return view('jurnal-penjualanpos.index', compact('penjualanPosBelum', 'jurnalsSudah'));
    }

    public function penjualanposCreate(Request $request, $id) // <-- Tambahkan Request $request di sini 
    {
        // Bersihkan sisa data old input dari menu/modul lain agar tidak merusak draf POS
        $request->session()->forget('_old_input');

        // 1. Ambil data induk penjualan POS berdasarkan ID antrean
        $penjualan = DB::table('penjualan_pos')->where('id', $id)->first();

        if (!$penjualan) {
            return back()->with('error', 'Data induk penjualan POS tidak ditemukan.');
        }

        // --- VALIDASI ROLE / OUTLET AKSES ---
        $user = auth()->user();
        if ($user && $user->gudang_id && $penjualan->gudang_id != $user->gudang_id) {
            abort(403, 'Anda tidak memiliki akses ke data penjualan outlet lain.');
        }

        if (($penjualan->status ?? 'Draft') !== 'SUKSES') {
            return redirect()->route('jurnal-penjualanpos.index')->with('error', 'Transaksi POS ini belum di-approve.');
        }

        // 2. Ambil master data COA untuk dropdown penyesuaian di form view
        $coas = \App\Models\ChartOfAccount::orderBy('kode', 'asc')->get();

        // 3. Hitung akumulasi nilai total HPP riil dari tabel detail POS
        // Jika hpp_satuan bernilai 0 (Draft), gunakan hpp_referensi dari master_barang sebagai fallback
        $posDetails = DB::table('penjualanpos_detail')
            ->join('master_barang', 'penjualanpos_detail.produk_id', '=', 'master_barang.id')
            ->where('penjualanpos_detail.penjualan_id', $id)
            ->select('penjualanpos_detail.qty', 'penjualanpos_detail.hpp_satuan', 'master_barang.hpp_referensi', 'penjualanpos_detail.subtotal')
            ->get();

        $totalHppRiil = 0;
        $nilaiPenjualan = 0;
        foreach ($posDetails as $pd) {
            $hpp = floatval($pd->hpp_satuan);
            if ($hpp <= 0) {
                $hpp = floatval($pd->hpp_referensi);
            }
            $totalHppRiil += floatval($pd->qty) * $hpp;
            $nilaiPenjualan += floatval($pd->subtotal);
        }

        $tarifPpn       = 0.10; // Sesuaikan dengan tarif PPN yang berlaku (misal 10%)
        $nilaiPpn       = $nilaiPenjualan * $tarifPpn;
        $totalKasMasuk  = $nilaiPenjualan + $nilaiPpn;

        // Deteksi Outlet / Gudang (Gaharu vs Kejingga)
        $gudang = DB::table('master_gudang')->where('id', $penjualan->gudang_id)->first();
        $isKejingga = ($penjualan->gudang_id == 4) || ($gudang && stripos($gudang->nama, 'kejingga') !== false);

        $kodeHpp       = $isKejingga ? '5102' : '5101';
        $kodePenjualan = $isKejingga ? '4102' : '4101';

        // Ambil ID COA secara dinamis berdasarkan kode resmi outlet
        $idKasOutlet      = DB::table('chart_of_accounts')->where('kode', '1101')->value('id') ?? 1;
        $idHppPos         = DB::table('chart_of_accounts')->where('kode', $kodeHpp)->value('id') ?? ($isKejingga ? 16 : 5);
        $idPenjualanPos   = DB::table('chart_of_accounts')->where('kode', $kodePenjualan)->value('id') ?? ($isKejingga ? 15 : 4);
        $idPpnKeluaran    = DB::table('chart_of_accounts')->where('kode', '2201')->value('id') ?? 6;
        $idPersediaanJadi = DB::table('chart_of_accounts')->where('kode', '1301')->value('id') ?? 3;

        // 4. Susun Draf Jurnal Otomatis (5 Baris Berpasangan - Pastikan Seimbang)
        $defaultDetails = [
            // --- Kelompok Penerimaan Arus Kas Omzet & Pajak ---
            [
                'account_id' => $idKasOutlet,
                'debit'      => $totalKasMasuk,
                'kredit'     => 0
            ],
            [
                'account_id' => $idPenjualanPos,
                'debit'      => 0,
                'kredit'     => $nilaiPenjualan
            ],
            [
                'account_id' => $idPpnKeluaran,
                'debit'      => 0,
                'kredit'     => $nilaiPpn
            ],
            // --- Kelompok Matching Concept Pengurangan Stok Harian ---
            [
                'account_id' => $idHppPos,
                'debit'      => floatval($totalHppRiil),
                'kredit'     => 0
            ],
            [
                'account_id' => $idPersediaanJadi,
                'debit'      => 0,
                'kredit'     => floatval($totalHppRiil)
            ]
        ];

        return view('jurnal-penjualanpos.create', compact('penjualan', 'coas', 'defaultDetails'));
    }

    public function penjualanposStore(Request $request, $id)
    {
        $penjualan = DB::table('penjualan_pos')->where('id', $id)->first();

        if (!$penjualan || ($penjualan->status ?? 'Draft') !== 'SUKSES') {
            return redirect()->route('jurnal-penjualanpos.index')->with('error', 'Transaksi POS ini belum di-approve.');
        }

        // --- VALIDASI ROLE / OUTLET AKSES ---
        $user = auth()->user();
        if ($user && $user->gudang_id && $penjualan->gudang_id != $user->gudang_id) {
            abort(403, 'Anda tidak memiliki akses ke data penjualan outlet lain.');
        }

        $request->validate([
            'tanggal'              => 'required|date',
            'no_ref'               => 'required|string',
            'deskripsi'            => 'required|string',
            'details'              => 'required|array|min:2',
            'details.*.account_id' => 'required|exists:chart_of_accounts,id',
            'details.*.debit'      => 'required|numeric|min:0',
            'details.*.kredit'     => 'required|numeric|min:0',
        ]);

        try {
            DB::beginTransaction();

            // Cek Keseimbangan saldo form sebelum masuk database
            $totalDebit = 0;
            $totalKredit = 0;
            foreach ($request->details as $item) {
                $totalDebit += floatval($item['debit'] ?? 0);
                $totalKredit += floatval($item['kredit'] ?? 0);
            }

            if (round($totalDebit, 2) != round($totalKredit, 2)) {
                throw new \Exception("Total Debit dan Kredit tidak seimbang!");
            }

            // Simpan Header ke tabel jurnal_penjualan_pos
            $jurnalId = DB::table('jurnal_penjualan_pos')->insertGetId([
                'tanggal'     => $request->tanggal,
                'no_ref'      => $request->no_ref,
                'deskripsi'   => $request->deskripsi,
                'source_type' => 'penjualan_pos',
                'source_id'   => $penjualan->id, // Mengunci ID POS agar keluar dari antrean index atas
                'created_by'  => auth()->id() ?? 1,
            ]);

            // Simpan Detail baris jurnal secara dinamis dari form browser
            // [OPSI A] Setiap baris kini ikut menyimpan journal_type = 'jurnal_penjualan_pos'
            // supaya nantinya bisa dibedakan dari baris journal_items milik modul lain
            // (mis. jurnal_pembelian) walau journal_id-nya kebetulan bernilai sama.
            foreach ($request->details as $item) {
                if (floatval($item['debit']) == 0 && floatval($item['kredit']) == 0) {
                    continue;
                }

                DB::table('journal_items')->insert([
                    'journal_id'   => $jurnalId,
                    'journal_type' => 'jurnal_penjualan_pos', // [OPSI A]
                    'account_id'   => $item['account_id'],
                    'debit'        => $item['debit'],
                    'kredit'       => $item['kredit'],
                ]);
            }

            DB::commit();
            return redirect()
                ->route('jurnal-penjualanpos.index')
                ->with('success', 'Jurnal Penjualan POS No. Ref ' . $request->no_ref . ' berhasil dibukukan harian!');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Gagal memproses draf jurnal POS: ' . $e->getMessage())->withInput();
        }
    }

    public function penjualanposShow($id)
    {
        // 1. Ambil data induk/header jurnal khusus POS dari tabel jurnal_penjualan_pos
        $jurnal = DB::table('jurnal_penjualan_pos')
            ->where('id', $id)
            ->where('source_type', 'penjualan_pos') // Menyelaraskan token identitas asal pos
            ->first();

        if (!$jurnal) {
            return redirect()
                ->route('jurnal-penjualanpos.index')
                ->with('error', 'Data riwayat jurnal Penjualan POS tidak ditemukan.');
        }

        // --- VALIDASI ROLE / OUTLET AKSES ---
        $user = auth()->user();
        if ($user && $user->gudang_id) {
            $penjualan = DB::table('penjualan_pos')->where('id', $jurnal->source_id)->first();
            if ($penjualan && $penjualan->gudang_id != $user->gudang_id) {
                abort(403, 'Anda tidak memiliki akses ke data jurnal penjualan outlet lain.');
            }
        }

        // 2. Ambil rincian item debit/kredit yang terikat dengan ID jurnal POS ini
        // [OPSI A] Tambahan where journal_type mencegah baris journal_items milik
        // modul lain (mis. jurnal_pembelian) ikut tertarik hanya karena journal_id
        // mereka kebetulan sama dengan ID jurnal POS yang sedang dibuka ini.
        $details = DB::table('journal_items')
            ->leftJoin('chart_of_accounts', 'journal_items.account_id', '=', 'chart_of_accounts.id')
            ->where('journal_items.journal_id', $id)
            ->where('journal_items.journal_type', 'jurnal_penjualan_pos') // [OPSI A]
            ->select(
                'journal_items.debit',
                'journal_items.kredit',
                'chart_of_accounts.kode',
                'chart_of_accounts.nama'
            )
            ->orderBy('journal_items.debit', 'desc') // Mengurutkan akun Debit agar di atas demi estetika akuntansi
            ->get();

        // 3. Hitung total nilai saldo debit & kredit global untuk tfoot balance check
        $totalDebit = $details->sum('debit');
        $totalKredit = $details->sum('kredit');

        return view('jurnal-penjualanpos.show', compact('jurnal', 'details', 'totalDebit', 'totalKredit'));
    }

    public function penjualanb2bIndex()
    {
        // 1. Ambil ID Sumber yang sudah pernah dijurnal
        $pembayaranDijurnal = DB::table('jurnal_penjualan_b2b')->where('source_type', 'pembayaran')->pluck('source_id')->toArray();
        $pengirimanDijurnal = DB::table('jurnal_penjualan_b2b')->where('source_type', 'pengiriman')->pluck('source_id')->toArray();

        // 2. Antrean: Pembayaran Kas (Uang Muka / Pelunasan)
        $pembayaranBelum = \App\Models\Pembayaran::with(['pesanan.customer'])
            ->whereNotNull('pesanan_id')
            ->whereNotIn('id', $pembayaranDijurnal)
            ->get()
            ->map(function ($item) {
                $item->antrean_type = 'pembayaran';
                $item->label_antrean = 'Pembayaran Kas (DP/Pelunasan)';
                $item->tanggal_antrean = $item->tanggal_bayar;
                $item->no_transaksi = $item->pesanan->kode_pesanan ?? '-'; // Mengisi no_transaksi dengan kode pesanan
                $item->nominal_display = floatval($item->jumlah_bayar); // Angka murni untuk number_format
                return $item;
            });

        // 3. Antrean: Pengiriman Barang (Logistik & HPP)
        $pengirimanBelum = DB::table('pengiriman')
            ->join('pesanan', 'pengiriman.pesanan_id', '=', 'pesanan.id')
            ->join('customers', 'pesanan.customer_id', '=', 'customers.id')
            ->whereNotIn('pengiriman.id', $pengirimanDijurnal)
            ->select(
                'pengiriman.id',
                'pengiriman.no_pengiriman',
                'pengiriman.tanggal_pengiriman as tanggal_antrean',
                'pesanan.kode_pesanan',
                'pesanan.total_pesanan',
                'customers.nama as nama_customer'
            )
            ->get()
            ->map(function ($item) {
                $item->antrean_type = 'pengiriman';
                $item->label_antrean = 'Pengiriman Barang & HPP';
                $item->no_transaksi = $item->no_pengiriman; // Mengisi no_transaksi dengan nomor pengiriman surat jalan
                $item->nominal_display = floatval($item->total_pesanan); // Angka murni nilai kontrak B2B (DPP)
                return $item;
            });

        // Gabungkan kedua antrean dan urutkan berdasarkan tanggal terbaru
        $pesananBelum = $pembayaranBelum->concat($pengirimanBelum)->sortByDesc('tanggal_antrean');

        // 4. Ringkas data buku jurnal khusus penjualan B2B (Satu baris per No. Ref)
        $jurnalsSudah = DB::table('jurnal_penjualan_b2b')
            ->join('journal_items', function ($join) {
                $join->on('journal_items.journal_id', '=', 'jurnal_penjualan_b2b.id')
                    ->where('journal_type', '=', 'penjualan_b2b');
            })
            ->select(
                'jurnal_penjualan_b2b.id',
                'jurnal_penjualan_b2b.tanggal',
                'jurnal_penjualan_b2b.no_ref',
                'jurnal_penjualan_b2b.deskripsi',
                DB::raw('SUM(journal_items.debit) as total_debit'),
                DB::raw('SUM(journal_items.kredit) as total_kredit')
            )
            ->groupBy(
                'jurnal_penjualan_b2b.id',
                'jurnal_penjualan_b2b.tanggal',
                'jurnal_penjualan_b2b.no_ref',
                'jurnal_penjualan_b2b.deskripsi'
            )
            ->orderBy('jurnal_penjualan_b2b.tanggal', 'desc')
            ->paginate(10);

        return view('jurnal-penjualanb2b.index', compact('pesananBelum', 'jurnalsSudah'));
    }

    public function penjualanb2bCreate(Request $request, $id)
    {
        $type = $request->query('type', 'pembayaran');
        $coas = \App\Models\ChartOfAccount::orderBy('kode', 'asc')->get();

        // PENCATATAN ID COA SECARA DINAMIS BERDASARKAN KODE AKUN RESMI
        $idKasBank        = DB::table('chart_of_accounts')->where('kode', '1101')->value('id') ?? 15;
        $idUangMuka       = DB::table('chart_of_accounts')->where('kode', '2102')->value('id') ?? 29;
        $idPendapatan     = DB::table('chart_of_accounts')->where('kode', '4103')->value('id') ?? 39;
        $idPpnKeluaran    = DB::table('chart_of_accounts')->where('kode', '2201')->value('id') ?? 30;
        $idPersediaanJadi = DB::table('chart_of_accounts')->where('kode', '1301')->value('id') ?? 19;
        $idHPP            = DB::table('chart_of_accounts')->where('kode', '5103')->value('id') ?? 43;

        $defaultDetails = [];
        $pembayaran = null;
        $pengiriman = null;

        // PINTU LOGIKAA 1: JURNAL ALIRAN KAS MASUK (DP ATAU PELUNASAN KEDUA)
        if ($type === 'pembayaran') {
            $pembayaran = \App\Models\Pembayaran::with(['pesanan.customer'])->findOrFail($id);

            if (\App\Models\Journal::isPeriodClosed($pembayaran->tanggal_bayar ?? $pembayaran->tanggal ?? null)) {
                return redirect()->route('jurnal-penjualanb2b.index')->with('error', 'Periode akuntansi tanggal ' . date('d/m/Y', strtotime($pembayaran->tanggal_bayar ?? $pembayaran->tanggal)) . ' sudah ditutup buku. Tidak dapat memproses jurnal B2B pada periode yang sudah ditutup.');
            }

            $pesanan = $pembayaran->pesanan;

            if (!$pesanan) {
                return back()->with('error', 'Data induk pesanan untuk pembayaran ini tidak ditemukan.');
            }

            $statusPesanan = $pesanan->status_pesanan;
            $taxPercentage = floatval($pesanan->tax_percentage ?? 0);

            // Nilai dari tabel pembayaran adalah kas yang diterima (DPP + PPN)
            $totalKasBank = floatval($pembayaran->jumlah_bayar);
            $dppCurrent   = round($totalKasBank / (1 + ($taxPercentage / 100)), 2);
            $ppnCurrent   = round($totalKasBank - $dppCurrent, 2);

            $defaultDetails = [
                ['account_id' => $idKasBank, 'debit' => $totalKasBank, 'kredit' => 0],
                ['account_id' => $idUangMuka, 'debit' => 0, 'kredit' => $dppCurrent]
            ];

            if ($ppnCurrent > 0) {
                $defaultDetails[] = ['account_id' => $idPpnKeluaran, 'debit' => 0, 'kredit' => $ppnCurrent];
            }

            return view('jurnal-penjualanb2b.create', compact('pembayaran', 'pesanan', 'coas', 'defaultDetails', 'type', 'statusPesanan'));
        }

        // PINTU LOGIKAA 2: JURNAL AKHIR PENGIRIMAN BARANG (PENGAKUAN OMZET & POTONG GUDANG HPP)
        else {
            $pengiriman = DB::table('pengiriman')->where('id', $id)->first();
            if (!$pengiriman) {
                return back()->with('error', 'Data pengiriman tidak ditemukan.');
            }

            if (\App\Models\Journal::isPeriodClosed($pengiriman->tanggal_pengiriman ?? $pengiriman->tanggal ?? null)) {
                return redirect()->route('jurnal-penjualanb2b.index')->with('error', 'Periode akuntansi tanggal ' . date('d/m/Y', strtotime($pengiriman->tanggal_pengiriman ?? $pengiriman->tanggal)) . ' sudah ditutup buku. Tidak dapat memproses jurnal B2B pada periode yang sudah ditutup.');
            }

            $pesanan = \App\Models\Pesanan::with('customer')->findOrFail($pengiriman->pesanan_id);
            $statusPesanan = $pesanan->status_pesanan;
            $taxPercentage = floatval($pesanan->tax_percentage ?? 0);

            // Pengakuan Omzet komersial bersih (DPP)
            $totalDppPenjualan = round(floatval($pesanan->total_pesanan) / (1 + ($taxPercentage / 100)), 2);

            // Ambil biaya HPP
            $totalHppRiil = DB::table('alokasi_produksi_pesanan')->where('pesanan_id', $pesanan->id)->sum('total_hpp_alokasi') ?? 0;
            if ($totalHppRiil == 0) {
                $totalHppRiil = round($totalDppPenjualan * 0.75, 2);
            }

            // 1. Pengakuan Omzet komersial bersih: Balik Uang Muka Penjualan (Debet) vs Penjualan B2B (Kredit)
            $defaultDetails[] = ['account_id' => $idUangMuka, 'debit' => $totalDppPenjualan, 'kredit' => 0];
            $defaultDetails[] = ['account_id' => $idPendapatan, 'debit' => 0, 'kredit' => $totalDppPenjualan];

            // 2. Pengeluaran Fisik Barang: HPP Bertambah (Debet) vs Persediaan Barang Jadi Berkurang (Kredit)
            $defaultDetails[] = ['account_id' => $idHPP, 'debit' => $totalHppRiil, 'kredit' => 0];
            $defaultDetails[] = ['account_id' => $idPersediaanJadi, 'debit' => 0, 'kredit' => $totalHppRiil];

            return view('jurnal-penjualanb2b.create', compact('pengiriman', 'pesanan', 'coas', 'defaultDetails', 'type', 'statusPesanan'));
        }
    }

    public function penjualanb2bStore(Request $request, $id)
    {
        $request->validate([
            'tanggal'              => 'required|date',
            'no_ref'               => 'required|string',
            'deskripsi'            => 'required|string',
            'source_type'          => 'required|in:pembayaran,pengiriman',
            'details'              => 'required|array|min:2',
            'details.*.account_id' => 'required|exists:chart_of_accounts,id',
            'details.*.debit'      => 'required|numeric|min:0',
            'details.*.kredit'     => 'required|numeric|min:0',
        ]);

        if (\App\Models\Journal::isPeriodClosed($request->tanggal)) {
            return back()->with('error', 'Periode akuntansi tanggal ' . date('d/m/Y', strtotime($request->tanggal)) . ' sudah ditutup buku. Tidak dapat menyimpan Jurnal Penjualan B2B pada periode yang sudah ditutup.')->withInput();
        }

        try {
            DB::beginTransaction();

            $totalDebit = 0;
            $totalKredit = 0;
            foreach ($request->details as $item) {
                $totalDebit += floatval($item['debit'] ?? 0);
                $totalKredit += floatval($item['kredit'] ?? 0);
            }

            if (abs(round($totalDebit, 2) - round($totalKredit, 2)) > 0.01) {
                throw new \Exception("Total Debit dan Kredit tidak seimbang!");
            }

            $jurnalsId = DB::table('jurnal_penjualan_b2b')->insertGetId([
                'tanggal'     => $request->tanggal,
                'no_ref'      => $request->no_ref,
                'deskripsi'   => $request->deskripsi,
                'source_type' => $request->source_type,
                'source_id'   => $id,
                'created_by'  => auth()->id() ?? 1,
            ]);

            foreach ($request->details as $item) {
                if (floatval($item['debit']) == 0 && floatval($item['kredit']) == 0) {
                    continue;
                }

                DB::table('journal_items')->insert([
                    'journal_id'   => $jurnalsId,
                    'journal_type' => 'penjualan_b2b',
                    'account_id'   => $item['account_id'],
                    'debit'        => $item['debit'],
                    'kredit'       => $item['kredit'],
                ]);
            }

            DB::commit();
            return redirect()
                ->route('jurnal-penjualanb2b.index')
                ->with('success', 'Transaksi No. Ref ' . $request->no_ref . ' berhasil dibukukan!');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Gagal memproses simpan jurnal: ' . $e->getMessage())->withInput();
        }
    }

    public function penjualanb2bShow($id)
    {
        $jurnal = DB::table('jurnal_penjualan_b2b')->where('id', $id)->first();

        if (!$jurnal) {
            return redirect()->route('jurnal-penjualanb2b.index')->with('error', 'Data riwayat jurnal B2B tidak ditemukan.');
        }

        $details = DB::table('journal_items')
            ->leftJoin('chart_of_accounts', 'journal_items.account_id', '=', 'chart_of_accounts.id')
            ->where('journal_items.journal_id', $id)
            ->where('journal_items.journal_type', 'penjualan_b2b')
            ->select('journal_items.debit', 'journal_items.kredit', 'chart_of_accounts.kode', 'chart_of_accounts.nama')
            ->orderBy('journal_items.debit', 'desc')
            ->get();

        $totalDebit = $details->sum('debit');
        $totalKredit = $details->sum('kredit');

        return view('jurnal-penjualanb2b.show', compact('jurnal', 'details', 'totalDebit', 'totalKredit'));
    }

    public function bukuPembantuIndex(Request $request)
    {
        // 1. Tangkap jenis laporan dari parameter URL (default: 'utang')
        $jenis = $request->query('jenis', 'utang');

        // 2. Ambil ID COA secara dinamis berdasarkan kode resmi di sistem
        $idPiutang      = DB::table('chart_of_accounts')->where('kode', '1201')->value('id') ?? 16;
        $idUangMukaPemb = DB::table('chart_of_accounts')->where('kode', '1202')->value('id') ?? 17;
        $idUtang        = DB::table('chart_of_accounts')->where('kode', '2101')->value('id') ?? 28;
        $idUangMukaPenj = DB::table('chart_of_accounts')->where('kode', '2102')->value('id') ?? 29;

        $entities = collect();

        // 3. Query Data Berdasarkan Pilihan Tab
        switch ($jenis) {
            // --- SISI PEMBELIAN (SUPPLIERS) ---
            case 'utang':
            case 'um-pembelian':
                $targetAccountId = ($jenis === 'utang') ? $idUtang : $idUangMukaPemb;

                $jurnalSubquery = DB::table('jurnal_pembelian as j')
                    ->leftJoin('penerimaan_pembelian as rcv', function ($join) {
                        $join->on('j.source_id', '=', 'rcv.id')
                             ->where('j.source_type', '=', 'penerimaan_pembelian');
                    })
                    ->select('j.id', DB::raw('COALESCE(CASE WHEN j.source_type = "pembelian" THEN j.source_id END, rcv.pembelian_id) as pembelian_id'));

                $entities = DB::table('suppliers')
                    ->leftJoin('pembelian', 'suppliers.id', '=', 'pembelian.supplier_id')
                    ->leftJoinSub($jurnalSubquery, 'j_sub', 'pembelian.id', '=', 'j_sub.pembelian_id')
                    ->leftJoin('journal_items', function ($join) use ($targetAccountId) {
                        $join->on('journal_items.journal_id', '=', 'j_sub.id')
                            ->where('journal_items.journal_type', '=', 'jurnal_pembelian')
                            ->where('journal_items.account_id', '=', $targetAccountId);
                    })
                    ->select(
                        'suppliers.id as entity_id',
                        'suppliers.nama as nama',
                        DB::raw("CONCAT('NO. ', suppliers.id) as kode_akun"),
                        DB::raw("COALESCE(SUM(journal_items.debit), 0) as total_debit"),
                        DB::raw("COALESCE(SUM(journal_items.kredit), 0) as total_kredit"),
                        // Perhitungan Saldo Normal: Utang (Kredit - Debit), UM Pembelian (Debit - Kredit)
                        DB::raw("
                            COALESCE(SUM(
                                " . ($jenis === 'utang' ? "(journal_items.kredit - journal_items.debit)" : "(journal_items.debit - journal_items.kredit)") . "
                            ), 0) as saldo
                        ")
                    )
                    ->groupBy('suppliers.id', 'suppliers.nama')
                    ->orderBy('suppliers.nama')
                    ->get();
                break;

            // --- SISI PENJUALAN (CUSTOMERS) ---
            case 'piutang':
            case 'um-penjualan':
                $targetAccountId = ($jenis === 'piutang') ? $idPiutang : $idUangMukaPenj;

                $jurnalSubquery = DB::table('jurnal_penjualan_b2b as j')
                    ->leftJoin('pembayaran as p', function ($join) {
                        $join->on('j.source_id', '=', 'p.id')
                             ->where('j.source_type', '=', 'pembayaran');
                    })
                    ->leftJoin('pengiriman as sh', function ($join) {
                        $join->on('j.source_id', '=', 'sh.id')
                             ->where('j.source_type', '=', 'pengiriman');
                    })
                    ->select('j.id', DB::raw('COALESCE(p.pesanan_id, sh.pesanan_id) as pesanan_id'));

                $entities = DB::table('customers')
                    ->leftJoin('pesanan', 'customers.id', '=', 'pesanan.customer_id')
                    ->leftJoinSub($jurnalSubquery, 'j_sub', 'pesanan.id', '=', 'j_sub.pesanan_id')
                    ->leftJoin('journal_items', function ($join) use ($targetAccountId) {
                        $join->on('journal_items.journal_id', '=', 'j_sub.id')
                            ->whereIn('journal_items.journal_type', ['penjualan_b2b', 'jurnal_penjualan_b2b'])
                            ->where('journal_items.account_id', '=', $targetAccountId);
                    })
                    ->select(
                        'customers.id as entity_id',
                        'customers.nama as nama',
                        DB::raw("CONCAT('NO. ', customers.id) as kode_akun"),
                        DB::raw("COALESCE(SUM(journal_items.debit), 0) as total_debit"),
                        DB::raw("COALESCE(SUM(journal_items.kredit), 0) as total_kredit"),
                        // Perhitungan Saldo Normal: Piutang (Debit - Kredit), UM Penjualan (Kredit - Debit)
                        DB::raw("
                            COALESCE(SUM(
                                " . ($jenis === 'piutang' ? "(journal_items.debit - journal_items.kredit)" : "(journal_items.kredit - journal_items.debit)") . "
                            ), 0) as saldo
                        ")
                    )
                    ->groupBy('customers.id', 'customers.nama')
                    ->orderBy('customers.nama')
                    ->get();
                break;
        }

        // 4. Transformasi Data untuk Tampilan Blade dengan logika penyelarasan status terima sebagian / selesai
        $entities = $entities->map(function ($item) use ($jenis) {
            $isCompleted = false;

            if (in_array($jenis, ['utang', 'um-pembelian'])) {
                $purchases = DB::table('pembelian')
                    ->where('supplier_id', $item->entity_id)
                    ->get();

                if ($purchases->isEmpty()) {
                    $isCompleted = true;
                } else {
                    $allMatch = true;
                    foreach ($purchases as $p) {
                        $hasReceipt = DB::table('penerimaan_pembelian')->where('pembelian_id', $p->id)->exists();
                        $ok = ($p->is_lunas == 1) && ($p->is_diterima == 1 || $hasReceipt);
                        if (!$ok) {
                            $allMatch = false;
                            break;
                        }
                    }
                    $isCompleted = $allMatch;
                }
            } else {
                $orders = DB::table('pesanan')
                    ->where('customer_id', $item->entity_id)
                    ->get();

                if ($orders->isEmpty()) {
                    $isCompleted = true;
                } else {
                    $allMatch = true;
                    foreach ($orders as $o) {
                        $hasShipment = DB::table('pengiriman')
                            ->where('pesanan_id', $o->id)
                            ->where('status_pengiriman', 'Selesai')
                            ->exists();
                        $ok = ($o->status_pembayaran === 'Lunas') && ($o->status_pesanan === 'Selesai' || $hasShipment);
                        if (!$ok) {
                            $allMatch = false;
                            break;
                        }
                    }
                    $isCompleted = $allMatch;
                }
            }

            if ($isCompleted) {
                $item->saldo = 0;
                $item->status = 'Lunas';
                $item->keterangan_sub = 'Semua transaksi sudah selesai';
            } else {
                $item->status = ($item->saldo <= 0) ? 'Lunas' : 'Belum Lunas';
                $item->keterangan_sub = ($item->saldo <= 0) 
                    ? 'Semua transaksi sudah selesai' 
                    : 'Terdapat transaksi aktif / belum tuntas';
            }

            return $item;
        });

        // 5. Summary Metrics
        $summary = [
            'total_saldo'   => $entities->sum('saldo'),
            'total_akun'    => $entities->count(),
            'total_pending' => $entities->where('saldo', '>', 0)->count(),
        ];

        return view('buku-pembantu.index', compact('jenis', 'entities', 'summary'));
    }

    public function bukuPembantuShow(Request $request, $jenis, $id)
    {
        $idPiutang      = DB::table('chart_of_accounts')->where('kode', '1201')->value('id') ?? 16;
        $idUangMukaPemb = DB::table('chart_of_accounts')->where('kode', '1202')->value('id') ?? 17;
        $idUtang        = DB::table('chart_of_accounts')->where('kode', '2101')->value('id') ?? 28;
        $idUangMukaPenj = DB::table('chart_of_accounts')->where('kode', '2102')->value('id') ?? 29;

        $entity = null;
        $mutasi = collect();

        switch ($jenis) {
            case 'utang':
            case 'um-pembelian':
                $targetAccountId = ($jenis === 'utang') ? $idUtang : $idUangMukaPemb;
                
                $entity = DB::table('suppliers')->where('id', $id)->first();
                if (!$entity) abort(404, 'Supplier tidak ditemukan.');

                $jurnalSubquery = DB::table('jurnal_pembelian as j')
                    ->leftJoin('penerimaan_pembelian as rcv', function ($join) {
                        $join->on('j.source_id', '=', 'rcv.id')
                             ->where('j.source_type', '=', 'penerimaan_pembelian');
                    })
                    ->select('j.id', DB::raw('COALESCE(CASE WHEN j.source_type = "pembelian" THEN j.source_id END, rcv.pembelian_id) as pembelian_id'));

                $mutasi = DB::table('journal_items')
                    ->joinSub($jurnalSubquery, 'j_sub', 'journal_items.journal_id', '=', 'j_sub.id')
                    ->join('jurnal_pembelian', 'j_sub.id', '=', 'jurnal_pembelian.id')
                    ->join('pembelian', 'j_sub.pembelian_id', '=', 'pembelian.id')
                    ->where('journal_items.journal_type', '=', 'jurnal_pembelian')
                    ->where('journal_items.account_id', '=', $targetAccountId)
                    ->where('pembelian.supplier_id', '=', $id)
                    ->select(
                        'jurnal_pembelian.tanggal',
                        'jurnal_pembelian.deskripsi as keterangan',
                        'jurnal_pembelian.no_ref as ref',
                        'journal_items.debit',
                        'journal_items.kredit'
                    )
                    ->orderBy('jurnal_pembelian.tanggal', 'asc')
                    ->orderBy('jurnal_pembelian.id', 'asc')
                    ->get();
                break;

            case 'piutang':
            case 'um-penjualan':
                $targetAccountId = ($jenis === 'piutang') ? $idPiutang : $idUangMukaPenj;

                $entity = DB::table('customers')->where('id', $id)->first();
                if (!$entity) abort(404, 'Customer tidak ditemukan.');

                $jurnalSubquery = DB::table('jurnal_penjualan_b2b as j')
                    ->leftJoin('pembayaran as p', function ($join) {
                        $join->on('j.source_id', '=', 'p.id')
                             ->where('j.source_type', '=', 'pembayaran');
                    })
                    ->leftJoin('pengiriman as sh', function ($join) {
                        $join->on('j.source_id', '=', 'sh.id')
                             ->where('j.source_type', '=', 'pengiriman');
                    })
                    ->select('j.id', DB::raw('COALESCE(p.pesanan_id, sh.pesanan_id) as pesanan_id'));

                $mutasi = DB::table('journal_items')
                    ->joinSub($jurnalSubquery, 'j_sub', 'journal_items.journal_id', '=', 'j_sub.id')
                    ->join('jurnal_penjualan_b2b', 'j_sub.id', '=', 'jurnal_penjualan_b2b.id')
                    ->join('pesanan', 'j_sub.pesanan_id', '=', 'pesanan.id')
                    ->whereIn('journal_items.journal_type', ['penjualan_b2b', 'jurnal_penjualan_b2b'])
                    ->where('journal_items.account_id', '=', $targetAccountId)
                    ->where('pesanan.customer_id', '=', $id)
                    ->select(
                        'jurnal_penjualan_b2b.tanggal',
                        'jurnal_penjualan_b2b.deskripsi as keterangan',
                        'jurnal_penjualan_b2b.no_ref as ref',
                        'journal_items.debit',
                        'journal_items.kredit'
                    )
                    ->orderBy('jurnal_penjualan_b2b.tanggal', 'asc')
                    ->orderBy('jurnal_penjualan_b2b.id', 'asc')
                    ->get();
                break;
        }

        // Akumulasi Saldo Berjalan (Running Balance)
        $runningSaldo = 0;
        $mutasi = $mutasi->map(function ($row) use (&$runningSaldo, $jenis) {
            $debit = floatval($row->debit);
            $kredit = floatval($row->kredit);

            if (in_array($jenis, ['utang', 'um-penjualan'])) {
                $runningSaldo += ($kredit - $debit);
            } else {
                $runningSaldo += ($debit - $kredit);
            }

            $row->saldo_akumulasi = $runningSaldo;
            return $row;
        });

        // Penyelarasan saldoAkhir dengan logika "terima sebagian" / transaksi selesai
        $isCompleted = false;
        if (in_array($jenis, ['utang', 'um-pembelian'])) {
            $purchases = DB::table('pembelian')->where('supplier_id', $id)->get();
            if ($purchases->isEmpty()) {
                $isCompleted = true;
            } else {
                $allMatch = true;
                foreach ($purchases as $p) {
                    $hasReceipt = DB::table('penerimaan_pembelian')->where('pembelian_id', $p->id)->exists();
                    $ok = ($p->is_lunas == 1) && ($p->is_diterima == 1 || $hasReceipt);
                    if (!$ok) {
                        $allMatch = false;
                        break;
                    }
                }
                $isCompleted = $allMatch;
            }
        } else {
            $orders = DB::table('pesanan')->where('customer_id', $id)->get();
            if ($orders->isEmpty()) {
                $isCompleted = true;
            } else {
                $allMatch = true;
                foreach ($orders as $o) {
                    $hasShipment = DB::table('pengiriman')
                        ->where('pesanan_id', $o->id)
                        ->where('status_pengiriman', 'Selesai')
                        ->exists();
                    $ok = ($o->status_pembayaran === 'Lunas') && ($o->status_pesanan === 'Selesai' || $hasShipment);
                    if (!$ok) {
                        $allMatch = false;
                        break;
                    }
                }
                $isCompleted = $allMatch;
            }
        }

        $saldoAkhir = $isCompleted ? 0 : $runningSaldo;

        return view('buku-pembantu.show', compact('jenis', 'entity', 'mutasi', 'saldoAkhir'));
    }

    public function pembelianPostAuto(Request $request, $id)
    {
        $tahap = trim(strtolower((string)$request->query('tahap')));
        
        if (in_array($tahap, ['reklas_lunas', 'cod'])) {
            $penerimaan = \App\Models\PenerimaanPembelian::with(['pembelian.supplier', 'details.barang'])->findOrFail($id);
            $pembelian = $penerimaan->pembelian;
            $tanggal = $penerimaan->tanggal ?? now()->format('Y-m-d');
            $noRef = $penerimaan->no_penerimaan;
        } else {
            $pembelian = \App\Models\Pembelian::with(['supplier', 'details.barang'])->findOrFail($id);
            $tanggal = $pembelian->tanggal ?? now()->format('Y-m-d');
            $noRef = $pembelian->kode_pembelian;
        }

        if (\App\Models\Journal::isPeriodClosed($tanggal)) {
            return back()->with('error', 'Periode akuntansi tanggal ' . date('d/m/Y', strtotime($tanggal)) . ' sudah ditutup buku. Tidak dapat menambah jurnal pada periode yang sudah ditutup.');
        }

        $hasOperational = false;
        foreach ($pembelian->details as $det) {
            if ($det->barang && ($det->barang->is_operational || !$det->barang->is_bahan_baku)) {
                $hasOperational = true;
                break;
            }
        }

        $debitCoaCode = $hasOperational ? '1501' : '1301';
        $idKasBank        = DB::table('chart_of_accounts')->where('kode', '1101')->value('id') ?? 15;
        $idPersediaan     = DB::table('chart_of_accounts')->where('kode', $debitCoaCode)->value('id') ?? ($hasOperational ? 27 : 19);
        $idUangMukaPemb   = DB::table('chart_of_accounts')->where('kode', '1202')->value('id') ?? 17;
        $idPPNMasukan     = DB::table('chart_of_accounts')->where('kode', '1203')->value('id') ?? 18;

        $defaultDetails = [];

        if (in_array($tahap, ['reklas_lunas', 'cod'])) {
            $dppTotal = 0;
            foreach ($penerimaan->details as $det) {
                $dppTotal += floatval($det->qty) * floatval($det->harga_per_qty);
            }

            if ($tahap === 'reklas_lunas') {
                $hasPelunasanJournal = DB::table('jurnal_pembelian')
                    ->where('source_id', $pembelian->id)
                    ->where('source_type', 'pembelian')
                    ->where('tahap', 'pelunasan')
                    ->exists();

                if ($hasPelunasanJournal) {
                    $defaultDetails = [
                        ['account_id' => $idPersediaan, 'debit' => $dppTotal, 'kredit' => 0],
                        ['account_id' => $idUangMukaPemb, 'debit' => 0, 'kredit' => $dppTotal]
                    ];
                } else {
                    $pembelianDppTotal = floatval($pembelian->total) - floatval($pembelian->tax_service ?? 0);
                    $dpMurni = floatval($pembelian->nominal_dp ?? ($pembelianDppTotal * (floatval($pembelian->persen_dp ?? 0) / 100)));
                    $sisaDpp = max(0, $pembelianDppTotal - $dpMurni);
                    $ppnTotal = floatval($pembelian->tax_service ?? 0);
                    $totalKasPelunasan = $sisaDpp + $ppnTotal;

                    $defaultDetails = [
                        ['account_id' => $idPersediaan, 'debit' => $dppTotal, 'kredit' => 0],
                        ['account_id' => $idPPNMasukan, 'debit' => $ppnTotal, 'kredit' => 0],
                        ['account_id' => $idUangMukaPemb, 'debit' => 0, 'kredit' => $dpMurni],
                        ['account_id' => $idKasBank, 'debit' => 0, 'kredit' => $totalKasPelunasan]
                    ];
                }
            } else {
                $ppnTotal = floatval($pembelian->tax_service ?? 0);
                $totalContracts = $dppTotal + $ppnTotal;

                $defaultDetails = [
                    ['account_id' => $idPersediaan, 'debit' => $dppTotal, 'kredit' => 0],
                    ['account_id' => $idPPNMasukan, 'debit' => $ppnTotal, 'kredit' => 0],
                    ['account_id' => $idKasBank, 'debit' => 0, 'kredit' => $totalContracts]
                ];
            }
        } else {
            $dppTotal = floatval($pembelian->total) - floatval($pembelian->tax_service ?? 0);
            $persenDP   = floatval($pembelian->persen_dp ?? 0);
            $dpMurni    = floatval($pembelian->nominal_dp ?? ($dppTotal * ($persenDP / 100)));
            $kasDP      = $dpMurni;

            $sisaDpp      = max(0, $dppTotal - $dpMurni);
            $ppnPelunasan = floatval($pembelian->tax_service ?? 0);
            $kasPelunasan = $sisaDpp + $ppnPelunasan;

            if ($tahap === 'dp') {
                $defaultDetails = [
                    ['account_id' => $idUangMukaPemb, 'debit' => $dpMurni, 'kredit' => 0],
                    ['account_id' => $idKasBank, 'debit' => 0, 'kredit' => $kasDP]
                ];
            } else {
                $defaultDetails = [
                    ['account_id' => $idUangMukaPemb, 'debit' => $sisaDpp, 'kredit' => 0],
                    ['account_id' => $idPPNMasukan, 'debit' => $ppnPelunasan, 'kredit' => 0],
                    ['account_id' => $idKasBank, 'debit' => 0, 'kredit' => $kasPelunasan]
                ];
            }
        }

        $descJurnal = 'Pembukuan jurnal khusus pembelian (' . strtoupper($tahap) . ') atas No. Invoice: ' . $pembelian->kode_pembelian;
        if (in_array($tahap, ['reklas_lunas', 'cod'])) {
            $descJurnal .= ' [No. Penerimaan: ' . $penerimaan->no_penerimaan . ']';
        }
        $descJurnal .= ' [Supplier: ' . ($pembelian->supplier->nama ?? '-') . ']';

        try {
            DB::beginTransaction();

            $jurnalPembelianId = DB::table('jurnal_pembelian')->insertGetId([
                'tanggal'     => $tanggal,
                'deskripsi'   => $descJurnal,
                'no_ref'      => $noRef,
                'source_type' => in_array($tahap, ['reklas_lunas', 'cod']) ? 'penerimaan_pembelian' : 'pembelian',
                'source_id'   => $id,
                'tahap'       => $tahap,
                'created_by'  => auth()->id() ?? 1,
            ]);

            foreach ($defaultDetails as $item) {
                if (floatval($item['debit']) == 0 && floatval($item['kredit']) == 0) {
                    continue;
                }

                DB::table('journal_items')->insert([
                    'journal_id'   => $jurnalPembelianId,
                    'journal_type' => 'jurnal_pembelian',
                    'account_id'   => $item['account_id'],
                    'debit'        => $item['debit'],
                    'kredit'       => $item['kredit'],
                ]);
            }

            DB::commit();
            return redirect()->route('jurnal-pembelian.index')->with('success', 'Jurnal khusus pembelian untuk ' . $pembelian->kode_pembelian . ' berhasil diposting!');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Gagal memproses posting jurnal: ' . $e->getMessage());
        }
    }

    public function penjualanposPostAuto(Request $request, $id)
    {
        try {
            self::autoPostPenjualanPos($id);
            $code = DB::table('penjualan_pos')->where('id', $id)->value('kode_transaksi');
            return redirect()->route('jurnal-penjualanpos.index')->with('success', 'Jurnal khusus penjualan POS untuk transaksi ' . $code . ' berhasil diposting!');
        } catch (\Exception $e) {
            return back()->with('error', 'Gagal memproses posting jurnal: ' . $e->getMessage());
        }
    }

    public function penjualanb2bPostAuto(Request $request, $id)
    {
        $type = $request->query('type', 'pembayaran');
        try {
            self::autoPostPenjualanB2b($id, $type);
            return redirect()->route('jurnal-penjualanb2b.index')->with('success', 'Jurnal khusus penjualan B2B untuk ' . ($type === 'pembayaran' ? 'pembayaran' : 'pengiriman') . ' berhasil diposting!');
        } catch (\Exception $e) {
            return back()->with('error', 'Gagal memproses posting jurnal: ' . $e->getMessage());
        }
    }

    public static function autoPostPembelian($pembelianId, $tahap, $penerimaanId = null)
    {
        try {
            DB::beginTransaction();

            $pembelian = \App\Models\Pembelian::with(['supplier', 'details.barang'])->findOrFail($pembelianId);

            $hasOperational = false;
            foreach ($pembelian->details as $det) {
                if ($det->barang && ($det->barang->is_operational || !$det->barang->is_bahan_baku)) {
                    $hasOperational = true;
                    break;
                }
            }

            $debitCoaCode = $hasOperational ? '1501' : '1301';

            $idKasBank      = DB::table('chart_of_accounts')->where('kode', '1101')->value('id') ?? 15;
            $idPersediaan   = DB::table('chart_of_accounts')->where('kode', $debitCoaCode)->value('id') ?? ($hasOperational ? 27 : 19);
            $idUangMukaPemb = DB::table('chart_of_accounts')->where('kode', '1202')->value('id') ?? 17;
            $idPPNMasukan   = DB::table('chart_of_accounts')->where('kode', '1203')->value('id') ?? 18;

            $defaultDetails = [];
            $sourceType = 'pembelian';
            $sourceId = $pembelian->id;
            $noRef = 'AJP-PB-' . strtoupper($tahap) . '-' . $pembelian->kode_pembelian;
            $descJurnal = $pembelian->kode_pembelian;

            if (in_array($tahap, ['reklas_lunas', 'cod'])) {
                if (!$penerimaanId) {
                    $penerimaan = \App\Models\PenerimaanPembelian::where('pembelian_id', $pembelianId)->latest()->first();
                } else {
                    $penerimaan = \App\Models\PenerimaanPembelian::findOrFail($penerimaanId);
                }

                if (!$penerimaan) {
                    throw new \Exception("Penerimaan barang tidak ditemukan.");
                }

                $sourceType = 'penerimaan_pembelian';
                $sourceId = $penerimaan->id;
                $noRef = 'AJP-RCV-' . strtoupper($tahap) . '-' . $penerimaan->no_penerimaan;
                $descJurnal .= ' [No. Penerimaan: ' . $penerimaan->no_penerimaan . ']';

                $dppTotal = 0;
                foreach ($penerimaan->details as $det) {
                    $dppTotal += floatval($det->qty) * floatval($det->harga_per_qty);
                }

                if ($tahap === 'reklas_lunas') {
                    $hasPelunasanJournal = DB::table('jurnal_pembelian')
                        ->where('source_id', $pembelian->id)
                        ->where('source_type', 'pembelian')
                        ->where('tahap', 'pelunasan')
                        ->exists();

                    if ($hasPelunasanJournal) {
                        $defaultDetails = [
                            ['account_id' => $idPersediaan, 'debit' => $dppTotal, 'kredit' => 0],
                            ['account_id' => $idUangMukaPemb, 'debit' => 0, 'kredit' => $dppTotal]
                        ];
                    } else {
                        $pembelianDppTotal = floatval($pembelian->total) - floatval($pembelian->tax_service ?? 0);
                        $dpMurni = floatval($pembelian->nominal_dp ?? ($pembelianDppTotal * (floatval($pembelian->persen_dp ?? 0) / 100)));
                        $sisaDpp = max(0, $pembelianDppTotal - $dpMurni);
                        $ppnTotal = floatval($pembelian->tax_service ?? 0);
                        $totalKasPelunasan = $sisaDpp + $ppnTotal;

                        $defaultDetails = [
                            ['account_id' => $idPersediaan, 'debit' => $dppTotal, 'kredit' => 0],
                            ['account_id' => $idPPNMasukan, 'debit' => $ppnTotal, 'kredit' => 0],
                            ['account_id' => $idUangMukaPemb, 'debit' => 0, 'kredit' => $dpMurni],
                            ['account_id' => $idKasBank, 'debit' => 0, 'kredit' => $totalKasPelunasan]
                        ];
                    }
                } else {
                    $ppnTotal = floatval($pembelian->tax_service ?? 0);
                    $totalContracts = $dppTotal + $ppnTotal;

                    $defaultDetails = [
                        ['account_id' => $idPersediaan, 'debit' => $dppTotal, 'kredit' => 0],
                        ['account_id' => $idPPNMasukan, 'debit' => $ppnTotal, 'kredit' => 0],
                        ['account_id' => $idKasBank, 'debit' => 0, 'kredit' => $totalContracts]
                    ];
                }
            } else {
                $dppTotal = floatval($pembelian->total) - floatval($pembelian->tax_service ?? 0);
                $persenDP   = floatval($pembelian->persen_dp ?? 0);
                $dpMurni    = floatval($pembelian->nominal_dp ?? ($dppTotal * ($persenDP / 100)));
                $ppnDP      = 0;
                $kasDP      = $dpMurni + $ppnDP;

                $sisaDpp      = max(0, $dppTotal - $dpMurni);
                $ppnPelunasan = floatval($pembelian->tax_service ?? 0);
                $kasPelunasan = $sisaDpp + $ppnPelunasan;

                if ($tahap === 'dp') {
                    $defaultDetails = [
                        ['account_id' => $idUangMukaPemb, 'debit' => $dpMurni, 'kredit' => 0],
                        ['account_id' => $idKasBank, 'debit' => 0, 'kredit' => $kasDP]
                    ];
                } else {
                    $defaultDetails = [
                        ['account_id' => $idUangMukaPemb, 'debit' => $sisaDpp, 'kredit' => 0],
                        ['account_id' => $idPPNMasukan, 'debit' => $ppnPelunasan, 'kredit' => 0],
                        ['account_id' => $idKasBank, 'debit' => 0, 'kredit' => $kasPelunasan]
                    ];
                }
            }

            $descJurnal .= ' [Supplier: ' . ($pembelian->supplier->nama ?? '-') . ']';

            $tanggalJurnal = date('Y-m-d');
            if (\App\Models\Journal::isPeriodClosed($tanggalJurnal)) {
                throw new \Exception("Periode akuntansi tanggal " . date('d/m/Y', strtotime($tanggalJurnal)) . " sudah ditutup buku.");
            }

            $jurnalPembelianId = DB::table('jurnal_pembelian')->insertGetId([
                'tanggal'     => $tanggalJurnal,
                'deskripsi'   => $descJurnal,
                'no_ref'      => $noRef,
                'source_type' => $sourceType,
                'source_id'   => $sourceId,
                'tahap'       => $tahap,
                'created_by'  => auth()->id() ?? 1,
            ]);

            foreach ($defaultDetails as $item) {
                if (floatval($item['debit']) == 0 && floatval($item['kredit']) == 0) {
                    continue;
                }

                DB::table('journal_items')->insert([
                    'journal_id'   => $jurnalPembelianId,
                    'journal_type' => 'jurnal_pembelian',
                    'account_id'   => $item['account_id'],
                    'debit'        => $item['debit'],
                    'kredit'       => $item['kredit'],
                ]);
            }

            DB::commit();
            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Gagal auto-post jurnal pembelian: ' . $e->getMessage());
            throw $e;
        }
    }

    public static function autoPostPenjualanPos($id)
    {
        try {
            DB::beginTransaction();

            $penjualan = DB::table('penjualan_pos')->where('id', $id)->first();
            if (!$penjualan) {
                throw new \Exception('Data induk penjualan POS tidak ditemukan.');
            }

            if (($penjualan->status ?? 'Draft') !== 'SUKSES') {
                throw new \Exception('Transaksi POS ini belum di-approve.');
            }

            // Mencegah duplikasi posting jurnal POS
            $alreadyPosted = DB::table('jurnal_penjualan_pos')
                ->where('source_type', 'penjualan_pos')
                ->where('source_id', $id)
                ->exists();
            if ($alreadyPosted) {
                DB::rollBack();
                return true;
            }

            $tanggal = $penjualan->tanggal ?? now()->format('Y-m-d');
            if (\App\Models\Journal::isPeriodClosed($tanggal)) {
                throw new \Exception('Periode akuntansi tanggal ' . date('d/m/Y', strtotime($tanggal)) . ' sudah ditutup buku.');
            }

            $posDetails = DB::table('penjualanpos_detail')
                ->join('master_barang', 'penjualanpos_detail.produk_id', '=', 'master_barang.id')
                ->where('penjualanpos_detail.penjualan_id', $id)
                ->select('penjualanpos_detail.qty', 'penjualanpos_detail.hpp_satuan', 'master_barang.hpp_referensi', 'penjualanpos_detail.subtotal')
                ->get();

            $totalHppRiil = 0;
            $nilaiPenjualan = 0;
            foreach ($posDetails as $pd) {
                $hpp = floatval($pd->hpp_satuan);
                if ($hpp <= 0) {
                    $hpp = floatval($pd->hpp_referensi);
                }
                $totalHppRiil += floatval($pd->qty) * $hpp;
                $nilaiPenjualan += floatval($pd->subtotal);
            }

            $tarifPpn       = 0.10;
            $nilaiPpn       = $nilaiPenjualan * $tarifPpn;
            $totalKasMasuk  = $nilaiPenjualan + $nilaiPpn;

            $gudang = DB::table('master_gudang')->where('id', $penjualan->gudang_id)->first();
            $isKejingga = ($penjualan->gudang_id == 4) || ($gudang && stripos($gudang->nama, 'kejingga') !== false);

            $kodeHpp       = $isKejingga ? '5102' : '5101';
            $kodePenjualan = $isKejingga ? '4102' : '4101';

            $idKasOutlet      = DB::table('chart_of_accounts')->where('kode', '1101')->value('id') ?? 15;
            $idHppPos         = DB::table('chart_of_accounts')->where('kode', $kodeHpp)->value('id') ?? ($isKejingga ? 42 : 41);
            $idPenjualanPos   = DB::table('chart_of_accounts')->where('kode', $kodePenjualan)->value('id') ?? ($isKejingga ? 38 : 37);
            $idPpnKeluaran    = DB::table('chart_of_accounts')->where('kode', '2201')->value('id') ?? 30;
            $idPersediaanJadi = DB::table('chart_of_accounts')->where('kode', '1301')->value('id') ?? 19;

            $defaultDetails = [
                ['account_id' => $idKasOutlet, 'debit' => $totalKasMasuk, 'kredit' => 0],
                ['account_id' => $idPenjualanPos, 'debit' => 0, 'kredit' => $nilaiPenjualan],
                ['account_id' => $idPpnKeluaran, 'debit' => 0, 'kredit' => $nilaiPpn],
                ['account_id' => $idHppPos, 'debit' => floatval($totalHppRiil), 'kredit' => 0],
                ['account_id' => $idPersediaanJadi, 'debit' => 0, 'kredit' => floatval($totalHppRiil)]
            ];

            $descJurnal = 'Penjualan Harian POS dari Outlet ' . ($gudang->nama ?? 'Gudang') . ' [No. Transaksi: ' . $penjualan->kode_transaksi . ']';

            $jurnalId = DB::table('jurnal_penjualan_pos')->insertGetId([
                'tanggal'     => $tanggal,
                'no_ref'      => 'JR-POS-' . time(),
                'deskripsi'   => $descJurnal,
                'source_type' => 'penjualan_pos',
                'source_id'   => $id,
                'created_by'  => $penjualan->created_by ?? auth()->id() ?? 1,
            ]);

            foreach ($defaultDetails as $item) {
                if (floatval($item['debit']) == 0 && floatval($item['kredit']) == 0) {
                    continue;
                }

                DB::table('journal_items')->insert([
                    'journal_id'   => $jurnalId,
                    'journal_type' => 'jurnal_penjualan_pos',
                    'account_id'   => $item['account_id'],
                    'debit'        => $item['debit'],
                    'kredit'       => $item['kredit'],
                ]);
            }

            DB::commit();
            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Gagal auto-post jurnal POS: ' . $e->getMessage());
            throw $e;
        }
    }

    public static function autoPostPenjualanB2b($id, $type)
    {
        try {
            DB::beginTransaction();

            $idKasBank        = DB::table('chart_of_accounts')->where('kode', '1101')->value('id') ?? 15;
            $idUangMuka       = DB::table('chart_of_accounts')->where('kode', '2102')->value('id') ?? 29;
            $idPendapatan     = DB::table('chart_of_accounts')->where('kode', '4103')->value('id') ?? 39;
            $idPpnKeluaran    = DB::table('chart_of_accounts')->where('kode', '2201')->value('id') ?? 30;
            $idPersediaanJadi = DB::table('chart_of_accounts')->where('kode', '1301')->value('id') ?? 19;
            $idHPP            = DB::table('chart_of_accounts')->where('kode', '5103')->value('id') ?? 43;

            $defaultDetails = [];
            $creatorId = 1;

            if ($type === 'pembayaran') {
                $pembayaran = \App\Models\Pembayaran::with(['pesanan.customer'])->findOrFail($id);
                $creatorId = $pembayaran->created_by ?? 1;

                // Mencegah duplikasi posting jurnal B2B pembayaran
                $alreadyPosted = DB::table('jurnal_penjualan_b2b')
                    ->where('source_type', 'pembayaran')
                    ->where('source_id', $id)
                    ->exists();
                if ($alreadyPosted) {
                    DB::rollBack();
                    return true;
                }

                $tanggal = $pembayaran->tanggal_bayar ?? $pembayaran->tanggal ?? now()->format('Y-m-d');
                if (\App\Models\Journal::isPeriodClosed($tanggal)) {
                    throw new \Exception('Periode akuntansi tanggal ' . date('d/m/Y', strtotime($tanggal)) . ' sudah ditutup buku.');
                }

                $pesanan = $pembayaran->pesanan;
                if (!$pesanan) {
                    throw new \Exception('Data induk pesanan untuk pembayaran ini tidak ditemukan.');
                }

                $taxPercentage = floatval($pesanan->tax_percentage ?? 0);
                $totalKasBank = floatval($pembayaran->jumlah_bayar);
                $dppCurrent   = round($totalKasBank / (1 + ($taxPercentage / 100)), 2);
                $ppnCurrent   = round($totalKasBank - $dppCurrent, 2);

                $defaultDetails = [
                    ['account_id' => $idKasBank, 'debit' => $totalKasBank, 'kredit' => 0],
                    ['account_id' => $idUangMuka, 'debit' => 0, 'kredit' => $dppCurrent]
                ];

                if ($ppnCurrent > 0) {
                    $defaultDetails[] = ['account_id' => $idPpnKeluaran, 'debit' => 0, 'kredit' => $ppnCurrent];
                }

                $descJurnal = 'Pembukuan penerimaan kas B2B (Uang Muka/Pelunasan) [No. Order: ' . ($pesanan->kode_pesanan ?? '-') . '] [Customer: ' . ($pesanan->customer->nama ?? '-') . ']';
                $noRef = 'JR-B2B-PAY-' . time();
            } else {
                $pengiriman = DB::table('pengiriman')->where('id', $id)->first();
                if (!$pengiriman) {
                    throw new \Exception('Data pengiriman tidak ditemukan.');
                }

                // Mencegah duplikasi posting jurnal B2B pengiriman
                $alreadyPosted = DB::table('jurnal_penjualan_b2b')
                    ->where('source_type', 'pengiriman')
                    ->where('source_id', $id)
                    ->exists();
                if ($alreadyPosted) {
                    DB::rollBack();
                    return true;
                }

                $tanggal = $pengiriman->tanggal_pengiriman ?? $pengiriman->tanggal ?? now()->format('Y-m-d');
                if (\App\Models\Journal::isPeriodClosed($tanggal)) {
                    throw new \Exception('Periode akuntansi tanggal ' . date('d/m/Y', strtotime($tanggal)) . ' sudah ditutup buku.');
                }

                $pesanan = \App\Models\Pesanan::with('customer')->findOrFail($pengiriman->pesanan_id);
                $taxPercentage = floatval($pesanan->tax_percentage ?? 0);
                $totalDppPenjualan = round(floatval($pesanan->total_pesanan) / (1 + ($taxPercentage / 100)), 2);

                $totalHppRiil = DB::table('alokasi_produksi_pesanan')->where('pesanan_id', $pesanan->id)->sum('total_hpp_alokasi') ?? 0;
                if ($totalHppRiil == 0) {
                    $totalHppRiil = round($totalDppPenjualan * 0.75, 2);
                }

                $defaultDetails[] = ['account_id' => $idUangMuka, 'debit' => $totalDppPenjualan, 'kredit' => 0];
                $defaultDetails[] = ['account_id' => $idPendapatan, 'debit' => 0, 'kredit' => $totalDppPenjualan];
                $defaultDetails[] = ['account_id' => $idHPP, 'debit' => $totalHppRiil, 'kredit' => 0];
                $defaultDetails[] = ['account_id' => $idPersediaanJadi, 'debit' => 0, 'kredit' => $totalHppRiil];

                $descJurnal = 'Pengiriman barang & pengakuan omzet penjualan B2B [No. Surat Jalan: ' . $pengiriman->no_pengiriman . '] [Customer: ' . ($pesanan->customer->nama ?? '-') . ']';
                $noRef = $pengiriman->no_pengiriman;
            }

            $jurnalId = DB::table('jurnal_penjualan_b2b')->insertGetId([
                'tanggal'     => $tanggal,
                'no_ref'      => $noRef,
                'deskripsi'   => $descJurnal,
                'source_type' => $type,
                'source_id'   => $id,
                'created_by'  => $creatorId,
            ]);

            foreach ($defaultDetails as $item) {
                if (floatval($item['debit']) == 0 && floatval($item['kredit']) == 0) {
                    continue;
                }

                DB::table('journal_items')->insert([
                    'journal_id'   => $jurnalId,
                    'journal_type' => 'penjualan_b2b',
                    'account_id'   => $item['account_id'],
                    'debit'        => $item['debit'],
                    'kredit'       => $item['kredit'],
                ]);
            }

            DB::commit();
            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Gagal auto-post jurnal B2B: ' . $e->getMessage());
            throw $e;
        }
    }
}