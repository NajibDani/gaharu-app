<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TransaksiStok extends Model
{
    protected $table = 'transaksi_stok';
    public $timestamps = false;
    protected $fillable = [
        'tanggal',
        'tipe',
        'source_type',
        'source_id',
        'gudang_asal_id',
        'divisi_asal_id',
        'gudang_tujuan_id',
        'divisi_tujuan_id',
        'barang_id',
        'qty',
        'total_harga',
        'created_by'
    ];

    public function barang()
    {
        return $this->belongsTo(MasterBarang::class, 'barang_id');
    }

    public function divisiAsal()
    {
        return $this->belongsTo(GudangDivisi::class, 'divisi_asal_id');
    }

    public function divisiTujuan()
    {
        return $this->belongsTo(GudangDivisi::class, 'divisi_tujuan_id');
    }
}
