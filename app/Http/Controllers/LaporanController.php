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

        $tableMapping = [
            'jurnal_penjualan_pos' => 'jurnal_penjualan_pos', 
            'penjualan_b2b'        => 'jurnal_penjualan_b2b', 
            'jurnal_pembelian'     => 'jurnal_pembelian',     
        ];

        // Helper Closure untuk subquery saldo umum (berdasarkan COA) dengan checking journal_type
        $getSaldoSubquery = function ($formula = 'COALESCE(SUM(debit - kredit), 0)') use ($bulan, $tahun, $tableMapping) {
            return \App\Models\JournalItem::selectRaw($formula)
                ->whereColumn('journal_items.account_id', 'chart_of_accounts.id')
                ->where('journal_items.journal_type', '!=', 'closing')
                ->where(function ($q) use ($bulan, $tahun, $tableMapping) {
                    // A. Jurnal Umum / Manual
                    $q->where(function ($queryManual) use ($bulan, $tahun) {
                        $queryManual->whereIn('journal_type', ['jurnal_umum', 'jurnal'])
                            ->whereHas('journal', function ($j) use ($bulan, $tahun) {
                                $j->whereMonth('tanggal', $bulan)
                                  ->whereYear('tanggal', $tahun)
                                  ->where('status', 'approved');
                            });
                    });

                    // B. Jurnal Penyesuaian
                    $q->orWhere(function ($queryAjp) use ($bulan, $tahun) {
                        $queryAjp->whereIn('journal_type', [\App\Models\JurnalPenyesuaian::class, 'jurnal_penyesuaian'])
                            ->whereHas('jurnalPenyesuaianHeader', function ($j) use ($bulan, $tahun) {
                                $j->whereMonth('tanggal', $bulan)
                                  ->whereYear('tanggal', $tahun)
                                  ->where('status', 'approved');
                            });
                    });

                    // C. Jurnal Otomatis
                    foreach ($tableMapping as $type => $tableName) {
                        $q->orWhere(function ($queryOtomatis) use ($type, $tableName, $bulan, $tahun) {
                            $queryOtomatis->where('journal_type', $type)
                                ->whereExists(function ($sub) use ($tableName, $bulan, $tahun) {
                                    $sub->select(\DB::raw(1))
                                        ->from($tableName)
                                        ->whereColumn("$tableName.id", 'journal_items.journal_id')
                                        ->whereMonth('tanggal', $bulan)
                                        ->whereYear('tanggal', $tahun);
                                });
                        });
                    }
                });
        };

        // 1. Ambil Rincian Penjualan B2B per Pelanggan/Transaksi (Collision-free)
        $detailsPenjualanB2b = \App\Models\JournalItem::query()
            ->selectRaw('
                chart_of_accounts.nama as nama_akun,
                chart_of_accounts.kode as kode_akun,
                COALESCE(SUM(journal_items.kredit - journal_items.debit), 0) as total
            ')
            ->join('chart_of_accounts', 'chart_of_accounts.id', '=', 'journal_items.account_id')
            ->whereIn('journal_items.journal_type', ['penjualan_b2b', 'jurnal_penjualan_b2b'])
            ->whereExists(function ($sub) use ($bulan, $tahun) {
                $sub->select(\DB::raw(1))
                    ->from('jurnal_penjualan_b2b')
                    ->whereColumn('jurnal_penjualan_b2b.id', 'journal_items.journal_id')
                    ->whereMonth('tanggal', $bulan)
                    ->whereYear('tanggal', $tahun);
            })
            ->where('chart_of_accounts.tipe', 'Pendapatan')
            ->groupBy('chart_of_accounts.id', 'chart_of_accounts.nama', 'chart_of_accounts.kode')
            ->get()
            ->filter(fn($item) => $item->total != 0);

        // 2. Ambil Pendapatan Non-B2B / Pendapatan Lainnya (Collision-free)
        $detailsPendapatanLain = ChartOfAccount::where('tipe', 'Pendapatan')
            ->addSelect(['saldo' => \App\Models\JournalItem::selectRaw('COALESCE(SUM(kredit - debit), 0)')
                ->whereColumn('journal_items.account_id', 'chart_of_accounts.id')
                ->where('journal_items.journal_type', '!=', 'closing')
                ->where(function ($q) use ($bulan, $tahun) {
                    // A. Jurnal Umum
                    $q->where(function ($queryManual) use ($bulan, $tahun) {
                        $queryManual->whereIn('journal_type', ['jurnal_umum', 'jurnal'])
                            ->whereHas('journal', function ($j) use ($bulan, $tahun) {
                                $j->whereMonth('tanggal', $bulan)
                                  ->whereYear('tanggal', $tahun)
                                  ->where('status', 'approved');
                            });
                    });

                    // B. Jurnal Penyesuaian
                    $q->orWhere(function ($queryAjp) use ($bulan, $tahun) {
                        $queryAjp->whereIn('journal_type', [\App\Models\JurnalPenyesuaian::class, 'jurnal_penyesuaian'])
                            ->whereHas('jurnalPenyesuaianHeader', function ($j) use ($bulan, $tahun) {
                                $j->whereMonth('tanggal', $bulan)
                                  ->whereYear('tanggal', $tahun)
                                  ->where('status', 'approved');
                            });
                    });

                    // C. Jurnal POS
                    $q->orWhere(function ($queryPos) use ($bulan, $tahun) {
                        $queryPos->where('journal_type', 'jurnal_penjualan_pos')
                            ->whereExists(function ($sub) use ($bulan, $tahun) {
                                $sub->select(\DB::raw(1))
                                    ->from('jurnal_penjualan_pos')
                                    ->whereColumn('jurnal_penjualan_pos.id', 'journal_items.journal_id')
                                    ->whereMonth('tanggal', $bulan)
                                    ->whereYear('tanggal', $tahun);
                            });
                    });
                })
            ])
            ->get()
            ->filter(fn($coa) => $coa->saldo != 0);

        // 3. Ambil detail HPP (Harga Pokok Penjualan)
        $detailsHpp = ChartOfAccount::where(function($q) {
                $q->where('tipe', 'Harga Pokok Penjualan')
                  ->orWhere('tipe', 'HPP')
                  ->orWhere('kode', 'like', '5%');
            })
            ->addSelect(['saldo' => $getSaldoSubquery('COALESCE(SUM(debit - kredit), 0)')])
            ->get()
            ->filter(fn($coa) => $coa->saldo != 0);

        // 4. Ambil detail Beban Operasional
        $detailsBeban = ChartOfAccount::where('tipe', 'Beban')
            ->where('kode', 'not like', '5%')
            ->addSelect(['saldo' => $getSaldoSubquery('COALESCE(SUM(debit - kredit), 0)')])
            ->get()
            ->filter(fn($coa) => $coa->saldo != 0);

        // Kalkulasi Total
        $totalPenjualanB2b = $detailsPenjualanB2b->sum('total');
        $totalPendapatanLain = $detailsPendapatanLain->sum('saldo');
        $totalPendapatan = $totalPenjualanB2b + $totalPendapatanLain;
        
        $totalHpp = $detailsHpp->sum('saldo');
        $labaKotor = $totalPendapatan - $totalHpp;
        
        $totalBeban = $detailsBeban->sum('saldo');
        $labaBersih = $labaKotor - $totalBeban;

    // Data payload untuk dikirim ke view/pdf/excel
    $dataCompact = compact(
        'detailsPenjualanB2b', 'detailsPendapatanLain', 'detailsHpp', 'detailsBeban',
        'totalPenjualanB2b', 'totalPendapatanLain', 'totalPendapatan', 
        'totalHpp', 'labaKotor', 'totalBeban', 'labaBersih',
        'bulan', 'tahun'
    );

    // Export PDF
    if ($request->format === 'pdf') {
        $pdf = app('dompdf.wrapper')->setPaper('a4', 'landscape');
        $pdf->loadView('laporan.laba-rugi.pdf', $dataCompact);
        return $pdf->download('laporan-laba-rugi-' . now()->format('Ymd') . '.pdf');
    }

    // Export Excel
    if ($request->format === 'excel') {
        return $this->exportExcelLabaRugi($dataCompact);
    }

    // Tampilan Web
    return view('laporan.laba-rugi.index', $dataCompact);
}

    public function neracaIndex(Request $request)
    {
    $bulan = $request->get('bulan', date('m'));
    $tahun = $request->get('tahun', date('Y'));

    // 1. Tentukan tanggal batas akhir pelaporan (As of Date)
    $tanggalCutoff = \Carbon\Carbon::createFromDate($tahun, $bulan)->endOfMonth()->toDateString();
    
    // 2. Tentukan tanggal awal tahun fiskal untuk Laba Tahun Berjalan (YTD)
    $awalTahun = \Carbon\Carbon::createFromDate($tahun, 1, 1)->toDateString();

    $tableMapping = [
        'jurnal_penjualan_pos' => 'jurnal_penjualan_pos', 
        'penjualan_b2b'        => 'jurnal_penjualan_b2b', 
        'jurnal_pembelian'     => 'jurnal_pembelian',     
    ];

    // Helper filter tanggal akumulatif (Dari awal berdiri s.d. tanggal cutoff) dengan checking journal_type
    $filterTanggalAkumulatif = function ($query) use ($tanggalCutoff, $tableMapping) {
        $query->where(function ($q) use ($tanggalCutoff, $tableMapping) {
            // A. Jurnal Umum / Manual / Closing
            $q->where(function ($queryManual) use ($tanggalCutoff) {
                $queryManual->whereIn('journal_type', ['jurnal_umum', 'jurnal', 'closing'])
                    ->whereHas('journal', function ($j) use ($tanggalCutoff) {
                        $j->where('tanggal', '<=', $tanggalCutoff)
                          ->where('status', 'approved');
                    });
            });

            // B. Jurnal Penyesuaian
            $q->orWhere(function ($queryAjp) use ($tanggalCutoff) {
                $queryAjp->whereIn('journal_type', [\App\Models\JurnalPenyesuaian::class, 'jurnal_penyesuaian'])
                    ->whereHas('jurnalPenyesuaianHeader', function ($j) use ($tanggalCutoff) {
                        $j->where('tanggal', '<=', $tanggalCutoff)
                          ->where('status', 'approved');
                    });
            });

            // C. Jurnal Otomatis
            foreach ($tableMapping as $type => $tableName) {
                $q->orWhere(function ($queryOtomatis) use ($type, $tableName, $tanggalCutoff) {
                    $queryOtomatis->where('journal_type', $type)
                        ->whereExists(function ($sub) use ($tableName, $tanggalCutoff) {
                            $sub->select(\DB::raw(1))
                                ->from($tableName)
                                ->whereColumn("$tableName.id", 'journal_items.journal_id')
                                ->where('tanggal', '<=', $tanggalCutoff);
                        });
                });
            }
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
    $filterTanggalYTD = function ($query) use ($awalTahun, $tanggalCutoff, $tableMapping) {
        $query->where(function ($q) use ($awalTahun, $tanggalCutoff, $tableMapping) {
            // A. Jurnal Umum / Manual
            $q->where(function ($queryManual) use ($awalTahun, $tanggalCutoff) {
                $queryManual->whereIn('journal_type', ['jurnal_umum', 'jurnal'])
                    ->where('journal_type', '!=', 'closing')
                    ->whereHas('journal', function ($j) use ($awalTahun, $tanggalCutoff) {
                        $j->whereBetween('tanggal', [$awalTahun, $tanggalCutoff])
                          ->where('status', 'approved');
                    });
            });

            // B. Jurnal Penyesuaian
            $q->orWhere(function ($queryAjp) use ($awalTahun, $tanggalCutoff) {
                $queryAjp->whereIn('journal_type', [\App\Models\JurnalPenyesuaian::class, 'jurnal_penyesuaian'])
                    ->whereHas('jurnalPenyesuaianHeader', function ($j) use ($awalTahun, $tanggalCutoff) {
                        $j->whereBetween('tanggal', [$awalTahun, $tanggalCutoff])
                          ->where('status', 'approved');
                    });
            });

            // C. Jurnal Otomatis
            foreach ($tableMapping as $type => $tableName) {
                $q->orWhere(function ($queryOtomatis) use ($type, $tableName, $awalTahun, $tanggalCutoff) {
                    $queryOtomatis->where('journal_type', $type)
                        ->whereExists(function ($sub) use ($tableName, $awalTahun, $tanggalCutoff) {
                            $sub->select(\DB::raw(1))
                                ->from($tableName)
                                ->whereColumn("$tableName.id", 'journal_items.journal_id')
                                ->whereBetween('tanggal', [$awalTahun, $tanggalCutoff]);
                        });
                });
            }
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
            if (class_exists(ArusKasExport::class)) {
                return Excel::download(new ArusKasExport($data), 'laporan-arus-kas-' . $tahun . $bulan . '.xlsx');
            }

            // Fallback Export Excel via View HTML
            return response()->view('laporan.arus-kas.excel', $data)
                ->header('Content-Type', 'application/vnd.ms-excel')
                ->header('Content-Disposition', 'attachment; filename="laporan-arus-kas-'.$tahun.$bulan.'.xls"');
        }

        // 4. Return View Web
        return view('laporan.arus-kas.index', $data);
    }

    // =========================================================================
    // PRIVATE CALCULATION METHODS
    // =========================================================================

    /**
     * Engine Kalkulasi Arus Kas Metode Langsung
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

        $investasiRaw = collect();
        $pendanaanRaw = collect();

        foreach ($kasJournalItems as $itemKas) {
            $debit  = $itemKas->debit;
            $kredit = $itemKas->kredit;

            if ($debit == 0 && $kredit == 0) continue;

            // Ambil header jurnal
            $header = $this->getHeaderJurnal($itemKas);
            $deskripsi = $header->deskripsi ?? 'Transaksi Kas';
            $descLower = strtolower($deskripsi);

            // Cari akun lawan (opponent) dalam jurnal yang sama
            $opponents = JournalItem::where('journal_id', $itemKas->journal_id)
                ->where('journal_type', $itemKas->journal_type)
                ->where('account_id', '!=', $itemKas->account_id)
                ->with('coa')
                ->get();

            $opponent = $opponents->first();

            // Klasifikasi berdasarkan akun lawan
            $activityType = 'operasional'; // fallback default
            if ($opponent && $opponent->coa) {
                $tipeCoa = $opponent->coa->tipe;
                $kodeCoa = $opponent->coa->kode;

                if ($tipeCoa === 'Ekuitas' || str_starts_with($kodeCoa, '3')) {
                    $activityType = 'pendanaan';
                } elseif ($tipeCoa === 'Liabilitas' && str_starts_with($kodeCoa, '22')) {
                    // Liabilitas jangka panjang masuk pendanaan
                    $activityType = 'pendanaan';
                } elseif ($tipeCoa === 'Aset' && str_starts_with($kodeCoa, '12')) {
                    // Aset tetap masuk investasi
                    $activityType = 'investasi';
                }
            }

            if ($activityType === 'pendanaan') {
                if ($debit > 0) {
                    $kategori = 'Setoran Modal Pemilik';
                    $pendanaanRaw->push([
                        'kategori' => $kategori,
                        'nominal'  => $debit,
                    ]);
                } else {
                    if ($opponent && $opponent->coa && $opponent->coa->kode === '3102') {
                        $kategori = 'Pengambilan Prive oleh Pemilik';
                    } else {
                        $kategori = 'Pembayaran Pendanaan / Pengembalian Modal';
                    }
                    $pendanaanRaw->push([
                        'kategori' => $kategori,
                        'nominal'  => $kredit * -1,
                    ]);
                }
            } elseif ($activityType === 'investasi') {
                if ($debit > 0) {
                    $kategori = 'Penjualan Aset Tetap';
                    $investasiRaw->push([
                        'kategori' => $kategori,
                        'nominal'  => $debit,
                    ]);
                } else {
                    $kategori = 'Pembelian Aset Tetap';
                    $investasiRaw->push([
                        'kategori' => $kategori,
                        'nominal'  => $kredit * -1,
                    ]);
                }
            } else {
                // OPERASIONAL
                if ($debit > 0) {
                    // Cek akun Pendapatan di lawan jurnalnya
                    $coaPenjualan = $opponents->filter(function ($item) {
                        return $item->coa && ($item->coa->tipe === 'Pendapatan' || str_starts_with($item->coa->kode, '4'));
                    })->first();

                    if ($coaPenjualan && $coaPenjualan->coa) {
                        $namaCoa = strtolower($coaPenjualan->coa->nama);
                        if (str_contains($namaCoa, 'kejingga')) {
                            $kategori = 'Penerimaan Penjualan Kasir POS Kejingga & PPN Keluaran';
                        } elseif (str_contains($namaCoa, 'gaharu')) {
                            $kategori = 'Penerimaan Penjualan Kasir POS Gaharu & PPN Keluaran';
                        } else {
                            $kategori = 'Penerimaan ' . $coaPenjualan->coa->nama;
                        }
                    } else {
                        // Fallback berdasarkan deskripsi atau tipe jurnal
                        if (str_contains($descLower, 'kejingga')) {
                            $kategori = 'Penerimaan Penjualan Kasir POS Kejingga & PPN Keluaran';
                        } elseif (str_contains($descLower, 'gaharu')) {
                            $kategori = 'Penerimaan Penjualan Kasir POS Gaharu & PPN Keluaran';
                        } elseif ($itemKas->journal_type === 'penjualan_b2b' || str_contains($descLower, 'b2b')) {
                            $kategori = 'Penerimaan Penjualan B2B';
                        } else {
                            $kategori = 'Penerimaan Kas Lainnya (' . $deskripsi . ')';
                        }
                    }

                    $penerimaanPelangganRaw->push([
                        'kategori' => $kategori,
                        'nominal'  => $debit,
                    ]);
                }

                if ($kredit > 0) {
                    // 1. Pengeluaran Pembelian Bahan Baku / Supplier (REKAPAN)
                    if ($itemKas->journal_type === 'jurnal_pembelian' || str_contains($descLower, 'pembelian') || str_contains($descLower, 'supplier')) {
                        $kategori = 'Pembayaran Pembelian Bahan Baku';
                        $pengeluaranBahanBakuRaw->push([
                            'kategori' => $kategori,
                            'nominal'  => $kredit * -1,
                        ]);
                    } else {
                        // 2. Pengeluaran Beban Operasional
                        if (str_contains($descLower, 'listrik') || str_contains($descLower, 'air') || str_contains($descLower, 'internet')) {
                            $kategori = 'Pembayaran Beban Listrik, Air, & Internet';
                        } elseif (str_contains($descLower, 'gaji') || str_contains($descLower, 'payroll') || str_contains($descLower, 'upah')) {
                            $kategori = 'Pembayaran Gaji & Upah Karyawan';
                        } else {
                            $kategori = $deskripsi;
                        }

                        $pengeluaranBebanOpRaw->push([
                            'kategori' => $kategori,
                            'nominal'  => $kredit * -1,
                        ]);
                    }
                }
            }
        }

        // GROUPING HASIL BERDASARKAN KATEGORI (Semua transaksi pembelian otomatis digabung)
        $penerimaanPelanggan = $penerimaanPelangganRaw->groupBy('kategori')->map(function ($items, $kat) {
            return ['keterangan' => $kat, 'nominal' => $items->sum('nominal')];
        })->values();

        $pengeluaranBahanBaku = $pengeluaranBahanBakuRaw->groupBy('kategori')->map(function ($items, $kat) {
            return ['keterangan' => $kat, 'nominal' => $items->sum('nominal')];
        })->values();

        $pengeluaranBebanOp = $pengeluaranBebanOpRaw->groupBy('kategori')->map(function ($items, $kat) {
            return ['keterangan' => $kat, 'nominal' => $items->sum('nominal')];
        })->values();

        $investasi = $investasiRaw->groupBy('kategori')->map(function ($items, $kat) {
            return ['keterangan' => $kat, 'nominal' => $items->sum('nominal')];
        })->values();

        $pendanaan = $pendanaanRaw->groupBy('kategori')->map(function ($items, $kat) {
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
        $saldoAwalKas = $this->getSaldoAkumulasiKasToDate($kasBankCoaIds, (int)$bulan - 1, (int)$tahun);
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
        $tableMapping = [
            'jurnal_penjualan_pos' => 'jurnal_penjualan_pos', 
            'penjualan_b2b'        => 'jurnal_penjualan_b2b', 
            'jurnal_pembelian'     => 'jurnal_pembelian',     
        ];

        return $query->where(function ($q) use ($bulan, $tahun, $tableMapping) {
            // A. Jurnal Umum / Manual
            $q->where(function ($sub) use ($bulan, $tahun) {
                $sub->whereIn('journal_type', ['jurnal_umum', 'jurnal'])
                    ->whereHas('journal', fn($j) => $j->whereMonth('tanggal', $bulan)->whereYear('tanggal', $tahun)->where('status', 'approved'));
            });

            // B. Jurnal Penyesuaian
            $q->orWhere(function ($sub) use ($bulan, $tahun) {
                $sub->whereIn('journal_type', [\App\Models\JurnalPenyesuaian::class, 'jurnal_penyesuaian'])
                    ->whereHas('jurnalPenyesuaianHeader', fn($j) => $j->whereMonth('tanggal', $bulan)->whereYear('tanggal', $tahun)->where('status', 'approved'));
            });

            // C. Jurnal Otomatis
            foreach ($tableMapping as $type => $tableName) {
                $q->orWhere(function ($sub) use ($type, $tableName, $bulan, $tahun) {
                    $sub->where('journal_type', $type)
                        ->whereExists(function ($subQ) use ($tableName, $bulan, $tahun) {
                            $subQ->select(\DB::raw(1))
                                ->from($tableName)
                                ->whereColumn("$tableName.id", 'journal_items.journal_id')
                                ->whereMonth('tanggal', $bulan)
                                ->whereYear('tanggal', $tahun);
                        });
                });
            }
        });
    }

    private function getSaldoAkumulasiKasToDate($kasBankCoaIds, $bulanTarget, $tahunTarget)
    {
        $bulanTarget = (int) $bulanTarget;
        $tahunTarget = (int) $tahunTarget;

        if ($bulanTarget <= 0) {
            $bulanTarget = 12;
            $tahunTarget = $tahunTarget - 1;
        }

        $tanggalBatas = Carbon::createFromDate($tahunTarget, $bulanTarget, 1)->endOfMonth()->format('Y-m-d');

        // 1. Saldo Opening Master (journal_type = 'opening')
        $saldoMasterOpening = JournalItem::whereIn('account_id', $kasBankCoaIds)
            ->where('journal_type', 'opening')
            ->selectRaw('SUM(debit) - SUM(kredit) as total')
            ->value('total') ?? 0;

        $tableMapping = [
            'jurnal_penjualan_pos' => 'jurnal_penjualan_pos', 
            'penjualan_b2b'        => 'jurnal_penjualan_b2b', 
            'jurnal_pembelian'     => 'jurnal_pembelian',     
        ];

        // 2. Akumulasi Mutasi Historis Sebelum/Sama Dengan Tanggal Batas
        $queryMutasiHistoris = JournalItem::whereIn('account_id', $kasBankCoaIds)
            ->where('journal_type', '!=', 'opening')
            ->where(function ($q) use ($tanggalBatas, $tableMapping) {
                // A. Jurnal Umum / Manual
                $q->where(function ($queryManual) use ($tanggalBatas) {
                    $queryManual->whereIn('journal_type', ['jurnal_umum', 'jurnal', 'closing'])
                        ->whereHas('journal', function ($j) use ($tanggalBatas) {
                            $j->whereDate('tanggal', '<=', $tanggalBatas)
                              ->where('status', 'approved');
                        });
                });

                // B. Jurnal Penyesuaian
                $q->orWhere(function ($queryAjp) use ($tanggalBatas) {
                    $queryAjp->whereIn('journal_type', [\App\Models\JurnalPenyesuaian::class, 'jurnal_penyesuaian'])
                        ->whereHas('jurnalPenyesuaianHeader', function ($j) use ($tanggalBatas) {
                            $j->whereDate('tanggal', '<=', $tanggalBatas)
                              ->where('status', 'approved');
                        });
                });

                // C. Jurnal Otomatis
                foreach ($tableMapping as $type => $tableName) {
                    $q->orWhere(function ($queryOtomatis) use ($type, $tableName, $tanggalBatas) {
                        $queryOtomatis->where('journal_type', $type)
                            ->whereExists(function ($sub) use ($tableName, $tanggalBatas) {
                                $sub->select(\DB::raw(1))
                                    ->from($tableName)
                                    ->whereColumn("$tableName.id", 'journal_items.journal_id')
                                    ->whereDate('tanggal', '<=', $tanggalBatas);
                            });
                    });
                }
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

    public function perubahanEkuitas(Request $request)
    {
        $startDate = $request->input('start_date', date('Y-01-01'));
        $endDate = $request->input('end_date', date('Y-12-31'));

        // Query dasar dengan Left Join ke semua jenis tabel transaksi jurnal
        $queryBase = DB::table('journal_items')
            ->join('chart_of_accounts', 'journal_items.account_id', '=', 'chart_of_accounts.id')
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
            // Filter hanya jurnal yang sudah disetujui (Approved) untuk tipe jurnal yang memiliki status
            ->where(function ($q) {
                $q->where(function ($sub) {
                    $sub->whereIn('journal_items.journal_type', ['jurnal_umum', 'jurnal', 'closing'])
                        ->where('journals.status', '=', 'approved');
                })
                ->orWhere(function ($sub) {
                    $sub->whereIn('journal_items.journal_type', [\App\Models\JurnalPenyesuaian::class, 'jurnal_penyesuaian'])
                        ->where('jurnal_penyesuaian.status', '=', 'approved');
                })
                ->orWhereNotIn('journal_items.journal_type', [
                    'jurnal_umum', 'jurnal', 'closing',
                    \App\Models\JurnalPenyesuaian::class, 'jurnal_penyesuaian'
                ]);
            });

        // 1. Modal Awal (Saldo kumulatif sebelum startDate)
        $modalAwal = (clone $queryBase)
            ->where(function ($q) use ($startDate) {
                $q->where('journal_items.journal_type', '=', 'opening')
                  ->orWhereRaw('COALESCE(journals.tanggal, jurnal_pembelian.tanggal, jurnal_penjualan_pos.tanggal, jurnal_penjualan_b2b.tanggal, jurnal_penyesuaian.tanggal) < ?', [$startDate]);
            })
            ->whereIn('chart_of_accounts.tipe', ['Ekuitas', 'Pendapatan', 'Beban'])
            ->selectRaw('SUM(journal_items.kredit - journal_items.debit) as total')
            ->value('total') ?? 0;

        // 2. Setoran Modal Tambahan (Selama periode berjalan untuk akun Ekuitas selain Prive, Laba Ditahan, Laba/Rugi Berjalan)
        $penambahanModal = (clone $queryBase)
            ->whereRaw('COALESCE(journals.tanggal, jurnal_pembelian.tanggal, jurnal_penjualan_pos.tanggal, jurnal_penjualan_b2b.tanggal, jurnal_penyesuaian.tanggal) BETWEEN ? AND ?', [$startDate, $endDate])
            ->where('chart_of_accounts.tipe', '=', 'Ekuitas')
            ->whereNotIn('chart_of_accounts.kode', ['3102', '3103', '3104'])
            ->selectRaw('SUM(journal_items.kredit - journal_items.debit) as total')
            ->value('total') ?? 0;

        // 3. Laba / Rugi Bersih (Selama periode berjalan)
        $totalPendapatan = (clone $queryBase)
            ->whereRaw('COALESCE(journals.tanggal, jurnal_pembelian.tanggal, jurnal_penjualan_pos.tanggal, jurnal_penjualan_b2b.tanggal, jurnal_penyesuaian.tanggal) BETWEEN ? AND ?', [$startDate, $endDate])
            ->where('chart_of_accounts.tipe', '=', 'Pendapatan')
            ->selectRaw('SUM(journal_items.kredit - journal_items.debit) as total')
            ->value('total') ?? 0;

        $totalBeban = (clone $queryBase)
            ->whereRaw('COALESCE(journals.tanggal, jurnal_pembelian.tanggal, jurnal_penjualan_pos.tanggal, jurnal_penjualan_b2b.tanggal, jurnal_penyesuaian.tanggal) BETWEEN ? AND ?', [$startDate, $endDate])
            ->where('chart_of_accounts.tipe', '=', 'Beban')
            ->selectRaw('SUM(journal_items.debit - journal_items.kredit) as total')
            ->value('total') ?? 0;

        $labaRugiBersih = $totalPendapatan - $totalBeban;

        // 4. Prive (Selama periode berjalan untuk akun 3102)
        $prive = (clone $queryBase)
            ->whereRaw('COALESCE(journals.tanggal, jurnal_pembelian.tanggal, jurnal_penjualan_pos.tanggal, jurnal_penjualan_b2b.tanggal, jurnal_penyesuaian.tanggal) BETWEEN ? AND ?', [$startDate, $endDate])
            ->where('chart_of_accounts.kode', '=', '3102')
            ->selectRaw('SUM(journal_items.debit - journal_items.kredit) as total')
            ->value('total') ?? 0;

        // 5. Total Perubahan & Modal Akhir
        $perubahanEkuitas = ($penambahanModal + $labaRugiBersih) - $prive;
        $modalAkhir = $modalAwal + $perubahanEkuitas;

        return view('laporan.perubahan_ekuitas.index', compact(
            'startDate',
            'endDate',
            'modalAwal',
            'penambahanModal',
            'labaRugiBersih',
            'prive',
            'perubahanEkuitas',
            'modalAkhir'
        ));
    }
}
