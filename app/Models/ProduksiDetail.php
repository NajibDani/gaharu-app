<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProduksiDetail extends Model
{
    protected $table = 'produksi_detail';
    protected $fillable = ['produksi_id', 'produk_id', 'qty'];

    // Relasi ke Header Produksi
    public function produksi() {
        return $this->belongsTo(Produksi::class, 'produksi_id');
    }

    // Relasi ke Master Barang / Produk
    public function produk() {
        return $this->belongsTo(MasterBarang::class, 'produk_id')->withoutGlobalScopes();
    }
}