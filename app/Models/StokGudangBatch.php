<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StokGudangBatch extends Model
{
    protected $table = 'stok_gudang_batch';

    protected $fillable = [
        'gudang_id',
        'divisi_id',
        'supplier_id',
        'barang_id',
        'pembelian_id',
        'pembelian_detail_id',
        'batch_number',
        'qty_masuk',
        'qty_keluar',
        'qty_sisa',
        'harga_per_qty',
        'is_habis',
    ];

    /*
    |--------------------------------------------------------------------------
    | RELATION
    |--------------------------------------------------------------------------
    */

    public function barang()
    {
        return $this->belongsTo(
            MasterBarang::class,
            'barang_id'
        );
    }

    public function supplier()
    {
        return $this->belongsTo(
            Supplier::class,
            'supplier_id'
        );
    }

    public function gudang()
    {
        return $this->belongsTo(
            MasterGudang::class,
            'gudang_id'
        );
    }

    public function divisi()
    {
        return $this->belongsTo(
            GudangDivisi::class,
            'divisi_id'
        );
    }

    public function pembelian()
    {
        return $this->belongsTo(
            Pembelian::class,
            'pembelian_id'
        );
    }
}