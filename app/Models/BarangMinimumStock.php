<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BarangMinimumStock extends Model
{
    protected $table = 'barang_minimum_stock';

    protected $fillable = [
        'barang_id',
        'gudang_id',
        'divisi_id',
        'minimum_stock',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function barang()
    {
        return $this->belongsTo(MasterBarang::class, 'barang_id');
    }

    public function gudang()
    {
        return $this->belongsTo(MasterGudang::class, 'gudang_id');
    }

    public function divisi()
    {
        return $this->belongsTo(GudangDivisi::class, 'divisi_id');
    }
}
