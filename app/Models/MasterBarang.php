<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Kategori;

class MasterBarang extends Model
{
    protected $table = 'master_barang';
    protected $fillable = [
        'kategori_id',
        'resep_id',
        'kode_barang',
        'nama',
        'satuan',
        'is_bahan_baku',
        'is_barang_jadi',
        'is_operational',
        'is_direct_consumption',
        'harga_jual_b2b',
        'harga_jual_pos',
        'hpp_referensi',
        'is_active',
        'minimum_stock',
        'minimum_order',
        'tipe_penjualan'
    ];

    protected static function booted()
    {
        static::addGlobalScope('role_barang_filter', function (\Illuminate\Database\Eloquent\Builder $builder) {
            // Bypass filter di luar konteks request (CLI, seeder, migrate)
            if (app()->runningInConsole()) {
                if (!app()->runningUnitTests() && !defined('TEST_RUNNING')) {
                    return;
                }
            }

            $user = auth()->user();
            if ($user && $user->role) {
                $roleName = $user->role->nama;
                if ($roleName === 'Super Admin' || $roleName === 'Administrator' || $roleName === 'HRD' || $roleName === 'Direktur Keuangan' || $roleName === 'Bagian Produksi') {
                    return;
                }

                if ($roleName === 'Kepala Outlet Gaharu') {
                    $builder->where(function ($q) {
                        $q->where('is_bahan_baku', 1)
                          ->orWhere(function ($q2) {
                              $q2->where('is_barang_jadi', 1)
                                 ->whereIn('tipe_penjualan', ['POS Gaharu', 'B2B']);
                          });
                    });
                } elseif ($roleName === 'Kepala Outlet Kejingga') {
                    $builder->where(function ($q) {
                        $q->where('is_bahan_baku', 1)
                          ->orWhere(function ($q2) {
                              $q2->where('is_barang_jadi', 1)
                                 ->where('tipe_penjualan', 'POS Kejingga');
                          });
                    });
                } elseif ($roleName === 'Kepala Gudang') {
                    $builder->where(function ($q) {
                        $q->where('is_bahan_baku', 1)
                          ->orWhere('is_operational', 1)
                          ->orWhere(function ($q2) {
                              $q2->where('is_barang_jadi', 1)
                                 ->where('tipe_penjualan', 'B2B');
                          });
                    });
                }
            }
        });
    }

    public function kategori()
    {
        return $this->belongsTo(Kategori::class, 'kategori_id');
    }
    public function permintaanDetails()
    {
        return $this->hasMany(
            PermintaanBahanBakuDetail::class,
            'barang_id'
        );
    }

// Di dalam class MasterBarang
public function hargaPosAktif()
{
    // Laravel akan otomatis mencari 'barang_id' di tabel harga_barang_pos 
    // dan mencocokkannya dengan 'id' di tabel ini
    return $this->hasOne(HargaPeriode::class, 'barang_id')
                ->whereDate('tgl_mulai', '<=', now())
                ->whereDate('tgl_selesai', '>=', now())
                ->latest();
}

public function firstFifoLayer()
{
    return $this->hasOne(FifoLayer::class, 'barang_id')->orderBy('tanggal_masuk', 'asc');
}
    public function resep()
    {
        // Gunakan hasMany karena satu resep_id memiliki banyak item bahan baku
        return $this->hasMany(ResepBahanBaku::class, 'resep_id', 'resep_id');
    }
public function stockOpnameDetails()
{
    return $this->hasMany(
        StockOpnameDetail::class,
        'barang_id'
    );
}
}
