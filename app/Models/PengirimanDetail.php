<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PengirimanDetail extends Model
{
    protected $table = 'pengiriman_detail'; // Tanpa "s"

    protected $fillable = [
        'pengiriman_id', 
        'barang_id', 
        'qty_kirim'
    ];

    public function pengiriman()
    {
        return $this->belongsTo(Pengiriman::class, 'pengiriman_id');
    }

    public function barang()
    {
        return $this->belongsTo(MasterBarang::class, 'barang_id');
    }

    public function produk()
    {
        return $this->belongsTo(MasterBarang::class, 'barang_id');
    }
}