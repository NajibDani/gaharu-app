<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\PengeluaranBahanBaku;
use App\Models\Produksi;
use App\Models\Pembelian;
use App\Models\MasterGudang;
use App\Models\Supplier;
use App\Models\GudangDivisi;
use Illuminate\Support\Facades\DB;

class LaporanCustomController extends Controller
{
    // 1. Pengeluaran bahan baku dari gudang utama kejingga
    public function pengeluaranBahanBakuGudangUtamaKejingga(Request $request)
    {
        $query = PengeluaranBahanBaku::with(['details.barang', 'gudang', 'divisi'])
            ->whereHas('gudang', function ($q) {
                $q->where('nama', 'like', '%Gudang Utama%');
            })
            ->where(function ($q) {
                $q->where('keterangan', 'like', '%kejingga%')
                  ->orWhere('jenis_pengeluaran', 'like', '%kejingga%')
                  ->orWhereHas('divisi', function ($q2) {
                      $q2->where('nama', 'like', '%kejingga%');
                  });
            })
            ->where(function($q) {
                $q->whereNull('keterangan')->orWhere('keterangan', 'not like', '%opname%');
            })
            ->where('kode_pengeluaran', 'not like', '%SO%');

        if ($request->filled('start_date') && $request->filled('end_date')) {
            $query->whereBetween('tanggal', [$request->start_date, $request->end_date]);
        }

        $data = $query->latest('tanggal')->get();

        return view('laporan_custom.pengeluaran_bahan_baku_kejingga', compact('data'));
    }

    // 2. Pengeluaran produksi central kitchen ke jingga
    public function pengeluaranProduksiCentralKitchenKejingga(Request $request)
    {
        // Asumsi: Produksi dari gudang/divisi Central Kitchen ke Kejingga
        $query = Produksi::with(['details.barang', 'gudangBahan', 'gudangHasil', 'divisi'])
            ->whereHas('gudangBahan', function ($q) {
                $q->where('nama', 'like', '%Central Kitchen%');
            })
            ->where(function ($q) {
                $q->whereHas('gudangHasil', function ($q2) {
                    $q2->where('nama', 'like', '%Kejingga%');
                })->orWhereHas('divisi', function ($q3) {
                    $q3->where('nama', 'like', '%Kejingga%');
                });
            })
            ->where('kode_produksi', 'not like', '%SO%');

        if ($request->filled('start_date') && $request->filled('end_date')) {
            $query->whereBetween('tanggal_mulai', [$request->start_date, $request->end_date]);
        }

        $data = $query->latest('tanggal_mulai')->get();

        return view('laporan_custom.pengeluaran_produksi_ck_kejingga', compact('data'));
    }

    // 3. Pengeluaran produksi cold kitchen kejingga
    public function pengeluaranProduksiColdKitchenKejingga(Request $request)
    {
        // Asumsi: Produksi dari gudang/divisi Cold Kitchen ke Kejingga
        $query = Produksi::with(['details.barang', 'gudangBahan', 'gudangHasil', 'divisi'])
            ->whereHas('gudangBahan', function ($q) {
                $q->where('nama', 'like', '%Cold Kitchen%');
            })
            ->where(function ($q) {
                $q->whereHas('gudangHasil', function ($q2) {
                    $q2->where('nama', 'like', '%Kejingga%');
                })->orWhereHas('divisi', function ($q3) {
                    $q3->where('nama', 'like', '%Kejingga%');
                });
            })
            ->where('kode_produksi', 'not like', '%SO%');

        if ($request->filled('start_date') && $request->filled('end_date')) {
            $query->whereBetween('tanggal_mulai', [$request->start_date, $request->end_date]);
        }

        $data = $query->latest('tanggal_mulai')->get();

        return view('laporan_custom.pengeluaran_produksi_cold_kejingga', compact('data'));
    }

    // 4. Pembelian bahan baku gudang utama dikurangi permintaan ke central kitchen dan kejingga
    public function pembelianGudangUtamaDikurangiPermintaan(Request $request)
    {
        $jenisSupplier = $request->input('jenis_supplier', 'nota_pasar'); // nota_pasar atau selain_nota_pasar
        
        $pembelianQuery = Pembelian::with(['details.barang', 'supplier'])
            ->whereHas('gudang', function ($q) {
                $q->where('nama', 'like', '%Gudang Utama%');
            })
            ->where(function($q) {
                $q->whereNull('keterangan')->orWhere('keterangan', 'not like', '%opname%');
            })
            ->where('kode_pembelian', 'not like', '%SO%');

        if ($jenisSupplier == 'nota_pasar') {
            $pembelianQuery->whereHas('supplier', function ($q) {
                $q->where('nama', 'like', '%Nota Pasar%');
            });
        } else {
            $pembelianQuery->whereHas('supplier', function ($q) {
                $q->where('nama', 'not like', '%Nota Pasar%');
            });
        }

        if ($request->filled('start_date') && $request->filled('end_date')) {
            $pembelianQuery->whereBetween('tanggal', [$request->start_date, $request->end_date]);
        }

        $pembelianData = $pembelianQuery->get();

        // Calculate totals logic here if needed, passing to view
        return view('laporan_custom.pembelian_gudang_utama_minus_permintaan', compact('pembelianData', 'jenisSupplier'));
    }

    // 5. Total permintaan bahan baku dengan filter tiap divisi
    public function totalPermintaanBahanBakuDivisi(Request $request)
    {
        $divisi_id = $request->input('divisi_id');
        
        $query = PengeluaranBahanBaku::with(['details.barang', 'divisi'])
            ->where(function($q) {
                $q->whereNull('keterangan')->orWhere('keterangan', 'not like', '%opname%');
            })
            ->where('kode_pengeluaran', 'not like', '%SO%');
        
        if ($divisi_id) {
            $query->where('divisi_id', $divisi_id);
        }

        if ($request->filled('start_date') && $request->filled('end_date')) {
            $query->whereBetween('tanggal', [$request->start_date, $request->end_date]);
        }

        $data = $query->latest('tanggal')->get();
        $divisis = GudangDivisi::all();

        return view('laporan_custom.total_permintaan_divisi', compact('data', 'divisis', 'divisi_id'));
    }
}
