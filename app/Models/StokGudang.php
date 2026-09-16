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
        $query = \Illuminate\Support\Facades\DB::table('stok_gudang');
        if ($barangId) {
            $query->where('barang_id', $barangId);
        }
        if ($gudangId) {
            $query->where('gudang_id', $gudangId);
        }
        if ($divisiId) {
            $query->where('divisi_id', $divisiId);
        }

        $records = $query->get();

        foreach ($records as $rec) {
            $bId = $rec->barang_id;
            $gId = $rec->gudang_id;
            $dId = $rec->divisi_id;

            $batchQuery = \Illuminate\Support\Facades\DB::table('stok_gudang_batch')
                ->where('gudang_id', $gId)
                ->where('barang_id', $bId);
            if ($dId) {
                $batchQuery->where('divisi_id', $dId);
            } else {
                $batchQuery->whereNull('divisi_id');
            }
            $hasBatches = $batchQuery->exists();
            $batchSum = (float) ($batchQuery->sum('qty_sisa') ?? 0);

            $txIn = \Illuminate\Support\Facades\DB::table('transaksi_stok')->where('barang_id', $bId);
            $txOut = \Illuminate\Support\Facades\DB::table('transaksi_stok')->where('barang_id', $bId);
            if ($gId && $dId) {
                $txIn->where('gudang_tujuan_id', $gId)->where('divisi_tujuan_id', $dId);
                $txOut->where('gudang_asal_id', $gId)->where('divisi_asal_id', $dId);
            } elseif ($gId) {
                $txIn->where('gudang_tujuan_id', $gId);
                $txOut->where('gudang_asal_id', $gId);
            } elseif ($dId) {
                $txIn->where('divisi_tujuan_id', $dId);
                $txOut->where('divisi_asal_id', $dId);
            }
            $txNet = (float) ($txIn->sum('qty') - $txOut->sum('qty'));

            $targetJumlah = $hasBatches ? $batchSum : max(0, $txNet);

            if (abs((float)$rec->jumlah - $targetJumlah) > 0.0001) {
                \Illuminate\Support\Facades\DB::table('stok_gudang')->where('id', $rec->id)->update(['jumlah' => $targetJumlah]);
            }
        }
    }
}