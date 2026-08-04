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
        // Laporan hanya menampilkan pesanan yang sudah SELESAI (revisi dosen),
        // sehingga statistik, export Excel, dan export PDF otomatis ikut hanya
        // menghitung pesanan yang selesai.
        $pesanans = Pesanan::with('customer')
            ->withSum('details', 'subtotal') 
            // Ditambahkan jam 00:00:00 s/d 23:59:59 karena kolom 'tanggal' menyimpan
            // jam juga, sehingga tanpa ini pesanan yang tanggalnya sama dengan
            // $tanggal_selesai tapi jamnya > 00:00:00 akan ikut terpotong dari hasil.
            ->whereBetween('tanggal', [$tanggal_mulai . ' 00:00:00', $tanggal_selesai . ' 23:59:59'])
            ->orderBy('tanggal', 'desc')
            ->get()
            ->filter(function ($p) {
                // Pengecekan status menggunakan strtolower + trim agar kebal dari beda huruf kapital/spasi
                return strtolower(trim($p->status_pesanan)) === 'selesai';
            })
            ->values();

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
        // Karena $pesanans di atas sudah difilter hanya yang 'selesai',
        // total_omzet & total_pesanan otomatis hanya menghitung pesanan selesai.
        $total_omzet = $pesanans->sum('details_sum_subtotal');
        $total_pesanan = $pesanans->count();
        $pesanan_selesai = $total_pesanan;

        // Jumlah pelanggan unik yang muncul di laporan (untuk kartu ringkasan,
        // menggantikan kartu "Pesanan Pending" yang sudah tidak relevan karena
        // laporan ini memang khusus pesanan selesai)
        $jumlah_customer = $pesanans->pluck('customer_id')->unique()->count();

        if ($request->format === 'pdf') {
            $pdf = app('dompdf.wrapper');
            $pdf->loadView('laporan-penjualan-pdf', compact(
                'pesanans', 'tanggal_mulai', 'tanggal_selesai',
                'total_omzet', 'total_pesanan', 'pesanan_selesai', 'jumlah_customer'
            ));
            return $pdf->download('laporan-penjualan-b2b-' . now()->format('Ymd') . '.pdf');
        }

        return view('laporan-penjualan', compact(
            'pesanans', 
            'tanggal_mulai', 
            'tanggal_selesai', 
            'total_omzet', 
            'total_pesanan',
            'pesanan_selesai',
            'jumlah_customer'
        ));
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
                $bbbSatuan = floatval($d->hpp_satuan); // Stored hpp_satuan is only BBB
                
                $btklSatuan = $bbbSatuan * 0.20;
                $bopSatuan = $bbbSatuan * 0.10;
                $hppSatuan = $bbbSatuan + $btklSatuan + $bopSatuan; // HPP Satuan = BBB + BTKL + BOP
                
                $bbb = $bbbSatuan * $qty;
                $btkl = $btklSatuan * $qty;
                $bop = $bopSatuan * $qty;
                $totalHpp = $hppSatuan * $qty;

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

    public function detailHargaJual(Request $request)
    {
        $type = $request->get('type');
        $id = $request->get('id');

        $items = [];
        $kode = '';
        $totalOmzet = 0;

        if ($type === 'b2b') {
            $pesanan = \App\Models\Pesanan::with(['details.produk'])->find($id);
            if (!$pesanan) {
                return response()->json(['error' => 'Pesanan tidak ditemukan.'], 404);
            }
            $kode = $pesanan->kode_pesanan;

            foreach ($pesanan->details as $d) {
                $qty = floatval($d->qty);
                $harga = floatval($d->harga);
                $subtotal = floatval($d->subtotal);

                $items[] = [
                    'nama_barang' => $d->produk->nama ?? 'N/A',
                    'kode_barang' => $d->produk->kode_barang ?? 'N/A',
                    'qty' => $qty,
                    'satuan' => $d->produk->satuan ?? 'pcs',
                    'harga' => $harga,
                    'subtotal' => $subtotal,
                ];

                $totalOmzet += $subtotal;
            }
        } elseif ($type === 'pos') {
            $penjualan = \App\Models\PenjualanPos::with(['details.produk'])->find($id);
            if (!$penjualan) {
                return response()->json(['error' => 'Transaksi POS tidak ditemukan.'], 404);
            }
            $kode = $penjualan->kode_transaksi;

            foreach ($penjualan->details as $d) {
                $qty = floatval($d->qty);
                $harga = floatval($d->harga);
                $subtotal = floatval($d->subtotal);

                $items[] = [
                    'nama_barang' => $d->produk->nama ?? 'N/A',
                    'kode_barang' => $d->produk->kode_barang ?? 'N/A',
                    'qty' => $qty,
                    'satuan' => $d->produk->satuan ?? 'pcs',
                    'harga' => $harga,
                    'subtotal' => $subtotal,
                ];

                $totalOmzet += $subtotal;
            }
        } else {
            return response()->json(['error' => 'Tipe laporan tidak valid.'], 400);
        }

        return response()->json([
            'kode' => $kode,
            'items' => $items,
            'summary' => [
                'total_omzet' => $totalOmzet,
            ]
        ]);
    }
}