<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ResepBahanBakuAlternatif extends Model
{
    protected $table = 'resep_bahanbaku_alternatif';
    protected $fillable = ['resep_bahanbaku_id', 'bahan_id', 'prioritas'];
    public $timestamps = false;

    public function bahan()
    {
        return $this->belongsTo(MasterBarang::class, 'bahan_id', 'id');
    }

    public function resepBahanBaku()
    {
        return $this->belongsTo(ResepBahanBaku::class, 'resep_bahanbaku_id');
    }
}