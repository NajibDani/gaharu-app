<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\WorkOrder;
use App\Models\WorkOrderDetail;
use App\Models\Produksi;
use App\Models\ChartOfAccount;

class LaporanProduksiController extends Controller
{
    /**
     * 1. LAPORAN REKAPITULASI PRODUKSI (OPERASIONAL)
     */
    public function rekapitulasi(Request $request)
    {
        // Set default filter bulan berjalan (awal bulan s/d akhir bulan)
        $startDate = $request->get('start_date', date('Y-m-01'));
        $endDate   = $request->get('end_date', date('Y-m-t'));

        $rekapitulasi = DB::table('produksi_detail')
            ->join('produksi', 'produksi_detail.produksi_id', '=', 'produksi.id')
            ->leftJoin('master_barang', 'produksi_detail.produk_id', '=', 'master_barang.id')
            ->leftJoin('master_gudang', 'produksi.gudang_hasil_id', '=', 'master_gudang.id')
            ->select(
                'produksi.tanggal_mulai as tanggal',
                'produksi.kode_produksi',
                'master_barang.nama as nama_produk',
                'master_gudang.nama as nama_gudang',
                'produksi_detail.qty as qty_hasil',
                'produksi.status_produksi',
                // Subquery Kode WO
                DB::raw('(SELECT wo.kode_wo 
                          FROM work_order wo 
                          JOIN work_order_detail wod ON wod.work_order_id = wo.id 
                          WHERE wod.pesanan_id = produksi.pesanan_id 
                          LIMIT 1) as kode_wo'),
                // Subquery Target Qty Rencana dari WO
                DB::raw('(SELECT SUM(wod.qty_rencana) 
                          FROM work_order wo 
                          JOIN work_order_detail wod ON wod.work_order_id = wo.id 
                          WHERE wod.pesanan_id = produksi.pesanan_id 
                          AND wod.produk_id = produksi_detail.produk_id 
                          LIMIT 1) as qty_target')
            )
            ->whereBetween('produksi.tanggal_mulai', [$startDate, $endDate])
            ->orderBy('produksi.tanggal_mulai', 'desc')
            ->get();

        if ($request->format === 'pdf') {
            $pdf = app('dompdf.wrapper');
            $pdf->loadView('laporanproduksi.rekapitulasi-pdf', compact(
                'rekapitulasi', 'startDate', 'endDate'
            ));
            return $pdf->download('laporan-rekapitulasi-produksi-' . now()->format('Ymd') . '.pdf');
        }

        if ($request->format === 'excel') {
            return $this->exportExcelRekapitulasi($rekapitulasi);
        }

        return view('laporanproduksi.rekapitulasi', compact('rekapitulasi', 'startDate', 'endDate'));
    }

