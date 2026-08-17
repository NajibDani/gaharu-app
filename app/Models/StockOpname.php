<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockOpname extends Model
{
    protected $table = 'stock_opname';

    protected $fillable = [
        'kode_opname',
        'tanggal',
        'gudang_id',
        'divisi_id',
        'status',
        'keterangan',
        'created_by',
    ];

    protected $casts = [
        'tanggal' => 'datetime',
    ];

    /*
    |--------------------------------------------------------------------------
    | RELATIONS
    |--------------------------------------------------------------------------
    */

    public function gudang()
    {
        return $this->belongsTo(MasterGudang::class, 'gudang_id');
    }

    public function divisi()
    {
        return $this->belongsTo(GudangDivisi::class, 'divisi_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function details()
    {
        return $this->hasMany(StockOpnameDetail::class, 'stock_opname_id');
    }

    /*
    |--------------------------------------------------------------------------
    | HELPERS
    |--------------------------------------------------------------------------
    */

    /**
     * Cek apakah opname ini sudah punya pengeluaran bahan baku otomatis.
     * Berguna untuk ditampilkan di view show.
     */
    public function pengeluaranOtomatis()
    {
        return \App\Models\PengeluaranBahanBaku::where(
            'keterangan',
            'like',
            '%' . $this->kode_opname . '%'
        )->first();
    }
}