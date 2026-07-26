<?php

namespace App\Http\Controllers;

use App\Models\MasterGudang;
use App\Models\MasterBarang;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StokGudangController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();
        $roleName = $user->role->nama ?? '';

        $gudangId = $request->gudang_id;
        $barangId = $request->barang_id;

        // Auto filter warehouse based on role
        if ($roleName === 'Kepala Outlet Kejingga') {
            $gudangId = 4;
        } elseif ($roleName === 'Kepala Outlet Gaharu') {
            $gudangId = 2;
        } elseif ($roleName === 'Kepala Gudang') {
            $gudangId = 1;
        }

        /*
        |--------------------------------------------------------------------------
        | QUERY UTAMA
        |--------------------------------------------------------------------------
        |
        | Tampilkan semua kombinasi barang × gudang yang punya record stok_gudang.
        | Jika filter gudang aktif → hanya gudang itu.
        | Jika filter barang aktif → hanya barang itu.
        | Barang yang belum punya stok_gudang sama sekali TETAP muncul
        | (lewat subquery UNION di bawah) agar inventory lengkap.
        |
        */

        // ── Bagian 1: Barang yang sudah punya baris di stok_gudang ──
        $query = DB::table('stok_gudang')
            ->join('master_barang', 'master_barang.id', '=', 'stok_gudang.barang_id')
            ->join('master_gudang',  'master_gudang.id',  '=', 'stok_gudang.gudang_id')
            ->select([
                'master_barang.id',
                'master_barang.kode_barang',
                'master_barang.nama',
                'master_barang.satuan',
                'master_gudang.nama   as nama_gudang',
                'stok_gudang.gudang_id',
                'stok_gudang.jumlah   as qty',
            ]);

        if ($gudangId) {
            $query->where('stok_gudang.gudang_id', $gudangId);
        }

        if ($barangId) {
            $query->where('master_barang.id', $barangId);
        }

        // Ambil semua hasil (kita paginate manual di bawah)
        $rows = $query->orderBy('master_barang.nama')->orderBy('master_gudang.nama')->get();

        /*
        |--------------------------------------------------------------------------
        | PAGINATE MANUAL (Slice Terlebih Dahulu Sebelum Map untuk Efisiensi)
        |--------------------------------------------------------------------------
        */
        $perPage     = 20;
        $currentPage = (int) ($request->page ?? 1);
        $total       = $rows->count();
        $items       = $rows->slice(($currentPage - 1) * $perPage, $perPage)->values();

        /*
        |--------------------------------------------------------------------------
        | BULK PRE-FETCH HARGA FIFO & FALLBACK
        |--------------------------------------------------------------------------
        */
        $itemIds = $items->pluck('id')->toArray();
        $gudangIds = $items->pluck('gudang_id')->unique()->toArray();

        // 1. Ambil harga rata-rata dari batch aktif
        $batchPrices = DB::table('stok_gudang_batch')
            ->whereIn('barang_id', $itemIds)
            ->whereIn('gudang_id', $gudangIds)
            ->where('qty_sisa', '>', 0)
            ->select('barang_id', 'gudang_id')
            ->selectRaw('AVG(harga_per_qty) as avg_harga')
            ->groupBy('barang_id', 'gudang_id')
            ->get()
            ->keyBy(fn($x) => $x->barang_id . '-' . $x->gudang_id);

        // 2. Fallback: Ambil harga rata-rata dari semua batch historis
        $historicalPrices = DB::table('stok_gudang_batch')
            ->whereIn('barang_id', $itemIds)
            ->whereIn('gudang_id', $gudangIds)
            ->select('barang_id', 'gudang_id')
            ->selectRaw('AVG(harga_per_qty) as avg_harga')
            ->groupBy('barang_id', 'gudang_id')
            ->get()
            ->keyBy(fn($x) => $x->barang_id . '-' . $x->gudang_id);

        // 3. Fallback akhir: HPP referensi master barang
        $hppReferences = DB::table('master_barang')
            ->whereIn('id', $itemIds)
            ->pluck('hpp_referensi', 'id');

        /*
        |--------------------------------------------------------------------------
        | HITUNG STATUS & NILAI FIFO PER BARIS (Hanya pada items halaman aktif)
        |--------------------------------------------------------------------------
        */
        $items = $items->map(function ($row) use ($batchPrices, $historicalPrices, $hppReferences) {
            $row->status = $row->qty > 0 ? 'tersedia' : 'habis';

            $key = $row->id . '-' . $row->gudang_id;
            
            // Cek harga FIFO batch aktif
            $hargaFifo = $batchPrices->get($key)?->avg_harga;

            // Fallback rata-rata batch historis
            if (!$hargaFifo) {
                $hargaFifo = $historicalPrices->get($key)?->avg_harga;
            }

            // Fallback HPP referensi
            if (!$hargaFifo) {
                $hargaFifo = $hppReferences->get($row->id) ?? 0;
            }

            $row->harga_fifo  = (float) $hargaFifo;
            $row->nilai_stok  = $row->qty * $row->harga_fifo;

            return $row;
        });

        $stokGudang = new \Illuminate\Pagination\LengthAwarePaginator(
            $items,
            $total,
            $perPage,
            $currentPage,
            ['path' => $request->url(), 'query' => $request->query()]
        );


        /*
        |--------------------------------------------------------------------------
        | FILTER OPTIONS
        |--------------------------------------------------------------------------
        */

        if ($roleName === 'Kepala Outlet Kejingga') {
            $gudangs = MasterGudang::where('id', 4)->get();
        } elseif ($roleName === 'Kepala Outlet Gaharu') {
            $gudangs = MasterGudang::where('id', 2)->get();
        } elseif ($roleName === 'Kepala Gudang') {
            $gudangs = MasterGudang::where('id', 1)->get();
        } else {
            $gudangs = MasterGudang::orderBy('nama')->get();
        }
        $barangs = MasterBarang::orderBy('nama')->get();

        return view(
            'stok-gudang.index',
            compact('stokGudang', 'gudangs', 'barangs', 'gudangId', 'barangId')
        );
    }
}