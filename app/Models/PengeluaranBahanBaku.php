<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PengeluaranBahanBaku extends Model
{
    protected $table = 'pengeluaran_bahan_baku';

    protected $fillable = [
        'kode_pengeluaran',
        'tanggal',
        'gudang_id',
        'divisi_id',
        'jenis_pengeluaran',
        'status',
        'keterangan',
        'created_by',
        'approved_by',
        'approved_at',
    ];

    protected $casts = [
        'tanggal' => 'datetime',
        'approved_at' => 'datetime',
    ];

    public function details()
    {
        return $this->hasMany(
            PengeluaranBahanBakuDetail::class,
            'pengeluaran_id'
        );
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