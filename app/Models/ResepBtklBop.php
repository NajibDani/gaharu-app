<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ResepBtklBop extends Model
{
    protected $table = 'resep_btkl_bop';
    protected $fillable = ['produk_id', 'output_qty', 'satuan_output', 'btkl_per_batch', 'bop_per_batch'];

    public $timestamps = false;

    public function produk()
    {
        return $this->belongsTo(MasterBarang::class, 'produk_id');
    }

    public function bahanbaku()
    {
        return $this->hasMany(ResepBahanBaku::class, 'resep_id');
    }

    public function getSatuanOutputAttribute($value)
    {
        if ($this->relationLoaded('produk') && $this->produk) {
            return $this->produk->satuan ?: $value;
        }
        if ($this->produk_id) {
            $satuan = $this->produk()->value('satuan');
            if ($satuan) {
                return $satuan;
            }
        }
        return $value;
    }
}
