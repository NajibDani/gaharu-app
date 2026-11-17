<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PenjualanPos extends Model
{
    protected $table = 'penjualan_pos';

    protected $fillable = [
        'kode_transaksi',
        'tanggal',
        'gudang_id',
        'divisi_id',
        'total',
        'status',
        'created_by'
    ];

    public $pendingDivisiId = null;

    public function details()
    {
        return $this->hasMany(PenjualanPosDetail::class, 'penjualan_id');
    }

    public function pembayaran()
    {
        return $this->hasMany(Pembayaran::class, 'penjualan_pos_id');
    }

    public function gudang()
    {
        return $this->belongsTo(MasterGudang::class, 'gudang_id');
    }

    public function getDivisiIdAttribute()
    {
        if (array_key_exists('divisi_id', $this->attributes)) {
            return $this->attributes['divisi_id'];
        }
        return \Illuminate\Support\Facades\Cache::get('penjualan_pos_divisi_' . $this->id);
    }

    public function setDivisiIdAttribute($value)
    {
        if (\Illuminate\Support\Facades\Schema::hasColumn('penjualan_pos', 'divisi_id')) {
            $this->attributes['divisi_id'] = $value;
        } else {
            $this->pendingDivisiId = $value;
        }
    }

    public function getDivisiAttribute()
    {
        $divId = $this->divisi_id;
        if (!$divId) return null;

        static $divisiCache = null;
        if ($divisiCache === null) {
            $divisiCache = \App\Models\GudangDivisi::all()->keyBy('id');
        }
        return $divisiCache->get($divId);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    protected static function boot()
    {
        parent::boot();

        static::saving(function ($model) {
            if (!\Illuminate\Support\Facades\Schema::hasColumn('penjualan_pos', 'divisi_id')) {
                if (isset($model->attributes['divisi_id'])) {
                    $model->pendingDivisiId = $model->attributes['divisi_id'];
                    unset($model->attributes['divisi_id']);
                }
            }
        });

        static::saved(function ($model) {
            if (isset($model->pendingDivisiId)) {
                if (!empty($model->pendingDivisiId)) {
                    \Illuminate\Support\Facades\Cache::put('penjualan_pos_divisi_' . $model->id, $model->pendingDivisiId, now()->addYears(5));
                } else {
                    \Illuminate\Support\Facades\Cache::forget('penjualan_pos_divisi_' . $model->id);
                }
                unset($model->pendingDivisiId);
            }
        });

        static::deleted(function ($model) {
            \Illuminate\Support\Facades\Cache::forget('penjualan_pos_divisi_' . $model->id);
        });

        static::deleting(function ($penjualan) {
            // Bersihkan transaksi_stok terkait jika ada
            \Illuminate\Support\Facades\DB::table('transaksi_stok')
                ->where('source_type', 'penjualan_pos')
                ->where('source_id', $penjualan->id)
                ->delete();

            // Bersihkan pengeluaran bahan baku AUTO_POS jika ada
            $pengeluaranList = \Illuminate\Support\Facades\DB::table('pengeluaran_bahan_baku')
                ->where('keterangan', 'AUTO_POS:' . $penjualan->kode_transaksi)
                ->get();

            foreach ($pengeluaranList as $peng) {
                \Illuminate\Support\Facades\DB::table('pengeluaran_bahan_baku_fifo')->where('pengeluaran_id', $peng->id)->delete();
                \Illuminate\Support\Facades\DB::table('pengeluaran_bahan_baku_detail')->where('pengeluaran_id', $peng->id)->delete();
                \Illuminate\Support\Facades\DB::table('pengeluaran_bahan_baku')->where('id', $peng->id)->delete();
                \Illuminate\Support\Facades\DB::table('transaksi_stok')
                    ->where('source_type', 'penjualan_pos')
                    ->where('source_id', $peng->id)
                    ->delete();
            }

            // Bersihkan detail transaksi
            $penjualan->details()->delete();
        });
    }
}