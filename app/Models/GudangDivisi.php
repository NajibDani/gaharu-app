<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GudangDivisi extends Model
{
    protected $table = 'gudang_divisi';

    protected $fillable = [
        'gudang_id',
        'nama',
        'keterangan',
    ];

    public function gudang()
    {
        return $this->belongsTo(MasterGudang::class, 'gudang_id');
    }

    public function stok()
    {
        return $this->hasMany(StokGudang::class, 'divisi_id');
    }

    public function stokBatch()
    {
        return $this->hasMany(StokGudangBatch::class, 'divisi_id');
    }

    public function pengeluaranBahanBaku()
    {
        return $this->hasMany(PengeluaranBahanBaku::class, 'divisi_id');
    }

    public function stockOpname()
    {
        return $this->hasMany(StockOpname::class, 'divisi_id');
    }
}
