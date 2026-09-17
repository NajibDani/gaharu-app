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

    public static function reconcileStockSummary($barangId = null, $gudangId = null, $divisiId = null)
    {
        $q1 = \Illuminate\Support\Facades\DB::table('stok_gudang')->select('gudang_id', 'divisi_id', 'barang_id');
        $q2 = \Illuminate\Support\Facades\DB::table('stok_gudang_batch')->select('gudang_id', 'divisi_id', 'barang_id');
        $q3 = \Illuminate\Support\Facades\DB::table('transaksi_stok')->whereNotNull('gudang_tujuan_id')->select('gudang_tujuan_id as gudang_id', 'divisi_tujuan_id as divisi_id', 'barang_id');
        $q4 = \Illuminate\Support\Facades\DB::table('transaksi_stok')->whereNotNull('gudang_asal_id')->select('gudang_asal_id as gudang_id', 'divisi_asal_id as divisi_id', 'barang_id');

        if ($barangId) {
            $q1->where('barang_id', $barangId);
            $q2->where('barang_id', $barangId);
            $q3->where('barang_id', $barangId);
            $q4->where('barang_id', $barangId);
        }
        if ($gudangId) {
            $q1->where('gudang_id', $gudangId);
            $q2->where('gudang_id', $gudangId);
            $q3->where('gudang_tujuan_id', $gudangId);
            $q4->where('gudang_asal_id', $gudangId);
        }
        if ($divisiId) {
            $q1->where('divisi_id', $divisiId);
            $q2->where('divisi_id', $divisiId);
            $q3->where('divisi_tujuan_id', $divisiId);
            $q4->where('divisi_asal_id', $divisiId);
        }

        $combos = $q1->union($q2)->union($q3)->union($q4)->get();

        foreach ($combos as $c) {
            $gId = $c->gudang_id;
            $dId = $c->divisi_id;
            $bId = $c->barang_id;

            if (!$gId || !$bId) continue;

            $batchQuery = \Illuminate\Support\Facades\DB::table('stok_gudang_batch')
                ->where('gudang_id', $gId)
                ->where('barang_id', $bId);
            if ($dId) {
                $batchQuery->where('divisi_id', $dId);
            } else {
                $batchQuery->whereNull('divisi_id');
            }
            $hasActiveBatches = (clone $batchQuery)->where('qty_sisa', '>', 0)->exists();
            $batchSum = (float) ((clone $batchQuery)->where('qty_sisa', '>', 0)->sum('qty_sisa') ?? 0);

            $txIn = \Illuminate\Support\Facades\DB::table('transaksi_stok')->where('barang_id', $bId)->where('gudang_tujuan_id', $gId);
            $txOut = \Illuminate\Support\Facades\DB::table('transaksi_stok')->where('barang_id', $bId)->where('gudang_asal_id', $gId);
            if ($dId) {
                $txIn->where('divisi_tujuan_id', $dId);
                $txOut->where('divisi_asal_id', $dId);
            } else {
                $txIn->whereNull('divisi_tujuan_id');
                $txOut->whereNull('divisi_asal_id');
            }
            $txNet = (float) ($txIn->sum('qty') - $txOut->sum('qty'));

            $targetJumlah = $hasActiveBatches ? max($batchSum, max(0, $txNet)) : max(0, $txNet);

            $sgQuery = \Illuminate\Support\Facades\DB::table('stok_gudang')->where('gudang_id', $gId)->where('barang_id', $bId);
            if ($dId) {
                $sgQuery->where('divisi_id', $dId);
            } else {
                $sgQuery->whereNull('divisi_id');
            }
            $existing = $sgQuery->first();

            if ($existing) {
                if (abs((float)$existing->jumlah - $targetJumlah) > 0.0001) {
                    \Illuminate\Support\Facades\DB::table('stok_gudang')->where('id', $existing->id)->update(['jumlah' => $targetJumlah]);
                }
            } else {
                if ($targetJumlah > 0) {
                    \Illuminate\Support\Facades\DB::table('stok_gudang')->insert([
                        'gudang_id' => $gId,
                        'divisi_id' => $dId,
                        'barang_id' => $bId,
                        'jumlah'    => $targetJumlah,
                    ]);
                }
            }
        }
    }
}