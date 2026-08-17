<?php

namespace App\Http\Controllers;

use App\Models\MasterBarang;
use App\Models\MasterGudang;
use App\Models\StokGudangBatch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StokGudangBatchController extends Controller
{
    public function index(Request $request)
    {
        $query = StokGudangBatch::with([
                'supplier',
                'barang',
                'gudang',
                'divisi',
                'pembelian',
            ])
            ->orderByDesc('id');

        /*
        |----------------------------------------------------------------------
        | FILTER
        |----------------------------------------------------------------------
        */

        if ($request->filled('gudang_id')) {
            $query->where('gudang_id', $request->gudang_id);
        }

        if ($request->filled('divisi_id')) {
            $query->where('divisi_id', $request->divisi_id);
        }

        if ($request->filled('barang_id')) {
            $query->where('barang_id', $request->barang_id);
        }

        if ($request->filled('status')) {
            if ($request->status === 'aktif') {
                $query->where('qty_sisa', '>', 0);
            } elseif ($request->status === 'habis') {
                $query->where('qty_sisa', '<=', 0);
            }
        }

        $data = $query->paginate(20)->withQueryString();

        /*
        |----------------------------------------------------------------------
        | DETEKSI BATCH TIDAK SINKRON
        |----------------------------------------------------------------------
        |
        | Tandai barang yang qty di stok_gudang != total qty_sisa di batch.
        | Ini membantu admin mendeteksi data yang tidak konsisten.
        |
        */

        $tidakSinkron = DB::table('stok_gudang as sg')
            ->join('master_barang as b', 'b.id', '=', 'sg.barang_id')
            ->join('master_gudang as mg', 'mg.id', '=', 'sg.gudang_id')
            ->leftJoin('gudang_divisi as gd', 'gd.id', '=', 'sg.divisi_id')
            ->leftJoin(DB::raw('(
                SELECT barang_id, gudang_id, divisi_id, SUM(qty_sisa) as total_sisa
                FROM stok_gudang_batch
                GROUP BY barang_id, gudang_id, divisi_id
            ) as batch_sum'), function ($join) {
                $join->on('batch_sum.barang_id', '=', 'sg.barang_id')
                     ->on('batch_sum.gudang_id', '=', 'sg.gudang_id')
                     ->where(function($q) {
                         $q->on('batch_sum.divisi_id', '=', 'sg.divisi_id')
                           ->orWhere(function($sq) {
                               $sq->whereNull('batch_sum.divisi_id')->whereNull('sg.divisi_id');
                           });
                     });
            })
            ->select(
                'b.nama as nama_barang',
                'mg.nama as nama_gudang',
                'gd.nama as nama_divisi',
                'sg.jumlah as stok_sistem',
                DB::raw('COALESCE(batch_sum.total_sisa, 0) as total_batch'),
                DB::raw('sg.jumlah - COALESCE(batch_sum.total_sisa, 0) as selisih')
            )
            ->havingRaw('ABS(selisih) > 0.01')
            ->get();

        /*
        |----------------------------------------------------------------------
        | FILTER OPTIONS
        |----------------------------------------------------------------------
        */

        $gudangs = MasterGudang::with('divisi')->orderBy('nama')->get();
        $barangs = MasterBarang::orderBy('nama')->get();

        return view(
            'stok-gudang-batch.index',
            compact('data', 'gudangs', 'barangs', 'tidakSinkron')
        );
    }
}