    private function exportExcelRekapitulasi($data)
    {
        $filename = 'laporan-rekapitulasi-produksi-' . now()->format('Ymd') . '.csv';
        $headers  = [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"$filename\"",
        ];

        $callback = function () use ($data) {
            $f = fopen('php://output', 'w');
            fprintf($f, chr(0xEF) . chr(0xBB) . chr(0xBF));
            fputcsv($f, ['Tanggal', 'Kode Produksi', 'Kode WO', 'Nama Produk', 'Gudang Tujuan', 'Target WO', 'Realisasi Output', 'Status']);
            foreach ($data as $row) {
                fputcsv($f, [
                    \Carbon\Carbon::parse($row->tanggal)->format('d-m-Y'),
                    $row->kode_produksi,
                    $row->kode_wo ?? '-',
                    $row->nama_produk,
                    $row->nama_gudang ?? 'Gudang B2B',
                    $row->qty_target,
                    $row->qty_hasil,
                    strtoupper($row->status_produksi ?? 'SELESAI'),
                ]);
            }
            fclose($f);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * 2. LAPORAN HARGA POKOK PRODUKSI / HPP (AKUNTANSI)
     */
    public function hpp(Request $request)
    {
        $startDate = $request->get('start_date', date('Y-m-01'));
        $endDate   = $request->get('end_date', date('Y-m-t'));

        // 1. Query HPP B2B (dari produksi)
        $b2bData = DB::table('produksi_detail')
            ->join('produksi', 'produksi_detail.produksi_id', '=', 'produksi.id')
            ->leftJoin('master_barang', 'produksi_detail.produk_id', '=', 'master_barang.id')
            ->select(
                'master_barang.id as produk_id',
                'master_barang.kode_barang',
                'master_barang.nama as nama_produk',
                'master_barang.satuan',
                DB::raw('SUM(produksi_detail.qty) as total_qty'),
                DB::raw('SUM(produksi_detail.hpp_total) as total_hpp'),
                DB::raw("'B2B' as tipe")
            )
            ->whereBetween('produksi.tanggal_mulai', [$startDate, $endDate])
            ->groupBy('master_barang.id', 'master_barang.kode_barang', 'master_barang.nama', 'master_barang.satuan')
            ->get();

        // 2. Query HPP POS (dari penjualanpos_detail)
        $posData = DB::table('penjualanpos_detail')
            ->join('penjualan_pos', 'penjualanpos_detail.penjualan_id', '=', 'penjualan_pos.id')
            ->leftJoin('master_barang', 'penjualanpos_detail.produk_id', '=', 'master_barang.id')
            ->select(
                'master_barang.id as produk_id',
                'master_barang.kode_barang',
                'master_barang.nama as nama_produk',
                'master_barang.satuan',
                DB::raw('SUM(penjualanpos_detail.qty) as total_qty'),
                DB::raw('SUM(penjualanpos_detail.qty * penjualanpos_detail.hpp_satuan) as total_hpp'),
                DB::raw("'POS' as tipe")
            )
            ->where('penjualan_pos.status', '=', 'SUKSES')
            ->whereBetween('penjualan_pos.tanggal', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
            ->groupBy('master_barang.id', 'master_barang.kode_barang', 'master_barang.nama', 'master_barang.satuan')
            ->get();

        // Combine collections and sort
        $laporanHpp = $b2bData->concat($posData)->sortByDesc('total_hpp');

        if ($request->format === 'pdf') {
            $pdf = app('dompdf.wrapper');
            $pdf->loadView('laporanproduksi.hpp-pdf', compact(
                'laporanHpp', 'startDate', 'endDate'
            ));
            return $pdf->download('laporan-hpp-' . now()->format('Ymd') . '.pdf');
        }

        if ($request->format === 'excel') {
            return $this->exportExcelHpp($laporanHpp);
        }

        return view('laporanproduksi.hpp', compact('laporanHpp', 'startDate', 'endDate'));
    }

    private function exportExcelHpp($data)
    {
        $filename = 'laporan-hpp-' . now()->format('Ymd') . '.csv';
        $headers  = [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"$filename\"",
        ];

        $callback = function () use ($data) {
            $f = fopen('php://output', 'w');
            fprintf($f, chr(0xEF) . chr(0xBB) . chr(0xBF));
            fputcsv($f, ['Kode Barang', 'Nama Produk Jadi', 'Tipe', 'Total Qty Produksi/Penjualan', 'Satuan', 'Total Nilai HPP', 'Rata-rata HPP / Satuan']);
            foreach ($data as $row) {
                $hppPerSatuan = $row->total_qty > 0 ? ($row->total_hpp / $row->total_qty) : 0;
                fputcsv($f, [
                    $row->kode_barang,
                    $row->nama_produk,
                    $row->tipe ?? 'B2B',
                    $row->total_qty,
                    $row->satuan ?? 'Pcs',
                    $row->total_hpp,
                    $hppPerSatuan,
                ]);
            }
            fclose($f);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function hppRecipeDetail(Request $request)
    {
        $produkId = $request->get('produk_id');
        $barang = \App\Models\MasterBarang::find($produkId);

        if (!$barang) {
            return response()->json(['error' => 'Produk tidak ditemukan.'], 404);
        }

        if (is_null($barang->resep_id)) {
            return response()->json(['error' => 'Produk ini belum memiliki resep yang terdaftar.'], 404);
        }

        $resepUtama = DB::table('resep_btkl_bop')->where('id', $barang->resep_id)->first();
        if (!$resepUtama) {
            return response()->json(['error' => 'Resep produk tidak ditemukan.'], 404);
        }

        $resepBahan = DB::table('resep_bahanbaku')
            ->where('resep_id', $resepUtama->id)
            ->get();

        $ingredients = [];
        $totalBbb = 0;

        foreach ($resepBahan as $bahan) {
            $rawMaterial = \App\Models\MasterBarang::find($bahan->bahan_id);
            
            // Get FIFO or Average price fallback
            $harga = DB::table('stok_gudang_batch')
                ->where('barang_id', $bahan->bahan_id)
                ->where('qty_sisa', '>', 0)
                ->orderBy('id', 'asc')
                ->value('harga_per_qty');
            if (!$harga) {
                $harga = DB::table('stok_gudang_batch')
                    ->where('barang_id', $bahan->bahan_id)
                    ->avg('harga_per_qty');
            }
            if (!$harga) {
                $harga = $rawMaterial ? $rawMaterial->hpp_referensi : 0;
            }

            $qtyBahan = floatval($bahan->qty_bahan);
            $hargaUnit = floatval($harga);
            $subtotalCost = $qtyBahan * $hargaUnit;
            $totalBbb += $subtotalCost;

            $ingredients[] = [
                'nama_bahan' => $rawMaterial ? $rawMaterial->nama : 'N/A',
                'kode_bahan' => $rawMaterial ? $rawMaterial->kode_barang : 'N/A',
                'qty_resep' => $qtyBahan,
                'satuan' => $bahan->satuan ?? ($rawMaterial ? $rawMaterial->satuan : 'Pcs'),
                'harga_satuan' => $hargaUnit,
                'total_harga' => $subtotalCost
            ];
        }

        // BTKL and BOP allocation based on 20% and 10% of BBB (Raw Material)
        $btkl = $totalBbb * 0.20;
        $bop = $totalBbb * 0.10;
        $totalHpp = $totalBbb * 1.30;

        $outputQty = floatval($resepUtama->output_qty) > 0 ? floatval($resepUtama->output_qty) : 1;

        return response()->json([
            'nama_produk' => $barang->nama,
            'kode_barang' => $barang->kode_barang,
            'satuan_output' => $resepUtama->satuan_output ?? $barang->satuan ?? 'pcs',
            'output_qty' => $outputQty,
            'ingredients' => $ingredients,
            'summary' => [
                'bbb' => $totalBbb,
                'btkl' => $btkl,
                'bop' => $bop,
                'total_hpp' => $totalHpp,
                'bbb_per_unit' => $totalBbb / $outputQty,
                'btkl_per_unit' => $btkl / $outputQty,
                'bop_per_unit' => $bop / $outputQty,
                'total_hpp_per_unit' => $totalHpp / $outputQty,
            ]
        ]);
    }

    /**
     * 3. DASHBOARD PRODUKSI (REPORTS)
     */
    public function dashboard(Request $request)
    {
        $startDate = $request->query('tgl_mulai', \Carbon\Carbon::now()->startOfMonth()->toDateString());
        $endDate   = $request->query('tgl_selesai', \Carbon\Carbon::now()->endOfMonth()->toDateString());

        // 1. Mini Summary Cards
        $woAktif = WorkOrder::where('status_wo', 'Diproses')
            ->whereBetween('created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
            ->count();
        
        $produksiSelesaiTahunIni = Produksi::where('status_produksi', 'Selesai')
            ->whereBetween('tanggal_selesai', [$startDate, $endDate])
            ->count();

        $totalQtyHasil = DB::table('produksi_detail')
            ->join('produksi', 'produksi_detail.produksi_id', '=', 'produksi.id')
            ->where('produksi.status_produksi', 'Selesai')
            ->whereBetween('produksi.tanggal_selesai', [$startDate, $endDate])
            ->sum('produksi_detail.qty');

        // Target Achievement Calculation
        $workOrders = WorkOrder::whereIn('status_wo', ['Draft', 'Diproses', 'Selesai'])
            ->whereBetween('created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
            ->get();
        $achievements = [];

        foreach ($workOrders as $wo) {
            $totalRencana = $wo->details()->sum('qty_rencana');
            
            $pesananIds = $wo->details()->pluck('pesanan_id')->filter()->unique()->toArray();
            $produkIds = $wo->details()->pluck('produk_id')->filter()->unique()->toArray();
            
            $totalAlokasi = 0;
            if (!empty($pesananIds) && !empty($produkIds)) {
                $totalAlokasi = DB::table('alokasi_produksi_pesanan')
                    ->whereIn('pesanan_id', $pesananIds)
                    ->whereIn('produk_id', $produkIds)
                    ->sum('qty_alokasi');
            }
            
            if ($totalRencana > 0) {
                $achievements[] = min(100, ($totalAlokasi / $totalRencana) * 100);
            }
        }

        $rataRataCapaian = count($achievements) > 0 ? (array_sum($achievements) / count($achievements)) : 0;

        // 2. Grafik Tren Produksi Dalam Periode
        $labelsProduksi = [];
        $dataProduksi = [];

        $chartData = DB::table('produksi')
            ->join('produksi_detail', 'produksi.id', '=', 'produksi_detail.produksi_id')
            ->where('produksi.status_produksi', 'Selesai')
            ->whereBetween('produksi.tanggal_selesai', [$startDate, $endDate])
            ->selectRaw('DATE(produksi.tanggal_selesai) as date_label, SUM(produksi_detail.qty) as daily_qty')
            ->groupBy('date_label')
            ->get()
            ->pluck('daily_qty', 'date_label');

        $periode = \Carbon\CarbonPeriod::create($startDate, $endDate);

        foreach ($periode as $tanggal) {
            $dateStr = $tanggal->format('Y-m-d');
            $labelsProduksi[] = $tanggal->format('d M');
            $dataProduksi[] = (float) ($chartData->get($dateStr) ?? 0);
        }

        // 3. List Bahan Baku yang Sudah Masuk ke Batas Minimum
        $bahanBakuMinimum = DB::table('master_barang')
            ->leftJoin('stok_gudang', 'master_barang.id', '=', 'stok_gudang.barang_id')
            ->where('master_barang.is_bahan_baku', 1)
            ->where('master_barang.is_active', true)
            ->select(
                'master_barang.nama',
                'master_barang.satuan',
                'master_barang.minimum_stock',
                DB::raw('COALESCE(SUM(stok_gudang.jumlah), 0) as total_stok')
            )
            ->groupBy('master_barang.id', 'master_barang.nama', 'master_barang.satuan', 'master_barang.minimum_stock')
            ->havingRaw('total_stok <= master_barang.minimum_stock')
            ->get();

        // 4. Produk Teratas Diproduksi (Top 5)
        $produkTeratas = DB::table('produksi_detail')
            ->join('produksi', 'produksi_detail.produksi_id', '=', 'produksi.id')
            ->join('master_barang', 'produksi_detail.produk_id', '=', 'master_barang.id')
            ->where('produksi.status_produksi', 'Selesai')
            ->whereBetween('produksi.tanggal_selesai', [$startDate, $endDate])
            ->select('master_barang.nama', 'master_barang.satuan', DB::raw('SUM(produksi_detail.qty) as total_qty'))
            ->groupBy('master_barang.id', 'master_barang.nama', 'master_barang.satuan')
            ->orderByDesc('total_qty')
            ->limit(5)
            ->get();

        // 5. Status Work Order
        $workOrderStatusQuery = WorkOrder::with('pembuat')
            ->whereBetween('created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
            ->latest();
        
        // If exporting, get all instead of limit 5
        if ($request->format === 'pdf' || $request->format === 'excel') {
            $workOrderStatus = $workOrderStatusQuery->get();
        } else {
            $workOrderStatus = $workOrderStatusQuery->limit(5)->get();
        }

        $workOrderStatus = $workOrderStatus->map(function ($wo) {
            $totalRencana = $wo->details()->sum('qty_rencana');
            
            $pesananIds = $wo->details()->pluck('pesanan_id')->filter()->unique()->toArray();
            $produkIds = $wo->details()->pluck('produk_id')->filter()->unique()->toArray();
            
            $totalAlokasi = 0;
            if (!empty($pesananIds) && !empty($produkIds)) {
                $totalAlokasi = DB::table('alokasi_produksi_pesanan')
                    ->whereIn('pesanan_id', $pesananIds)
                    ->whereIn('produk_id', $produkIds)
                    ->sum('qty_alokasi');
            }
            
            $wo->total_rencana = $totalRencana;
            $wo->total_realisasi = $totalAlokasi;
            $wo->persentase = $totalRencana > 0 ? round(($totalAlokasi / $totalRencana) * 100, 2) : 0;
            return $wo;
        });

        if ($request->format === 'pdf') {
            $pdf = app('dompdf.wrapper');
            $pdf->loadView('laporanproduksi.dashboard-pdf', compact('workOrderStatus'));
            return $pdf->download('laporan-work-order-status-' . now()->format('Ymd') . '.pdf');
        }

        if ($request->format === 'excel') {
            return $this->exportExcelWO($workOrderStatus);
        }

        return view('laporanproduksi.dashboard', compact(
            'startDate',
            'endDate',
            'woAktif',
            'produksiSelesaiTahunIni',
            'totalQtyHasil',
            'rataRataCapaian',
            'labelsProduksi',
            'dataProduksi',
            'bahanBakuMinimum',
            'produkTeratas',
            'workOrderStatus'
        ));
    }

    private function exportExcelWO($data)
    {
        $filename = 'laporan-work-order-status-' . now()->format('Ymd') . '.csv';
        $headers  = [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"$filename\"",
        ];

        $callback = function () use ($data) {
            $f = fopen('php://output', 'w');
            fprintf($f, chr(0xEF) . chr(0xBB) . chr(0xBF));
            fputcsv($f, ['Kode WO', 'Tanggal WO', 'Pembuat', 'Total Rencana Qty', 'Total Realisasi Qty', 'Realisasi %', 'Status']);
            foreach ($data as $row) {
                fputcsv($f, [
                    $row->kode_wo,
                    \Carbon\Carbon::parse($row->tanggal_wo)->format('d-m-Y'),
                    $row->pembuat->nama ?? 'Sistem',
                    $row->total_rencana,
                    $row->total_realisasi,
                    $row->persentase . '%',
                    $row->status_wo,
                ]);
            }
            fclose($f);
        };

        return response()->stream($callback, 200, $headers);
    }
}