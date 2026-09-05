<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PembelianDetail extends Model
{
    protected $table = 'pembelian_detail';

    protected $fillable = [
        'pembelian_id',
        'barang_id',
        'supplier_id',
        'satuan_pembelian',
        'konversi_pembelian',
        'qty',
        'qty_diterima',
        'harga',
        'harga_per_qty',
        'batch_number',
        // Pembayaran Per Line Item
        'metode_pembayaran',
        'persen_dp',
        'nominal_dp',
        'tanggal_jatuh_tempo',
        'tanggal_pelunasan',
        'catatan_pembayaran',
        'bukti_pembayaran',
        'is_lunas',
        'lunas_at',
    ];

    protected $casts = [
        'qty'                => 'decimal:2',
        'qty_diterima'       => 'decimal:2',
        'harga'              => 'decimal:2',
        'harga_per_qty'      => 'decimal:2',
        'nominal_dp'         => 'decimal:2',
        'is_lunas'           => 'boolean',
        'lunas_at'           => 'datetime',
    ];

    public $timestamps = false;

    public function pembelian()
    {
        return $this->belongsTo(Pembelian::class, 'pembelian_id');
    }

    public function barang()
    {
        return $this->belongsTo(MasterBarang::class, 'barang_id');
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }

    public function fifoBatch()
    {
        return $this->hasOne(StokGudangBatch::class, 'pembelian_detail_id');
    }
}