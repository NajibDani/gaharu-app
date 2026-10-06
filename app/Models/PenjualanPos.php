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
        'total',
        'status', // <--- Tambahkan baris ini
        'created_by'
    ];

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

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    protected static function boot()
    {
        parent::boot();

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