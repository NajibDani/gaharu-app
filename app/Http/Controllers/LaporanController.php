<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ChartOfAccount;
use App\Models\JournalItem;
use Carbon\Carbon;

class LaporanController extends Controller
{
    /**
     * Laporan Arus Kas - METODE LANGSUNG (Direct Method)
     */
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

        // 3. Return View Web
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

        // 1. ID Akun Kas & Bank (1101 Kas di Bank BRI dan akun Kas/Bank lainnya)
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
            $descLower = strtolower($deskripsi);

            // =========================================================================
            // A. PENDAPATAN / KAS MASUK (Debit Kas)
            // =========================================================================
            if ($debit > 0) {
                // 1. Deteksi Uang Muka Penjualan / Pelanggan
                if (str_contains($descLower, 'uang muka') || str_contains($descLower, 'dp') || str_contains($descLower, 'down payment')) {
                    $kategori = 'Penerimaan Uang Muka Penjualan Pelanggan';
                } else {
                    // 2. Cek akun Pendapatan di lawan jurnalnya
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
                // 1. Cek apakah lawan akunnya adalah Ekuitas / Modal (misal: Prive, Dividen)
                $coaModal = JournalItem::where('journal_id', $itemKas->journal_id)
                    ->where('journal_type', $itemKas->journal_type)
                    ->whereHas('coa', fn($q) => $q->whereIn('tipe', ['Modal', 'Ekuitas'])->orWhere('kode', 'LIKE', '3%'))
                    ->with('coa')
                    ->first();

                if ($coaModal && $coaModal->coa) {
                    $pendanaan->push([
                        'keterangan' => $coaModal->coa->nama,
                        'nominal'    => $kredit * -1,
                    ]);
                } 
                // Fallback pencarian kata kunci prive/dividen di deskripsi
                elseif (str_contains($descLower, 'prive') || str_contains($descLower, 'dividen') || str_contains($descLower, 'modal')) {
                    $pendanaan->push([
                        'keterangan' => $deskripsi,
                        'nominal'    => $kredit * -1,
                    ]);
                }
                // 2. Deteksi Uang Muka Pembelian Supplier
                elseif (str_contains($descLower, 'uang muka') || str_contains($descLower, 'dp') || str_contains($descLower, 'down payment')) {
                    $kategori = 'Pembayaran Uang Muka Pembelian Supplier';

                    $pengeluaranBahanBakuRaw->push([
                        'kategori' => $kategori,
                        'nominal'  => $kredit * -1,
                    ]);
                } 
                // 3. Pengeluaran Pembelian Bahan Baku / Supplier (Direkap)
                elseif ($itemKas->journal_type === 'jurnal_pembelian' || str_contains($descLower, 'pembelian') || str_contains($descLower, 'supplier')) {
                    
                    $kategori = 'Pembayaran Pembelian Bahan Baku';

                    $pengeluaranBahanBakuRaw->push([
                        'kategori' => $kategori,
                        'nominal'  => $kredit * -1,
                    ]);

                } else {
                    // 4. Pengeluaran Beban Operasional
                    if (str_contains($descLower, 'listrik') || str_contains($descLower, 'air') || str_contains($descLower, 'internet')) {
                        $kategori = 'Pembayaran Beban Listrik, Air, & Internet';
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
        $kasBersihPendanaan   = $pendanaan->sum('keterangan')->count() > 0 ? $pendanaan->sum('nominal') : $pendanaan->sum('nominal');

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
}