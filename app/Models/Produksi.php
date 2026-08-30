<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Produksi extends Model
{
    protected $table = 'produksi';

    protected $fillable = [
        'kode_produksi', 
        'pesanan_id', 
        'tanggal_mulai', 
        'tanggal_selesai', 
        'status_produksi', 
        'gudang_bahan_id', 
        'gudang_hasil_id', 
        'created_by',
        'divisi_id'
    ];

    /**
     * Relasi ke Pesanan (Sangat Penting untuk memperbaiki Error)
     */
    public function pesanan(): BelongsTo
    {
        return $this->belongsTo(Pesanan::class, 'pesanan_id');
    }

    public function divisi(): BelongsTo
    {
        return $this->belongsTo(GudangDivisi::class, 'divisi_id');
    }

    /**
     * Relasi ke Detail Produksi
     */
    public function details(): HasMany
    {
        return $this->hasMany(ProduksiDetail::class, 'produksi_id');
    }

    /**
     * Relasi ke Permintaan Bahan Baku
     */
    public function permintaanBahanBaku(): HasMany
    {
        return $this->hasMany(PermintaanBahanBaku::class, 'produksi_id');
    }

    /**
     * Relasi ke User pembuat data
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Relasi ke Gudang Bahan (Gudang B2B - ID 3)
     */
    public function gudangBahan(): BelongsTo
    {
        return $this->belongsTo(MasterGudang::class, 'gudang_bahan_id');
    }

    /**
     * Relasi ke Gudang Hasil (Gudang B2B - ID 3)
     */
    public function gudangHasil(): BelongsTo
    {
        return $this->belongsTo(MasterGudang::class, 'gudang_hasil_id');
    }
}