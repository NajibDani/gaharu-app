<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\JournalItem;
use App\Models\ChartOfAccount;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class LaporanController extends Controller
{
    public function neracaSaldo(Request $request)
    {
        // Filter Bulan dan Tahun (Default ke bulan & tahun saat ini jika kosong)
        $bulan = $request->get('bulan', date('m'));
        $tahun = $request->get('tahun', date('Y'));

        // Konversi string rentang tanggal bulan berjalan
        $startOfMonth = "$tahun-$bulan-01";
        $endOfMonth = date('Y-m-t', strtotime($startOfMonth));

        $tableMapping = [
            'jurnal_penjualan_pos' => 'jurnal_penjualan_pos', 
            'penjualan_b2b' => 'jurnal_penjualan_b2b', 
            'jurnal_pembelian'     => 'jurnal_pembelian',     
        ];

        // 1. AMBIL DATA SALDO AWAL BULK (Murni dari journal_type = 'opening')
        $openingBalances = \App\Models\JournalItem::where('journal_type', 'opening')
            ->select('account_id')
            ->selectRaw('SUM(debit) as total_debit, SUM(kredit) as total_kredit')
            ->groupBy('account_id')
            ->get()
            ->keyBy('account_id');

        // 2. AMBIL MUTASI LALU BULK (Transaksi sebelum bulan berjalan, termasuk closing)
        $mutasiLaluBalances = \App\Models\JournalItem::where('journal_type', '!=', 'opening')
            ->where(function ($q) use ($startOfMonth, $tableMapping) {
                // A. Jurnal Umum, Jurnal Manual, & Jurnal Penutup (Closing)
                $q->where(function ($queryManual) use ($startOfMonth) {
                    $queryManual->whereIn('journal_type', ['jurnal_umum', 'jurnal', 'closing'])
                        ->whereHas('journal', function ($j) use ($startOfMonth) {
                            $j->where('tanggal', '<', $startOfMonth)
                                ->where('status', 'approved');
                        });
                });

                // B. Jurnal Penyesuaian (Manual)
                $q->orWhere(function ($queryAjp) use ($startOfMonth) {
                    $queryAjp->whereIn('journal_type', [\App\Models\JurnalPenyesuaian::class, 'jurnal_penyesuaian'])
                        ->whereHas('jurnalPenyesuaianHeader', function ($j) use ($startOfMonth) {
                            $j->where('tanggal', '<', $startOfMonth)
                                ->where('status', 'approved');
                        });
                });

                // C. Jurnal Otomatis (Looping berdasarkan mapping tabel database)
                foreach ($tableMapping as $type => $tableName) {
                    $q->orWhere(function ($queryOtomatis) use ($type, $tableName, $startOfMonth) {
                        $queryOtomatis->where('journal_type', $type)
                            ->whereExists(function ($sub) use ($tableName, $startOfMonth) {
                                $sub->select(\DB::raw(1))
                                    ->from($tableName)
                                    ->whereColumn("$tableName.id", 'journal_items.journal_id')
                                    ->where('tanggal', '<', $startOfMonth);
                            });
                    });
                }
            })
            ->select('account_id')
            ->selectRaw('SUM(debit) as total_debit, SUM(kredit) as total_kredit')
            ->groupBy('account_id')
            ->get()
            ->keyBy('account_id');

        // 3. AMBIL MUTASI PERIODE BULK (Bulan Berjalan)
        $mutasiBalances = \App\Models\JournalItem::where('journal_type', '!=', 'opening')
            ->where(function ($q) use ($startOfMonth, $endOfMonth, $tableMapping) {
                // A. Jurnal Umum (Manual)
                $q->where(function ($queryManual) use ($startOfMonth, $endOfMonth) {
                    $queryManual->whereIn('journal_type', ['jurnal_umum', 'jurnal'])
                        ->whereHas('journal', function ($j) use ($startOfMonth, $endOfMonth) {
                            $j->whereBetween('tanggal', [$startOfMonth, $endOfMonth])
                                ->where('status', 'approved');
                        });
                });

                // B. Jurnal Penyesuaian (Manual)
                $q->orWhere(function ($queryAjp) use ($startOfMonth, $endOfMonth) {
                    $queryAjp->whereIn('journal_type', [\App\Models\JurnalPenyesuaian::class, 'jurnal_penyesuaian'])
                        ->whereHas('jurnalPenyesuaianHeader', function ($j) use ($startOfMonth, $endOfMonth) {
                            $j->whereBetween('tanggal', [$startOfMonth, $endOfMonth])
                                ->where('status', 'approved');
                        });
                });

                // C. Jurnal Otomatis
                foreach ($tableMapping as $type => $tableName) {
                    $q->orWhere(function ($queryOtomatis) use ($type, $tableName, $startOfMonth, $endOfMonth) {
                        $queryOtomatis->where('journal_type', $type)
                            ->whereExists(function ($sub) use ($tableName, $startOfMonth, $endOfMonth) {
                                $sub->select(\DB::raw(1))
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

        // Ambil semua akun COA dari database CV Gaharu Agung Sejahtera
        $neracaSaldo = \App\Models\ChartOfAccount::orderBy('kode', 'asc')
            ->get()
            ->map(function ($coa) use ($openingBalances, $mutasiLaluBalances, $mutasiBalances) {

                $openingRaw = $openingBalances->get($coa->id);
                $openingDebit  = $openingRaw->total_debit ?? 0;
                $openingKredit = $openingRaw->total_kredit ?? 0;

                $mutasiLaluRaw = $mutasiLaluBalances->get($coa->id);
                $mutasiLaluDebit  = $mutasiLaluRaw->total_debit ?? 0;
                $mutasiLaluKredit = $mutasiLaluRaw->total_kredit ?? 0;

                // HITUNG TOTAL SALDO AWAL RIIL
                $totalSaldoAwalDebit  = $openingDebit + $mutasiLaluDebit;
                $totalSaldoAwalKredit = $openingKredit + $mutasiLaluKredit;

                $saldoNormal = strtolower($coa->saldo_normal);

                if ($saldoNormal == 'debit') {
                    $netSaldoAwal = $totalSaldoAwalDebit - $totalSaldoAwalKredit;
                    $coa->saldo_awal_debit  = $netSaldoAwal > 0 ? $netSaldoAwal : 0;
                    $coa->saldo_awal_kredit = $netSaldoAwal < 0 ? abs($netSaldoAwal) : 0;
                } else {
                    $netSaldoAwal = $totalSaldoAwalKredit - $totalSaldoAwalDebit;
                    $coa->saldo_awal_debit  = $netSaldoAwal < 0 ? abs($netSaldoAwal) : 0;
                    $coa->saldo_awal_kredit = $netSaldoAwal > 0 ? $netSaldoAwal : 0;
                }

                // MUTASI PERIODE (Bulan Berjalan)
                $mutasiRaw = $mutasiBalances->get($coa->id);
                $coa->mutasi_debit  = $mutasiRaw->total_debit ?? 0;
                $coa->mutasi_kredit = $mutasiRaw->total_kredit ?? 0;

                // HITUNG SALDO AKHIR
                $totalDebitKeseluruhan  = $totalSaldoAwalDebit + $coa->mutasi_debit;
                $totalKreditKeseluruhan = $totalSaldoAwalKredit + $coa->mutasi_kredit;

                if ($saldoNormal == 'debit') {
                    $netSaldoAkhir = $totalDebitKeseluruhan - $totalKreditKeseluruhan;
                    $coa->debet_akhir  = $netSaldoAkhir >= 0 ? $netSaldoAkhir : 0;
                    $coa->kredit_akhir = $netSaldoAkhir < 0 ? abs($netSaldoAkhir) : 0;
                } else {
                    $netSaldoAkhir = $totalKreditKeseluruhan - $totalDebitKeseluruhan;
                    $coa->debet_akhir  = $netSaldoAkhir < 0 ? abs($netSaldoAkhir) : 0;
                    $coa->kredit_akhir = $netSaldoAkhir >= 0 ? $netSaldoAkhir : 0;
                }

                return $coa;
            });

        if ($request->format === 'pdf') {
            $pdf = app('dompdf.wrapper')->setPaper('a4', 'landscape');
            $pdf->loadView('laporan.neraca-saldo.pdf', compact('neracaSaldo', 'bulan', 'tahun'));
            return $pdf->download('laporan-neraca-saldo-' . now()->format('Ymd') . '.pdf');
        }

        if ($request->format === 'excel') {
            return $this->exportExcelNeracaSaldo($neracaSaldo, $bulan, $tahun);
        }

        return view('laporan.neraca-saldo.index', compact('neracaSaldo', 'bulan', 'tahun'));
    }


    public function labaRugiIndex(Request $request)
    {
        $bulan = $request->get('bulan', date('m'));
        $tahun = $request->get('tahun', date('Y'));

        // 1. Ambil detail Pendapatan (Kredit - Debit)
        $detailsPendapatan = ChartOfAccount::where('tipe', 'Pendapatan')
            ->addSelect([
                'saldo' => \App\Models\JournalItem::selectRaw('COALESCE(SUM(kredit - debit), 0)')
                    ->whereColumn('journal_items.account_id', 'chart_of_accounts.id')
                    ->where('journal_items.journal_type', '!=', 'closing')
                    ->where(function ($q) use ($bulan, $tahun) {

                        // Jurnal Umum (Perlu Approved)
                        $q->whereHas('journal', function ($j) use ($bulan, $tahun) {
                            $j->whereMonth('tanggal', $bulan)
                            ->whereYear('tanggal', $tahun)
                            ->where('status', 'approved');
                        })

                        // Jurnal Penyesuaian (Perlu Approved)
                        ->orWhereHas('jurnalPenyesuaianHeader', function ($j) use ($bulan, $tahun) {
                            $j->whereMonth('tanggal', $bulan)
                            ->whereYear('tanggal', $tahun)
                            ->where('status', 'approved');
                        })

                        // Jurnal Pembelian (Tanpa status approval)
                        ->orWhereHas('jurnalPembelianHeader', function ($j) use ($bulan, $tahun) {
                            $j->whereMonth('tanggal', $bulan)
                            ->whereYear('tanggal', $tahun);
                        })

                        // Jurnal Penjualan B2B (Tanpa status approval)
                        ->orWhereHas('jurnalPenjualanB2bHeader', function ($j) use ($bulan, $tahun) {
                            $j->whereMonth('tanggal', $bulan)
                            ->whereYear('tanggal', $tahun);
                        })

                        // Jurnal Penjualan POS (Tanpa status approval)
                        ->orWhereHas('jurnalPenjualanPosHeader', function ($j) use ($bulan, $tahun) {
                            $j->whereMonth('tanggal', $bulan)
                            ->whereYear('tanggal', $tahun);
                        });
                    })
            ])
            ->get()
            ->filter(fn($coa) => $coa->saldo != 0);

        // 2. Ambil detail Beban (Debit - Kredit)
        $detailsBeban = ChartOfAccount::where('tipe', 'Beban')
            ->addSelect([
                'saldo' => \App\Models\JournalItem::selectRaw('COALESCE(SUM(debit - kredit), 0)')
                    ->whereColumn('journal_items.account_id', 'chart_of_accounts.id')
                    ->where('journal_items.journal_type', '!=', 'closing')
                    ->where(function ($q) use ($bulan, $tahun) {

                        // Jurnal Umum (Perlu Approved)
                        $q->whereHas('journal', function ($j) use ($bulan, $tahun) {
                            $j->whereMonth('tanggal', $bulan)
                            ->whereYear('tanggal', $tahun)
                            ->where('status', 'approved');
                        })

                        // Jurnal Penyesuaian (Perlu Approved)
                        ->orWhereHas('jurnalPenyesuaianHeader', function ($j) use ($bulan, $tahun) {
                            $j->whereMonth('tanggal', $bulan)
                            ->whereYear('tanggal', $tahun)
                            ->where('status', 'approved');
                        })

                        // Jurnal Pembelian (Tanpa status approval)
                        ->orWhereHas('jurnalPembelianHeader', function ($j) use ($bulan, $tahun) {
                            $j->whereMonth('tanggal', $bulan)
                            ->whereYear('tanggal', $tahun);
                        })

                        // Jurnal Penjualan B2B (Tanpa status approval)
                        ->orWhereHas('jurnalPenjualanB2bHeader', function ($j) use ($bulan, $tahun) {
                            $j->whereMonth('tanggal', $bulan)
                            ->whereYear('tanggal', $tahun);
                        })

                        // Jurnal Penjualan POS (Tanpa status approval)
                        ->orWhereHas('jurnalPenjualanPosHeader', function ($j) use ($bulan, $tahun) {
                            $j->whereMonth('tanggal', $bulan)
                            ->whereYear('tanggal', $tahun);
                        });
                    })
            ])
            ->get()
            ->filter(fn($coa) => $coa->saldo != 0);

        $totalPendapatan = $detailsPendapatan->sum('saldo');
        $totalBeban = $detailsBeban->sum('saldo');

        if ($request->format === 'pdf') {
            $pdf = app('dompdf.wrapper')->setPaper('a4', 'landscape');
            $pdf->loadView('laporan.laba-rugi.pdf', compact(
                'detailsPendapatan', 'detailsBeban', 'totalPendapatan', 'totalBeban', 'bulan', 'tahun'
            ));
            return $pdf->download('laporan-laba-rugi-' . now()->format('Ymd') . '.pdf');
        }

        if ($request->format === 'excel') {
            return $this->exportExcelLabaRugi($detailsPendapatan, $detailsBeban, $totalPendapatan, $totalBeban, $bulan, $tahun);
        }

        return view('laporan.laba-rugi.index', compact(
            'detailsPendapatan', 'detailsBeban', 'totalPendapatan', 'totalBeban', 'bulan', 'tahun'
        ));
    }

    public function neracaIndex(Request $request)
    {
    $bulan = $request->get('bulan', date('m'));
    $tahun = $request->get('tahun', date('Y'));

    // 1. Tentukan tanggal batas akhir pelaporan (As of Date)
    $tanggalCutoff = \Carbon\Carbon::createFromDate($tahun, $bulan)->endOfMonth()->toDateString();
    
    // 2. Tentukan tanggal awal tahun fiskal untuk Laba Tahun Berjalan (YTD)
    $awalTahun = \Carbon\Carbon::createFromDate($tahun, 1, 1)->toDateString();

    // Helper filter tanggal akumulatif (Dari awal berdiri s.d. tanggal cutoff)
    $filterTanggalAkumulatif = function ($query) use ($tanggalCutoff) {
        $query->where(function ($q) use ($tanggalCutoff) {
            $q->whereHas('journal', fn($h) => $h->where('tanggal', '<=', $tanggalCutoff))
              ->orWhereHas('jurnalPembelianHeader', fn($h) => $h->where('tanggal', '<=', $tanggalCutoff))
              ->orWhereHas('jurnalPenjualanB2bHeader', fn($h) => $h->where('tanggal', '<=', $tanggalCutoff))
              ->orWhereHas('jurnalPenjualanPosHeader', fn($h) => $h->where('tanggal', '<=', $tanggalCutoff))
              ->orWhereHas('jurnalPenyesuaianHeader', fn($h) => $h->where('tanggal', '<=', $tanggalCutoff));
        });
    };

    // Pull total saldo mutasi akumulatif per account_id
    $itemsAccumulated = JournalItem::where($filterTanggalAkumulatif)
        ->select('account_id')
        ->selectRaw('SUM(debit) as total_debit, SUM(kredit) as total_kredit')
        ->groupBy('account_id')
        ->get()
        ->keyBy('account_id');

    // --- 3. PROSES DATA AKTIVA (ASET) ---
    $allAset = ChartOfAccount::where('tipe', 'Aset')->get()->map(function ($coa) use ($itemsAccumulated) {
        $raw = $itemsAccumulated->get($coa->id);
        $debit = $raw->total_debit ?? 0;
        $kredit = $raw->total_kredit ?? 0;

        if (strtoupper($coa->saldo_normal) === 'KREDIT') {
            $coa->saldo = $kredit - $debit;
        } else {
            $coa->saldo = $debit - $kredit;
        }

        return $coa;
    })->where('saldo', '!=', 0);

    // Pemisahan Aset Lancar vs Aset Tidak Lancar (Aset Tetap / Penyusutan)
    $asetLancar = $allAset->filter(function ($coa) {
        return !str_contains(strtolower($coa->nama), 'tanah') &&
               !str_contains(strtolower($coa->nama), 'gedung') &&
               !str_contains(strtolower($coa->nama), 'mesin') &&
               !str_contains(strtolower($coa->nama), 'kendaraan') &&
               !str_contains(strtolower($coa->nama), 'akumulasi');
    });

    $asetTetap = $allAset->reject(function ($coa) use ($asetLancar) {
        return $asetLancar->contains('id', $coa->id);
    });

    $totalAsetLancar = $asetLancar->sum('saldo');
    $totalAsetTetap = $asetTetap->sum(function ($coa) {
        return strtoupper($coa->saldo_normal) === 'KREDIT' ? -$coa->saldo : $coa->saldo;
    });

    $totalAktiva = $totalAsetLancar + $totalAsetTetap;

    // --- 4. PROSES DATA PASIVA (LIABILITAS & EKUITAS) ---
    $passiva = ChartOfAccount::whereIn('tipe', ['Liabilitas', 'Ekuitas'])
        ->where('nama', 'not like', '%Prive%')
        ->get()
        ->map(function ($coa) use ($itemsAccumulated) {
            $raw = $itemsAccumulated->get($coa->id);
            $debit = $raw->total_debit ?? 0;
            $kredit = $raw->total_kredit ?? 0;

            $coa->saldo = $kredit - $debit;
            return $coa;
        })->where('saldo', '!=', 0);

    // --- 5. HITUNG PRIVE AKUMULATIF ---
    $akunPrive = ChartOfAccount::where('nama', 'like', '%Prive%')->first();
    $totalPrive = 0;

    if ($akunPrive) {
        $rawPrive = $itemsAccumulated->get($akunPrive->id);
        $debitPrive = $rawPrive->total_debit ?? 0;
        $kreditPrive = $rawPrive->total_kredit ?? 0;
        $totalPrive = $debitPrive - $kreditPrive;
    }

    // --- 6. HITUNG LABA TAHUN BERJALAN (YTD: 1 JAN S.D. CUTOFF) ---
    $filterTanggalYTD = function ($query) use ($awalTahun, $tanggalCutoff) {
        $query->where(function ($q) use ($awalTahun, $tanggalCutoff) {
            $q->whereHas('journal', fn($h) => $h->whereBetween('tanggal', [$awalTahun, $tanggalCutoff]))
              ->orWhereHas('jurnalPembelianHeader', fn($h) => $h->whereBetween('tanggal', [$awalTahun, $tanggalCutoff]))
              ->orWhereHas('jurnalPenjualanB2bHeader', fn($h) => $h->whereBetween('tanggal', [$awalTahun, $tanggalCutoff]))
              ->orWhereHas('jurnalPenjualanPosHeader', fn($h) => $h->whereBetween('tanggal', [$awalTahun, $tanggalCutoff]))
              ->orWhereHas('jurnalPenyesuaianHeader', fn($h) => $h->whereBetween('tanggal', [$awalTahun, $tanggalCutoff]));
        });
    };

    $totalPendapatan = JournalItem::whereHas('coa', fn($q) => $q->where('tipe', 'Pendapatan'))
        ->where($filterTanggalYTD)
        ->sum(\Illuminate\Support\Facades\DB::raw('kredit - debit'));

    $totalBeban = JournalItem::whereHas('coa', fn($q) => $q->where('tipe', 'Beban'))
        ->where($filterTanggalYTD)
        ->sum(\Illuminate\Support\Facades\DB::raw('debit - kredit'));

    $labaBerjalan = $totalPendapatan - $totalBeban;

    // Total Kalkulasi Modal Akhir & Passiva
    $totalKewajiban = $passiva->where('tipe', 'Liabilitas')->sum('saldo');
    $totalModalAwal = $passiva->where('tipe', 'Ekuitas')->sum('saldo');
    
    $modalAkhir = $totalModalAwal + $labaBerjalan - $totalPrive;
    $totalPassiva = $totalKewajiban + $modalAkhir;

    // Export Excel
    if ($request->format === 'excel') {
        $aktiva = $asetLancar->merge($asetTetap);
        return $this->exportExcelNeraca(
            $aktiva, $passiva, $labaBerjalan, $totalPrive, $modalAkhir, $bulan, $tahun
        );
    }

    // Export PDF
    if ($request->format === 'pdf') {
        $pdf = app('dompdf.wrapper')->setPaper('a4', 'landscape');
        $aktiva = $asetLancar->merge($asetTetap);
        $pdf->loadView('laporan.neraca.pdf', compact(
            'aktiva', 'asetLancar', 'asetTetap', 'totalAsetLancar', 'totalAsetTetap', 'totalAktiva',
            'passiva', 'totalKewajiban', 'labaBerjalan', 'totalPrive', 'modalAkhir', 'totalPassiva',
            'bulan', 'tahun', 'tanggalCutoff'
        ));
        return $pdf->download('laporan-neraca-' . now()->format('Ymd') . '.pdf');
    }

    // Mengirim variabel yang dibutuhkan oleh index.blade.php
    return view('laporan.neraca.index', compact(
        'asetLancar', 'asetTetap', 'totalAsetLancar', 'totalAsetTetap', 'totalAktiva',
        'passiva', 'totalKewajiban', 'labaBerjalan', 'totalPrive', 'modalAkhir', 'totalPassiva',
        'bulan', 'tahun', 'tanggalCutoff'
    ));
    }

    public function arusKasIndex(Request $request)
    {
        $bulan = $request->get('bulan', date('m'));
        $tahun = $request->get('tahun', date('Y'));

        // 1. Ambil kalkulasi data arus kas metode langsung
        $data = $this->getArusKasDirectData($bulan, $tahun);

        // 2. Export PDF
        if ($request->format === 'pdf') {
            $pdf = app('dompdf.wrapper')->setPaper('a4', 'portrait');
            return $pdf->loadView('laporan.arus-kas.pdf', $data)
                       ->download('laporan-arus-kas-' . $tahun . $bulan . '.pdf');
        }

        // 3. Export Excel
        if ($request->format === 'excel') {
            return $this->exportExcelArusKas($data, $bulan, $tahun);
        }

        // 4. Return View Web
        return view('laporan.arus-kas.index', $data);
    }

    // =========================================================================
    // PRIVATE CALCULATION METHODS
    // =========================================================================

    /**
     * Engine Kalkulasi Arus Kas Metode Langsung (Sesuai Dokumen 2 & Buku Besar)
     */
    private function getArusKasDirectData($bulan, $tahun)
{
    $namaBulan = Carbon::createFromDate($tahun, $bulan, 1)->locale('id')->translatedFormat('F');

    // 1. ID Akun Kas & Bank (1101 Kas di Bank BRI)
    $kasBankCoaIds = ChartOfAccount::where('kode', '1101')
        ->orWhere('nama', 'LIKE', '%Kas di Bank BRI%')
        ->orWhere(function ($q) {
            $q->where('nama', 'LIKE', '%kas%')->orWhere('nama', 'LIKE', '%bank%');
        })
        ->pluck('id')
        ->toArray();

    // 2. Transaksi Kas/Bank Periode Ini
    $kasJournalItemsQuery = JournalItem::whereIn('account_id', $kasBankCoaIds);
    $kasJournalItems = $this->applyFilterPeriodeArusKas($kasJournalItemsQuery, $bulan, $tahun)->get();

    $penerimaanPelangganRaw  = collect();
    $pengeluaranBahanBakuRaw = collect();
    $pengeluaranBebanOpRaw   = collect();

    $investasi = collect();
    $pendanaan = collect();

    foreach ($kasJournalItems as $itemKas) {
        $debit  = $itemKas->debit;
        $kredit = $itemKas->kredit;

        if ($debit == 0 && $kredit == 0) continue;

        // Ambil header jurnal
        $header = $this->getHeaderJurnal($itemKas);
        $deskripsi = $header->deskripsi ?? 'Transaksi Kas';

        // =========================================================================
        // A. PENDAPATAN / KAS MASUK (Debit Kas)
        // =========================================================================
        if ($debit > 0) {
            // Cek apakah ada akun Pendapatan di lawan jurnalnya
            $coaPenjualan = JournalItem::where('journal_id', $itemKas->journal_id)
                ->where('journal_type', $itemKas->journal_type)
                ->whereHas('coa', fn($q) => $q->where('tipe', 'Pendapatan')->orWhere('kode', 'LIKE', '4%'))
                ->with('coa')
                ->first();

            if ($coaPenjualan && $coaPenjualan->coa) {
                $namaCoa = strtolower($coaPenjualan->coa->nama);
                if (str_contains($namaCoa, 'kejingga')) {
                    $kategori = 'Penerimaan Penjualan Kasir POS Kejingga & PPN Keluaran';
                } elseif (str_contains($namaCoa, 'gaharu')) {
                    $kategori = 'Penerimaan Penjualan Kasir POS Gaharu & PPN Keluaran';
                } else {
                    // Jika ada akun penjualan lain (misal Penjualan B2B / Outlet baru)
                    $kategori = 'Penerimaan ' . $coaPenjualan->coa->nama;
                }
            } else {
                // Fallback berdasarkan deskripsi atau tipe jurnal
                $descLower = strtolower($deskripsi);
                if (str_contains($descLower, 'kejingga')) {
                    $kategori = 'Penerimaan Penjualan Kasir POS Kejingga & PPN Keluaran';
                } elseif (str_contains($descLower, 'gaharu')) {
                    $kategori = 'Penerimaan Penjualan Kasir POS Gaharu & PPN Keluaran';
                } elseif ($itemKas->journal_type === 'penjualan_b2b' || str_contains($descLower, 'b2b')) {
                    $kategori = 'Penerimaan Penjualan B2B';
                } else {
                    // Menangkap seluruh transaksi kas masuk lainnya agar tidak ada yang terlewat!
                    $kategori = 'Penerimaan Kas Lainnya (' . $deskripsi . ')';
                }
            }

            $penerimaanPelangganRaw->push([
                'kategori' => $kategori,
                'nominal'  => $debit,
            ]);
        }

        // =========================================================================
        // B. PENGELUARAN / KAS KELUAR (Kredit Kas)
        // =========================================================================
        if ($kredit > 0) {
            $descLower = strtolower($deskripsi);

            // 1. Pengeluaran Pembelian Bahan Baku / Supplier
            if ($itemKas->journal_type === 'jurnal_pembelian' || str_contains($descLower, 'pembelian') || str_contains($descLower, 'supplier')) {
                
                // Cari nama supplier dari transaksi jika ada
                if (str_contains($descLower, 'tofico')) {
                    $kategori = 'Pembayaran Pembelian Bahan Baku (Supplier TOFICO)';
                } else {
                    // Dinamis mengambil deskripsi pembelian supplier lainnya
                    $kategori = 'Pembayaran ' . $deskripsi;
                }

                $pengeluaranBahanBakuRaw->push([
                    'kategori' => $kategori,
                    'nominal'  => $kredit * -1,
                ]);

            } else {
                // 2. Pengeluaran Beban Operasional Lainnya
                if (str_contains($descLower, 'listrik') || str_contains($descLower, 'air') || str_contains($descLower, 'internet')) {
                    $kategori = 'Pembayaran Beban Listrik, Air, & Internet';
                } else {
                    // Dinamis menangkap beban operasional lainnya (misal Beban Gaji, Beban Sewa, dll)
                    $kategori = $deskripsi;
                }

                $pengeluaranBebanOpRaw->push([
                    'kategori' => $kategori,
                    'nominal'  => $kredit * -1,
                ]);
            }
        }
    }

    // GROUPING HASIL BERDASARKAN KATEGORI
    $penerimaanPelanggan = $penerimaanPelangganRaw->groupBy('kategori')->map(function ($items, $kat) {
        return ['keterangan' => $kat, 'nominal' => $items->sum('nominal')];
    })->values();

    $pengeluaranBahanBaku = $pengeluaranBahanBakuRaw->groupBy('kategori')->map(function ($items, $kat) {
        return ['keterangan' => $kat, 'nominal' => $items->sum('nominal')];
    })->values();

    $pengeluaranBebanOp = $pengeluaranBebanOpRaw->groupBy('kategori')->map(function ($items, $kat) {
        return ['keterangan' => $kat, 'nominal' => $items->sum('nominal')];
    })->values();

    // Subtotal Operasional
    $totalPenerimaanPelanggan  = $penerimaanPelanggan->sum('nominal');
    $totalPengeluaranBahanBaku = $pengeluaranBahanBaku->sum('nominal');
    $totalPengeluaranBebanOp   = $pengeluaranBebanOp->sum('nominal');

    $kasBersihOperasional = $totalPenerimaanPelanggan + $totalPengeluaranBahanBaku + $totalPengeluaranBebanOp;
    $kasBersihInvestasi   = $investasi->sum('nominal');
    $kasBersihPendanaan   = $pendanaan->sum('nominal');

    // Rekonsiliasi Saldo Kas Awal & Akhir
    $saldoAwalKas = $this->getSaldoAkumulasiKasToDate($kasBankCoaIds, $bulan - 1, $tahun);
    $kenaikanPenurunanKas = $kasBersihOperasional + $kasBersihInvestasi + $kasBersihPendanaan;
    $saldoAkhirKas = $saldoAwalKas + $kenaikanPenurunanKas;

    return [
        'penerimaanPelanggan'       => $penerimaanPelanggan,
        'totalPenerimaanPelanggan'  => $totalPenerimaanPelanggan,
        'pengeluaranBahanBaku'      => $pengeluaranBahanBaku,
        'totalPengeluaranBahanBaku' => $totalPengeluaranBahanBaku,
        'pengeluaranBebanOp'        => $pengeluaranBebanOp,
        'totalPengeluaranBebanOp'   => $totalPengeluaranBebanOp,
        'kasBersihOperasional'      => $kasBersihOperasional,
        
        'investasi'                 => $investasi,
        'kasBersihInvestasi'        => $kasBersihInvestasi,
        
        'pendanaan'                 => $pendanaan,
        'kasBersihPendanaan'        => $kasBersihPendanaan,
        
        'saldoAwalKas'              => $saldoAwalKas,
        'kenaikanPenurunanKas'      => $kenaikanPenurunanKas,
        'saldoAkhirKas'             => $saldoAkhirKas,
        'bulan'                     => $bulan,
        'tahun'                     => $tahun,
        'namaBulan'                 => $namaBulan
    ];
}

    // =========================================================================
    // HELPER FUNCTIONS
    // =========================================================================

    private function getHeaderJurnal($itemKas)
    {
        switch ($itemKas->journal_type) {
            case 'jurnal_pembelian':
                return $itemKas->jurnalPembelianHeader;
            case 'penjualan_b2b':
                return $itemKas->jurnalPenjualanB2bHeader;
            case 'jurnal_penjualan_pos':
                return $itemKas->jurnalPenjualanPosHeader;
            case 'jurnal_penyesuaian':
            case \App\Models\JurnalPenyesuaian::class:
                return $itemKas->jurnalPenyesuaianHeader;
            default:
                return $itemKas->journal;
        }
    }

    private function applyFilterPeriodeArusKas($query, $bulan, $tahun)
    {
        return $query->where(function ($q) use ($bulan, $tahun) {
            $q->where(function ($sub) use ($bulan, $tahun) {
                $sub->whereIn('journal_type', ['jurnal_umum', 'jurnal'])
                    ->whereHas('journal', fn($j) => $j->whereMonth('tanggal', $bulan)->whereYear('tanggal', $tahun)->where('status', 'approved'));
            })
            ->orWhere(function ($sub) use ($bulan, $tahun) {
                $sub->where('journal_type', 'jurnal_pembelian')
                    ->whereHas('jurnalPembelianHeader', fn($j) => $j->whereMonth('tanggal', $bulan)->whereYear('tanggal', $tahun));
            })
            ->orWhere(function ($sub) use ($bulan, $tahun) {
                $sub->where('journal_type', 'penjualan_b2b')
                    ->whereHas('jurnalPenjualanB2bHeader', fn($j) => $j->whereMonth('tanggal', $bulan)->whereYear('tanggal', $tahun));
            })
            ->orWhere(function ($sub) use ($bulan, $tahun) {
                $sub->where('journal_type', 'jurnal_penjualan_pos')
                    ->whereHas('jurnalPenjualanPosHeader', fn($j) => $j->whereMonth('tanggal', $bulan)->whereYear('tanggal', $tahun));
            })
            ->orWhere(function ($sub) use ($bulan, $tahun) {
                $sub->whereIn('journal_type', [\App\Models\JurnalPenyesuaian::class, 'jurnal_penyesuaian'])
                    ->whereHas('jurnalPenyesuaianHeader', fn($j) => $j->whereMonth('tanggal', $bulan)->whereYear('tanggal', $tahun)->where('status', 'approved'));
            });
        });
    }

    private function getSaldoAkumulasiKasToDate($kasBankCoaIds, $bulanTarget, $tahunTarget)
    {
        // Pastikan bulanTarget berbentuk integer
        $bulanTarget = (int) $bulanTarget;
        $tahunTarget = (int) $tahunTarget;

        // Jika bulan target adalah 0 (pilihan Januari), maka mundur ke Desember tahun sebelumnya
        if ($bulanTarget <= 0) {
            $bulanTarget = 12;
            $tahunTarget = $tahunTarget - 1;
        }

        // Buat tanggal batas akhir bulan sebelumnya secara presisi (contoh: 2026-07-31)
        $tanggalBatas = Carbon::createFromDate($tahunTarget, $bulanTarget, 1)->endOfMonth()->format('Y-m-d');

        // 1. Ambil nilai dari Jurnal Saldo Awal Master (journal_type = 'opening')
        $saldoMasterOpening = JournalItem::whereIn('account_id', $kasBankCoaIds)
            ->where('journal_type', 'opening')
            ->selectRaw('SUM(debit) - SUM(kredit) as total')
            ->value('total') ?? 0;

        // 2. Ambil akumulasi mutasi transaksi regular sebelum atau sama dengan tanggal batas (<= $tanggalBatas)
        $queryMutasiHistoris = JournalItem::whereIn('account_id', $kasBankCoaIds)
            ->where('journal_type', '!=', 'opening')
            ->where(function ($q) use ($tanggalBatas) {
                $q->whereHas('journal', fn($j) => $j->whereDate('tanggal', '<=', $tanggalBatas))
                ->orWhereHas('jurnalPembelianHeader', fn($j) => $j->whereDate('tanggal', '<=', $tanggalBatas))
                ->orWhereHas('jurnalPenjualanB2bHeader', fn($j) => $j->whereDate('tanggal', '<=', $tanggalBatas))
                ->orWhereHas('jurnalPenjualanPosHeader', fn($j) => $j->whereDate('tanggal', '<=', $tanggalBatas))
                ->orWhereHas('jurnalPenyesuaianHeader', fn($j) => $j->whereDate('tanggal', '<=', $tanggalBatas));
            });

        $debitHistoris  = $queryMutasiHistoris->sum('debit');
        $kreditHistoris = $queryMutasiHistoris->sum('kredit');

        $mutasiHistoris = $debitHistoris - $kreditHistoris;

        return $saldoMasterOpening + $mutasiHistoris;
    }

    public function bukuBesar(Request $request)
    {
        $bulan = $request->get('bulan', date('m'));
        $tahun = $request->get('tahun', date('Y'));
        $firstDayOfMonth = "$tahun-$bulan-01";

        // 1. HITUNG SALDO AWAL (Murni mengambil opening + seluruh mutasi & closing sebelum tanggal 1 bulan ini)
        $beginningBalances = DB::table('journal_items')
            ->leftJoin('journals', function ($join) {
                $join->on('journal_items.journal_id', '=', 'journals.id')
                     ->whereIn('journal_items.journal_type', ['jurnal_umum', 'jurnal', 'closing']);
            })
            ->leftJoin('jurnal_pembelian', function ($join) {
                $join->on('journal_items.journal_id', '=', 'jurnal_pembelian.id')
                     ->where('journal_items.journal_type', '=', 'jurnal_pembelian');
            })
            ->leftJoin('jurnal_penjualan_pos', function ($join) {
                $join->on('journal_items.journal_id', '=', 'jurnal_penjualan_pos.id')
                     ->where('journal_items.journal_type', '=', 'jurnal_penjualan_pos');
            })
            ->leftJoin('jurnal_penjualan_b2b', function ($join) {
                $join->on('journal_items.journal_id', '=', 'jurnal_penjualan_b2b.id')
                     ->where('journal_items.journal_type', '=', 'penjualan_b2b');
            })
            ->leftJoin('jurnal_penyesuaian', function ($join) {
                $join->on('journal_items.journal_id', '=', 'jurnal_penyesuaian.id')
                     ->whereIn('journal_items.journal_type', [\App\Models\JurnalPenyesuaian::class, 'jurnal_penyesuaian']);
            })
            ->select('journal_items.account_id')
            ->selectRaw('SUM(journal_items.debit) as total_debit, SUM(journal_items.kredit) as total_kredit')
            ->where(function ($q) use ($firstDayOfMonth) {
                $q->where('journal_items.journal_type', 'opening')
                  ->orWhere(function ($sub) use ($firstDayOfMonth) {
                      $sub->whereRaw("COALESCE(journals.tanggal, jurnal_pembelian.tanggal, jurnal_penjualan_pos.tanggal, jurnal_penjualan_b2b.tanggal, jurnal_penyesuaian.tanggal) < ?", [$firstDayOfMonth]);
                  });
            })
            ->groupBy('journal_items.account_id')
            ->get()
            ->keyBy('account_id');

        // 2. TARIK MUTASI BERJALAN (Abaikan 'opening' di bulan berjalan)
        $mutasiItems = DB::table('journal_items')
            ->leftJoin('journals', function ($join) {
                $join->on('journal_items.journal_id', '=', 'journals.id')
                     ->whereIn('journal_items.journal_type', ['jurnal_umum', 'jurnal', 'closing']);
            })
            ->leftJoin('jurnal_pembelian', function ($join) {
                $join->on('journal_items.journal_id', '=', 'jurnal_pembelian.id')
                     ->where('journal_items.journal_type', '=', 'jurnal_pembelian');
            })
            ->leftJoin('jurnal_penjualan_pos', function ($join) {
                $join->on('journal_items.journal_id', '=', 'jurnal_penjualan_pos.id')
                     ->where('journal_items.journal_type', '=', 'jurnal_penjualan_pos');
            })
            ->leftJoin('jurnal_penjualan_b2b', function ($join) {
                $join->on('journal_items.journal_id', '=', 'jurnal_penjualan_b2b.id')
                     ->where('journal_items.journal_type', '=', 'penjualan_b2b');
            })
            ->leftJoin('jurnal_penyesuaian', function ($join) {
                $join->on('journal_items.journal_id', '=', 'jurnal_penyesuaian.id')
                     ->whereIn('journal_items.journal_type', [\App\Models\JurnalPenyesuaian::class, 'jurnal_penyesuaian']);
            })
            ->select('journal_items.*')
            ->selectRaw("COALESCE(journals.tanggal, jurnal_pembelian.tanggal, jurnal_penjualan_pos.tanggal, jurnal_penjualan_b2b.tanggal, jurnal_penyesuaian.tanggal) as tanggal")
            ->selectRaw("COALESCE(journals.deskripsi, jurnal_pembelian.deskripsi, jurnal_penjualan_pos.deskripsi, jurnal_penjualan_b2b.deskripsi, jurnal_penyesuaian.deskripsi) as deskripsi")
            ->selectRaw("COALESCE(journals.no_ref, jurnal_pembelian.no_ref, jurnal_penjualan_pos.no_ref, jurnal_penjualan_b2b.no_ref, jurnal_penyesuaian.no_ref) as no_ref")
            ->whereNotIn('journal_items.journal_type', ['opening'])
            ->whereRaw('MONTH(COALESCE(journals.tanggal, jurnal_pembelian.tanggal, jurnal_penjualan_pos.tanggal, jurnal_penjualan_b2b.tanggal, jurnal_penyesuaian.tanggal)) = ?', [(int)$bulan])
            ->whereRaw('YEAR(COALESCE(journals.tanggal, jurnal_pembelian.tanggal, jurnal_penjualan_pos.tanggal, jurnal_penjualan_b2b.tanggal, jurnal_penyesuaian.tanggal)) = ?', [(int)$tahun])
            ->orderBy('tanggal', 'asc')
            ->get()
            ->groupBy('account_id');

        // 3. MAP DATA KE MODEL COA
        $accountsData = ChartOfAccount::all()
            ->map(function ($coa) use ($beginningBalances, $mutasiItems) {
                $balanceData = $beginningBalances->get($coa->id);
                $initialDebit = $balanceData ? $balanceData->total_debit : 0;
                $initialKredit = $balanceData ? $balanceData->total_kredit : 0;

                $saldoNormal = strtolower($coa->saldo_normal);
                if ($saldoNormal === 'kredit') {
                    $coa->beginning_balance = $initialKredit - $initialDebit;
                } else {
                    $coa->beginning_balance = $initialDebit - $initialKredit;
                }

                $coa->items = $mutasiItems->get($coa->id, collect());

                return $coa;
            })
            ->filter(function ($coa) {
                return $coa->items->count() > 0 || $coa->beginning_balance != 0;
            });

        if ($request->format === 'pdf') {
            $pdf = app('dompdf.wrapper')->setPaper('a4', 'landscape');
            $pdf->loadView('laporan.buku-besar.pdf', compact('accountsData', 'bulan', 'tahun'));
            return $pdf->download('laporan-buku-besar-' . now()->format('Ymd') . '.pdf');
        }

        if ($request->format === 'excel') {
            return $this->exportExcelBukuBesar($accountsData, $bulan, $tahun);
        }

        return view('laporan.buku-besar.index', compact('accountsData', 'bulan', 'tahun'));
    }

    private function exportExcelNeracaSaldo($data, $bulan, $tahun)
    {
        $filename = 'laporan-neraca-saldo-' . now()->format('Ymd') . '.csv';
        $headers  = [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"$filename\"",
        ];

        $callback = function () use ($data, $bulan, $tahun) {
            $f = fopen('php://output', 'w');
            fprintf($f, chr(0xEF) . chr(0xBB) . chr(0xBF));
            fputcsv($f, ['CV GAHARU AGUNG SEJAHTERA']);
            fputcsv($f, ['LAPORAN NERACA SALDO']);
            fputcsv($f, ["Periode: " . date('F', mktime(0,0,0,$bulan,1)) . " " . $tahun]);
            fputcsv($f, []);
            fputcsv($f, ['Kode Akun', 'Nama Akun', 'Saldo Awal Debit', 'Saldo Awal Kredit', 'Mutasi Debit', 'Mutasi Kredit', 'Saldo Akhir Debit', 'Saldo Akhir Kredit']);
            foreach ($data as $row) {
                fputcsv($f, [
                    $row->kode,
                    $row->nama,
                    $row->saldo_awal_debit,
                    $row->saldo_awal_kredit,
                    $row->mutasi_debit,
                    $row->mutasi_kredit,
                    $row->debet_akhir,
                    $row->kredit_akhir,
                ]);
            }
            fclose($f);
        };

        return response()->stream($callback, 200, $headers);
    }

    private function exportExcelLabaRugi($detailsPendapatan, $detailsBeban, $totalPendapatan, $totalBeban, $bulan, $tahun)
    {
        $filename = 'laporan-laba-rugi-' . now()->format('Ymd') . '.csv';
        $headers  = [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"$filename\"",
        ];

        $callback = function () use ($detailsPendapatan, $detailsBeban, $totalPendapatan, $totalBeban, $bulan, $tahun) {
            $f = fopen('php://output', 'w');
            fprintf($f, chr(0xEF) . chr(0xBB) . chr(0xBF));
            fputcsv($f, ['CV GAHARU AGUNG SEJAHTERA']);
            fputcsv($f, ['LAPORAN LABA RUGI']);
            fputcsv($f, ["Periode: " . date('F', mktime(0,0,0,$bulan,1)) . " " . $tahun]);
            fputcsv($f, []);
            
            fputcsv($f, ['PENDAPATAN']);
            foreach ($detailsPendapatan as $row) {
                fputcsv($f, [$row->kode, $row->nama, $row->saldo]);
            }
            fputcsv($f, ['TOTAL PENDAPATAN', '', $totalPendapatan]);
            fputcsv($f, []);

            fputcsv($f, ['BEBAN']);
            foreach ($detailsBeban as $row) {
                fputcsv($f, [$row->kode, $row->nama, $row->saldo]);
            }
            fputcsv($f, ['TOTAL BEBAN', '', $totalBeban]);
            fputcsv($f, []);

            fputcsv($f, ['LABA / RUGI BERSIH', '', $totalPendapatan - $totalBeban]);
            fclose($f);
        };

        return response()->stream($callback, 200, $headers);
    }

    private function exportExcelNeraca($aktiva, $passiva, $labaBerjalan, $totalPrive, $modalAkhir, $bulan, $tahun)
    {
        $filename = 'laporan-neraca-' . now()->format('Ymd') . '.csv';
        $headers  = [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"$filename\"",
        ];

        $callback = function () use ($aktiva, $passiva, $labaBerjalan, $totalPrive, $modalAkhir, $bulan, $tahun) {
            $f = fopen('php://output', 'w');
            fprintf($f, chr(0xEF) . chr(0xBB) . chr(0xBF));
            fputcsv($f, ['CV GAHARU AGUNG SEJAHTERA']);
            fputcsv($f, ['LAPORAN NERACA']);
            fputcsv($f, ["Periode: " . date('F', mktime(0,0,0,$bulan,1)) . " " . $tahun]);
            fputcsv($f, []);

            fputcsv($f, ['AKTIVA', '', '', 'PASSIVA']);
            
            fputcsv($f, ['--- AKTIVA ---']);
            foreach ($aktiva as $row) {
                fputcsv($f, [$row->kode, $row->nama, $row->saldo]);
            }
            fputcsv($f, ['TOTAL AKTIVA', '', $aktiva->sum('saldo')]);
            fputcsv($f, []);

            fputcsv($f, ['--- PASSIVA ---']);
            fputcsv($f, ['Kewajiban (Liabilitas)']);
            $totalKewajiban = 0;
            foreach ($passiva->where('tipe', 'Liabilitas') as $row) {
                $totalKewajiban += $row->saldo;
                fputcsv($f, [$row->kode, $row->nama, $row->saldo]);
            }
            fputcsv($f, ['Total Kewajiban', '', $totalKewajiban]);
            fputcsv($f, []);

            fputcsv($f, ['Ekuitas (Modal)']);
            $totalEkuitasTabel = 0;
            foreach ($passiva->where('tipe', 'Ekuitas') as $row) {
                $totalEkuitasTabel += $row->saldo;
                fputcsv($f, [$row->kode, $row->nama, $row->saldo]);
            }
            fputcsv($f, ['Laba Tahun Berjalan', '', $labaBerjalan]);
            if ($totalPrive != 0) {
                fputcsv($f, ['Prive', '', -$totalPrive]);
            }
            fputcsv($f, ['Total Ekuitas', '', $modalAkhir]);
            fputcsv($f, []);

            fputcsv($f, ['TOTAL PASSIVA', '', $totalKewajiban + $modalAkhir]);
            fclose($f);
        };

        return response()->stream($callback, 200, $headers);
    }

    private function exportExcelArusKas($data, $bulan, $tahun)
    {
        $filename = 'laporan-arus-kas-' . now()->format('Ymd') . '.csv';
        $headers  = [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"$filename\"",
        ];

        $callback = function () use ($data, $bulan, $tahun) {
            $f = fopen('php://output', 'w');
            fprintf($f, chr(0xEF) . chr(0xBB) . chr(0xBF)); // UTF-8 BOM
            fputcsv($f, ['CV GAHARU AGUNG SEJAHTERA']);
            fputcsv($f, ['LAPORAN ARUS KAS (METODE LANGSUNG)']);
            fputcsv($f, ["Periode: " . date('F', mktime(0, 0, 0, $bulan, 1)) . " " . $tahun]);
            fputcsv($f, []);

            // 1. Aktivitas Operasional
            fputcsv($f, ['ARUS KAS DARI AKTIVITAS OPERASIONAL']);
            fputcsv($f, ['Penerimaan Kas dari Pelanggan:']);
            foreach ($data['penerimaanPelanggan'] as $item) {
                fputcsv($f, ['', $item['keterangan'], $item['nominal']]);
            }
            if (count($data['penerimaanPelanggan']) == 0) {
                fputcsv($f, ['', '- Tidak ada penerimaan kas dari pelanggan -', 0]);
            }

            fputcsv($f, ['Pengeluaran Kas untuk Operasional:']);
            foreach ($data['pengeluaranBahanBaku'] as $item) {
                fputcsv($f, ['', $item['keterangan'], $item['nominal']]);
            }
            foreach ($data['pengeluaranBebanOp'] as $item) {
                fputcsv($f, ['', $item['keterangan'], $item['nominal']]);
            }
            if (count($data['pengeluaranBahanBaku']) == 0 && count($data['pengeluaranBebanOp']) == 0) {
                fputcsv($f, ['', '- Tidak ada pengeluaran kas operasional -', 0]);
            }

            fputcsv($f, ['Arus Kas Bersih Dari Aktivitas Operasional', '', $data['kasBersihOperasional']]);
            fputcsv($f, []);

            // 2. Aktivitas Investasi
            fputcsv($f, ['ARUS KAS DARI AKTIVITAS INVESTASI']);
            foreach ($data['investasi'] as $item) {
                fputcsv($f, ['', $item['keterangan'], $item['nominal']]);
            }
            if (count($data['investasi']) == 0) {
                fputcsv($f, ['', '- Tidak ada transaksi kas dari aktivitas investasi -', 0]);
            }
            fputcsv($f, ['Arus Kas Bersih Dari Aktivitas Investasi', '', $data['kasBersihInvestasi']]);
            fputcsv($f, []);

            // 3. Aktivitas Pendanaan
            fputcsv($f, ['ARUS KAS DARI AKTIVITAS PENDANAAN']);
            foreach ($data['pendanaan'] as $item) {
                fputcsv($f, ['', $item['keterangan'], $item['nominal']]);
            }
            if (count($data['pendanaan']) == 0) {
                fputcsv($f, ['', '- Tidak ada transaksi kas dari aktivitas pendanaan -', 0]);
            }
            fputcsv($f, ['Arus Kas Bersih Dari Aktivitas Pendanaan', '', $data['kasBersihPendanaan']]);
            fputcsv($f, []);

            // 4. Rekonsiliasi
            fputcsv($f, ['REKONSILIASI KAS DAN BANK']);
            fputcsv($f, ['KENAIKAN (PENURUNAN) BERSIH KAS DAN BANK', '', $data['kenaikanPenurunanKas']]);
            fputcsv($f, ['KAS DAN BANK AWAL PERIODE', '', $data['saldoAwalKas']]);
            fputcsv($f, ['KAS DAN BANK AKHIR PERIODE', '', $data['saldoAkhirKas']]);

            fclose($f);
        };

        return response()->stream($callback, 200, $headers);
    }

    private function exportExcelBukuBesar($accountsData, $bulan, $tahun)
    {
        $filename = 'laporan-buku-besar-' . now()->format('Ymd') . '.csv';
        $headers  = [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"$filename\"",
        ];

        $callback = function () use ($accountsData, $bulan, $tahun) {
            $f = fopen('php://output', 'w');
            fprintf($f, chr(0xEF) . chr(0xBB) . chr(0xBF));
            fputcsv($f, ['CV GAHARU AGUNG SEJAHTERA']);
            fputcsv($f, ['LAPORAN BUKU BESAR']);
            fputcsv($f, ["Periode: " . date('F', mktime(0,0,0,$bulan,1)) . " " . $tahun]);
            fputcsv($f, []);

            foreach ($accountsData as $coa) {
                fputcsv($f, ['Akun:', $coa->kode . ' - ' . $coa->nama]);
                fputcsv($f, ['Saldo Awal:', $coa->beginning_balance]);
                fputcsv($f, ['Tanggal', 'Deskripsi / Keterangan', 'No. Ref', 'Debit', 'Kredit', 'Saldo']);
                
                $runningBalance = $coa->beginning_balance;
                $saldoNormal = strtolower($coa->saldo_normal);

                foreach ($coa->items as $item) {
                    if ($saldoNormal === 'kredit') {
                        $runningBalance += ($item->kredit - $item->debit);
                    } else {
                        $runningBalance += ($item->debit - $item->kredit);
                    }
                    fputcsv($f, [
                        \Carbon\Carbon::parse($item->tanggal)->format('d-m-Y'),
                        $item->deskripsi ?? '-',
                        $item->no_ref ?? '-',
                        $item->debit,
                        $item->kredit,
                        $runningBalance
                    ]);
                }
                fputcsv($f, []);
            }
            fclose($f);
        };

        return response()->stream($callback, 200, $headers);
    }
}
