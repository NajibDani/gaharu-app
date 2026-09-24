<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StokGudang extends Model
{
    protected $table = 'stok_gudang';

    public $timestamps = false;

    protected $fillable = [
        'gudang_id',
        'divisi_id',
        'barang_id',
        'jumlah',
    ];

    public function gudang()
    {
        return $this->belongsTo(MasterGudang::class, 'gudang_id');
    }

    public function divisi()
    {
        return $this->belongsTo(GudangDivisi::class, 'divisi_id');
    }

    public function barang()
    {
        return $this->belongsTo(MasterBarang::class, 'barang_id');
    }

    /**
     * Hitung stok real-time barang berdasarkan buku pembantu persediaan (transaksi_stok).
     */
    public static function getStokBukuPembantu($barangId, $gudangId = null, $divisiId = null, $date = null): float
    {
        $date = $date ?: date('Y-m-d');
        $cutoff = $date . ' 23:59:59';

        $qIn = \Illuminate\Support\Facades\DB::table('transaksi_stok')
            ->where('barang_id', $barangId)
            ->where('tanggal', '<=', $cutoff);

        $qOut = \Illuminate\Support\Facades\DB::table('transaksi_stok')
            ->where('barang_id', $barangId)
            ->where('tanggal', '<=', $cutoff);

        if ($gudangId && $divisiId) {
            $qIn->where('gudang_tujuan_id', $gudangId)->where('divisi_tujuan_id', $divisiId);
            $qOut->where('gudang_asal_id', $gudangId)->where('divisi_asal_id', $divisiId);
        } elseif ($gudangId) {
            $qIn->where('gudang_tujuan_id', $gudangId);
            $qOut->where('gudang_asal_id', $gudangId);
        } elseif ($divisiId) {
            $qIn->where('divisi_tujuan_id', $divisiId);
            $qOut->where('divisi_asal_id', $divisiId);
        } else {
            $qIn->where('tipe', 'masuk');
            $qOut->where('tipe', 'keluar');
        }

        $totalIn = (float) $qIn->sum('qty');
        $totalOut = (float) $qOut->sum('qty');

        return (float) ($totalIn - $totalOut);
    }

    /**
     * Bulk hitung stok real-time untuk banyak barang sekaligus dalam 1-2 query database.
     */
    public static function getBulkStokBukuPembantu(array $barangIds, $gudangId = null, $divisiId = null, $date = null): array
    {
        if (empty($barangIds)) return [];

        $date = $date ?: date('Y-m-d');
        $cutoff = $date . ' 23:59:59';

        $qIn = \Illuminate\Support\Facades\DB::table('transaksi_stok')
            ->whereIn('barang_id', $barangIds)
            ->where('tanggal', '<=', $cutoff)
            ->select('barang_id', \Illuminate\Support\Facades\DB::raw('SUM(qty) as total_in'))
            ->groupBy('barang_id');

        $qOut = \Illuminate\Support\Facades\DB::table('transaksi_stok')
            ->whereIn('barang_id', $barangIds)
            ->where('tanggal', '<=', $cutoff)
            ->select('barang_id', \Illuminate\Support\Facades\DB::raw('SUM(qty) as total_out'))
            ->groupBy('barang_id');

        if ($gudangId && $divisiId) {
            $qIn->where('gudang_tujuan_id', $gudangId)->where('divisi_tujuan_id', $divisiId);
            $qOut->where('gudang_asal_id', $gudangId)->where('divisi_asal_id', $divisiId);
        } elseif ($gudangId) {
            $qIn->where('gudang_tujuan_id', $gudangId);
            $qOut->where('gudang_asal_id', $gudangId);
        } elseif ($divisiId) {
            $qIn->where('divisi_tujuan_id', $divisiId);
            $qOut->where('divisi_asal_id', $divisiId);
        } else {
            $qIn->where('tipe', 'masuk');
            $qOut->where('tipe', 'keluar');
        }

        $ins = $qIn->pluck('total_in', 'barang_id');
        $outs = $qOut->pluck('total_out', 'barang_id');

        $result = [];
        foreach ($barangIds as $id) {
            $result[$id] = (float) (($ins[$id] ?? 0) - ($outs[$id] ?? 0));
        }

        return $result;
    }

    /**
     * Rekonsiliasi ringkasan stok stok_gudang dengan performa tinggi (bulk aggregated queries).
     */
    public static function reconcileStockSummary($barangId = null, $gudangId = null, $divisiId = null)
    {
        $inQuery = \Illuminate\Support\Facades\DB::table('transaksi_stok')
            ->whereNotNull('gudang_tujuan_id')
            ->select(
                'gudang_tujuan_id as gudang_id',
                \Illuminate\Support\Facades\DB::raw('COALESCE(divisi_tujuan_id, 0) as divisi_id'),
                'barang_id',
                \Illuminate\Support\Facades\DB::raw('SUM(qty) as total_in')
            )
            ->groupBy('gudang_tujuan_id', \Illuminate\Support\Facades\DB::raw('COALESCE(divisi_tujuan_id, 0)'), 'barang_id');

        $outQuery = \Illuminate\Support\Facades\DB::table('transaksi_stok')
            ->whereNotNull('gudang_asal_id')
            ->select(
                'gudang_asal_id as gudang_id',
                \Illuminate\Support\Facades\DB::raw('COALESCE(divisi_asal_id, 0) as divisi_id'),
                'barang_id',
                \Illuminate\Support\Facades\DB::raw('SUM(qty) as total_out')
            )
            ->groupBy('gudang_asal_id', \Illuminate\Support\Facades\DB::raw('COALESCE(divisi_asal_id, 0)'), 'barang_id');

        if ($barangId) {
            $inQuery->where('barang_id', $barangId);
            $outQuery->where('barang_id', $barangId);
        }
        if ($gudangId) {
            $inQuery->where('gudang_tujuan_id', $gudangId);
            $outQuery->where('gudang_asal_id', $gudangId);
        }
        if ($divisiId) {
            $inQuery->where('divisi_tujuan_id', $divisiId);
            $outQuery->where('divisi_asal_id', $divisiId);
        }

        $ins = $inQuery->get();
        $outs = $outQuery->get();

        $stockMap = [];
        foreach ($ins as $row) {
            $key = "{$row->gudang_id}_{$row->divisi_id}_{$row->barang_id}";
            $stockMap[$key] = [
                'gudang_id' => $row->gudang_id,
                'divisi_id' => $row->divisi_id == 0 ? null : $row->divisi_id,
                'barang_id' => $row->barang_id,
                'jumlah'    => (float) $row->total_in,
            ];
        }

        foreach ($outs as $row) {
            $key = "{$row->gudang_id}_{$row->divisi_id}_{$row->barang_id}";
            if (!isset($stockMap[$key])) {
                $stockMap[$key] = [
                    'gudang_id' => $row->gudang_id,
                    'divisi_id' => $row->divisi_id == 0 ? null : $row->divisi_id,
                    'barang_id' => $row->barang_id,
                    'jumlah'    => -(float) $row->total_out,
                ];
            } else {
                $stockMap[$key]['jumlah'] -= (float) $row->total_out;
            }
        }

        // Ambil existing stok_gudang
        $sgQuery = \Illuminate\Support\Facades\DB::table('stok_gudang');
        if ($barangId) $sgQuery->where('barang_id', $barangId);
        if ($gudangId) $sgQuery->where('gudang_id', $gudangId);
        if ($divisiId) $sgQuery->where('divisi_id', $divisiId);
        $existingRows = $sgQuery->get();

        $existingMap = [];
        foreach ($existingRows as $er) {
            $dKey = $er->divisi_id ? $er->divisi_id : 0;
            $existingMap["{$er->gudang_id}_{$dKey}_{$er->barang_id}"] = $er;
        }

        // Update beda nilai atau insert baru
        foreach ($stockMap as $key => $target) {
            if (isset($existingMap[$key])) {
                $current = $existingMap[$key];
                if (abs((float)$current->jumlah - $target['jumlah']) > 0.0001) {
                    \Illuminate\Support\Facades\DB::table('stok_gudang')
                        ->where('id', $current->id)
                        ->update(['jumlah' => $target['jumlah']]);
                }
            } else {
                if (abs($target['jumlah']) > 0.0001) {
                    \Illuminate\Support\Facades\DB::table('stok_gudang')->insert([
                        'gudang_id' => $target['gudang_id'],
                        'divisi_id' => $target['divisi_id'],
                        'barang_id' => $target['barang_id'],
                        'jumlah'    => $target['jumlah'],
                    ]);
                }
            }
        }

        // Jika baris stok_gudang ada tapi di stockMap tidak ada sama sekali mutasinya
        foreach ($existingMap as $key => $current) {
            if (!isset($stockMap[$key]) && abs((float)$current->jumlah) > 0.0001) {
                \Illuminate\Support\Facades\DB::table('stok_gudang')
                    ->where('id', $current->id)
                    ->update(['jumlah' => 0]);
            }
        }
    }
}