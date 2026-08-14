<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PesananDetail extends Model
{
    protected $table = 'pesanan_detail';

    protected $fillable = [
        'pesanan_id',
        'produk_id',
        'qty',
        'harga',
        'subtotal'
    ];

    public function pesanan()
    {
        return $this->belongsTo(Pesanan::class);
    }

    public function produk()
    {
        return $this->belongsTo(MasterBarang::class, 'produk_id');
    }

    public function getQtyTerkirimAttribute()
    {
        return floatval(\App\Models\PengirimanDetail::whereHas('pengiriman', function($q) {
            $q->where('pesanan_id', $this->pesanan_id)
              ->where('status_pengiriman', 'Selesai');
        })->where('barang_id', $this->produk_id)->sum('qty_kirim'));
    }

    public function getQtySisaAttribute()
    {
        $sisa = floatval($this->qty) - $this->qty_terkirim;
        return max(0, $sisa);
    }
}