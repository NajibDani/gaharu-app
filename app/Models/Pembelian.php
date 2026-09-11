<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Pembelian extends Model
{
    protected $table = 'pembelian';

    protected $fillable = [
        'kode_pembelian',
        'supplier_id',
        'gudang_id',
        'tanggal',
        'total',
        'created_by',
        // Pembayaran
        'metode_pembayaran',
        'tanggal_jatuh_tempo',
        'persen_dp',
        'nominal_dp',
        'tanggal_pelunasan',
        'catatan_pembayaran',
        'dicatat_oleh',
        'dicatat_pada',
        // Penerimaan barang
        'is_diterima',
        'diterima_at',
        'diterima_oleh',
        // Pelunasan
        'is_lunas',
        'lunas_at',
        'nominal_pelunasan',
        'catatan_pelunasan',
        'is_diterima',
    'is_lunas',
    'lunas_at', 
    'nominal_pelunasan',
    'catatan_pelunasan',
      'diterima_at',    
    'diterima_oleh',
        'tax_service',
    ];

    protected $casts = [
        'is_diterima'       => 'boolean',
        'is_lunas'          => 'boolean',
        'lunas_at'          => 'datetime',
        'diterima_at'       => 'datetime',
        'nominal_pelunasan' => 'decimal:2',
        'nominal_dp'        => 'decimal:2',
        'tax_service'       => 'decimal:2',
    ];

    public $timestamps = false;

    

    /*
    |--------------------------------------------------------------------------
    | RELATIONS
    |--------------------------------------------------------------------------
    */

    public function supplier()
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }

    public function gudang()
    {
        return $this->belongsTo(MasterGudang::class, 'gudang_id');
    }

    public function details()
    {
        return $this->hasMany(PembelianDetail::class, 'pembelian_id');
    }

    public function pembayaran()
    {
        return $this->hasMany(Pembayaran::class, 'pembelian_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function penerimaDiterima()
    {
        return $this->belongsTo(User::class, 'diterima_oleh');
    }

    /*
    |--------------------------------------------------------------------------
    | HELPER: apakah bisa diedit / dihapus
    |--------------------------------------------------------------------------
    | Terkunci jika: sudah diterima ATAU sudah lunas
    */

    public function isTerkunci(): bool
    {
        return $this->is_diterima || $this->is_lunas;
    }

    /*
    |--------------------------------------------------------------------------
    | HELPER: apakah tombol terima bisa diklik
    |--------------------------------------------------------------------------
    */

    public function bisaDiterima(): bool
    {
        return !$this->is_diterima;
    }

    public function isReceived(): bool
    {
        return (bool) $this->is_diterima;
    }

    /*
    |--------------------------------------------------------------------------
    | HELPER: apakah perlu tombol lunasi
    |--------------------------------------------------------------------------
    | Hanya untuk DP & Termin yang belum lunas
    */

    public function perluLunasi(): bool
    {
        return in_array($this->metode_pembayaran, ['dp', 'termin'])
            && !$this->is_lunas;
    }

    /*
    |--------------------------------------------------------------------------
    | VALIDASI EDITABLE (lama — dipertahankan untuk kompatibilitas)
    |--------------------------------------------------------------------------
    */

    public function isEditable(): bool
    {
        // Jika sudah terkunci, langsung false
        if ($this->isTerkunci()) {
            return false;
        }

        foreach ($this->details as $detail) {
            $stok = \App\Models\StokGudang::where('barang_id', $detail->barang_id)
                ->where('gudang_id', $this->gudang_id)
                ->first();

            if (!$stok || $stok->jumlah < $detail->qty) {
                return false;
            }
        }

        return true;
    }
}