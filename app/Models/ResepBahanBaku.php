<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ResepBahanBaku extends Model
{
    protected $table = 'resep_bahanbaku';
    protected $fillable = ['resep_id', 'bahan_id', 'qty_bahan', 'satuan'];
    public $timestamps = false;

    public function bahan()
    {
        // Menghubungkan bahan_id kembali ke MasterBarang untuk ambil Nama Barang
        return $this->belongsTo(MasterBarang::class, 'bahan_id', 'id');
    }

    public function resep()
    {
        return $this->belongsTo(ResepBtklBop::class, 'resep_id');
    }

    public function alternatif()
    {
        return $this->hasMany(ResepBahanBakuAlternatif::class, 'resep_bahanbaku_id')->orderBy('prioritas');
    }
}