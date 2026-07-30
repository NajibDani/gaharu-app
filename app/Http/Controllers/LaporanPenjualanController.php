<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Pesanan;

class LaporanPenjualanController extends Controller
{
    public function index(Request $request)
    {
        // 1. Mengatur default filter tanggal
        $tanggal_mulai = $request->get('tanggal_mulai', date('Y-m-01'));
        $tanggal_selesai = $request->get('tanggal_selesai', date('Y-m-t'));

        // 2. Ambil data pesanan beserta total subtotal dari detailnya
        $pesanans = Pesanan::with('customer')
            ->withSum('details', 'subtotal') 
            ->whereBetween('tanggal', [$tanggal_mulai, $tanggal_selesai])
            ->orderBy('tanggal', 'desc')
            ->get();

        // Hitung total HPP untuk setiap pesanan
        foreach ($pesanans as $row) {
            $hppAlokasi = \Illuminate\Support\Facades\DB::table('alokasi_produksi_pesanan')
                ->where('pesanan_id', $row->id)
                ->sum('total_hpp_alokasi') ?? 0;

            if ($hppAlokasi <= 0) {
                $hppAlokasi = \Illuminate\Support\Facades\DB::table('pesanan_detail')
                    ->join('master_barang', 'pesanan_detail.produk_id', '=', 'master_barang.id')
                    ->where('pesanan_detail.pesanan_id', $row->id)
                    ->sum(\Illuminate\Support\Facades\DB::raw('pesanan_detail.qty * master_barang.hpp_referensi')) ?? 0;
            }

            $row->total_hpp = $hppAlokasi;
        }

        // 3. Hitung Ringkasan Statistik
        $total_omzet = $pesanans->sum('details_sum_subtotal');
        $total_pesanan = $pesanans->count();
        
        // KODE ANTI-ERROR: Menggunakan filter + strtolower + trim agar kebal dari spasi/kapital
        $pesanan_selesai = $pesanans->filter(function ($p) {
            return strtolower(trim($p->status_pesanan)) === 'selesai';
        })->count();
        
        $pesanan_pending = $pesanans->filter(function ($p) {
            $status = strtolower(trim($p->status_pesanan));
            return $status === 'pending' || $status === 'siap kirim' || $status === 'siap_kirim';
        })->count();

        if ($request->format === 'pdf') {
            $pdf = app('dompdf.wrapper');
            $pdf->loadView('laporan-penjualan-pdf', compact(
                'pesanans', 'tanggal_mulai', 'tanggal_selesai',
                'total_omzet', 'total_pesanan', 'pesanan_selesai', 'pesanan_pending'
            ));
            return $pdf->download('laporan-penjualan-b2b-' . now()->format('Ymd') . '.pdf');
        }

        if ($request->format === 'excel') {
            return $this->exportExcel($pesanans);
        }

        return view('laporan-penjualan', compact(
            'pesanans', 
            'tanggal_mulai', 
            'tanggal_selesai', 
            'total_omzet', 
            'total_pesanan',
            'pesanan_selesai',
            'pesanan_pending'
        ));
    }

    private function exportExcel($data)
    {
        $filename = 'laporan-penjualan-b2b-' . now()->format('Ymd') . '.csv';
        $headers  = [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"$filename\"",
        ];

        $callback = function () use ($data) {
            $f = fopen('php://output', 'w');
            fprintf($f, chr(0xEF) . chr(0xBB) . chr(0xBF));
            fputcsv($f, ['Kode Pesanan', 'Customer', 'Tanggal', 'Status Pesanan', 'Status Bayar', 'Total HPP', 'Total Omzet']);
            foreach ($data as $row) {
                fputcsv($f, [
                    $row->kode_pesanan,
                    $row->customer->nama ?? 'N/A',
                    \Carbon\Carbon::parse($row->tanggal)->format('d-m-Y'),
                    ucfirst($row->status_pesanan),
                    $row->status_bayar ?? 'DP 30%',
                    $row->total_hpp ?? 0,
                    $row->details_sum_subtotal ?? 0,
                ]);
            }
            fclose($f);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function detailHpp(Request $request)
    {
        $type = $request->get('type');
        $id = $request->get('id');

        $items = [];
        $kode = '';

        if ($type === 'b2b') {
            $pesanan = \App\Models\Pesanan::with(['details.produk'])->find($id);
            if (!$pesanan) {
                return response()->json(['error' => 'Pesanan tidak ditemukan.'], 404);
            }
            $kode = $pesanan->kode_pesanan;

            foreach ($pesanan->details as $d) {
                $hppAlokasi = \Illuminate\Support\Facades\DB::table('alokasi_produksi_pesanan')
                    ->where('pesanan_id', $pesanan->id)
                    ->where('produk_id', $d->produk_id)
                    ->value('total_hpp_alokasi');

                if (is_null($hppAlokasi) || $hppAlokasi <= 0) {
                    $hppAlokasi = floatval($d->qty) * floatval($d->produk->hpp_referensi ?? 0);
                }

                $qty = floatval($d->qty);
                $totalHpp = floatval($hppAlokasi);
                $hppSatuan = $qty > 0 ? ($totalHpp / $qty) : 0;

                $bbb = $totalHpp / 1.3;
                $btkl = $bbb * 0.20;
                $bop = $bbb * 0.10;

                $items[] = [
                    'nama_barang' => $d->produk->nama ?? 'N/A',
                    'kode_barang' => $d->produk->kode_barang ?? 'N/A',
                    'qty' => $qty,
                    'satuan' => $d->produk->satuan ?? 'pcs',
                    'hpp_satuan' => $hppSatuan,
                    'total_hpp' => $totalHpp,
                    'bbb' => $bbb,
                    'btkl' => $btkl,
                    'bop' => $bop,
                ];
            }
        } elseif ($type === 'pos') {
            $penjualan = \App\Models\PenjualanPos::with(['details.produk'])->find($id);
            if (!$penjualan) {
                return response()->json(['error' => 'Transaksi POS tidak ditemukan.'], 404);
            }
            $kode = $penjualan->kode_transaksi;

            foreach ($penjualan->details as $d) {
                $qty = floatval($d->qty);
                $hppSatuan = floatval($d->hpp_satuan);
                $totalHpp = $qty * $hppSatuan;

                $bbb = $totalHpp / 1.3;
                $btkl = $bbb * 0.20;
                $bop = $bbb * 0.10;

                $items[] = [
                    'nama_barang' => $d->produk->nama ?? 'N/A',
                    'kode_barang' => $d->produk->kode_barang ?? 'N/A',
                    'qty' => $qty,
                    'satuan' => $d->produk->satuan ?? 'pcs',
                    'hpp_satuan' => $hppSatuan,
                    'total_hpp' => $totalHpp,
                    'bbb' => $bbb,
                    'btkl' => $btkl,
                    'bop' => $bop,
                ];
            }
        } else {
            return response()->json(['error' => 'Tipe laporan tidak valid.'], 400);
        }

        $totalBbb = 0;
        $totalBtkl = 0;
        $totalBop = 0;
        $totalHppKeseluruhan = 0;

        foreach ($items as $item) {
            $totalBbb += $item['bbb'];
            $totalBtkl += $item['btkl'];
            $totalBop += $item['bop'];
            $totalHppKeseluruhan += $item['total_hpp'];
        }

        return response()->json([
            'kode' => $kode,
            'items' => $items,
            'summary' => [
                'bbb' => $totalBbb,
                'btkl' => $totalBtkl,
                'bop' => $totalBop,
                'total_hpp' => $totalHppKeseluruhan,
            ]
        ]);
    }
}