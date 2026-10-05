<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockOpnameDetail extends Model
{
    protected $table = 'stock_opname_detail';

    protected $fillable = [
        'stock_opname_id',
        'barang_id',
        'stok_sistem',
        'stok_fisik',
        'selisih',
        'nilai_selisih',
        'harga_satuan',
    ];

    /**
     * Pastikan kolom harga_satuan (harga per satuan input manual) tersedia tanpa file migrasi.
     */
    public static function ensureHargaColumn(): bool
    {
        static $available = null;
        if ($available !== null) return $available;
        try {
            if (!\Illuminate\Support\Facades\Schema::hasColumn('stock_opname_detail', 'harga_satuan')) {
                \Illuminate\Support\Facades\Schema::table('stock_opname_detail', function ($table) {
                    $table->decimal('harga_satuan', 18, 4)->nullable();
                });
            }
            $available = \Illuminate\Support\Facades\Schema::hasColumn('stock_opname_detail', 'harga_satuan');
        } catch (\Throwable $e) {
            $available = false;
        }
        return $available;
    }

    /*
    |--------------------------------------------------------------------------
    | RELATION
    |--------------------------------------------------------------------------
    */

    public function stockOpname()
    {
        return $this->belongsTo(
            StockOpname::class,
            'stock_opname_id'
        );
    }

    public function barang()
    {
        return $this->belongsTo(
            MasterBarang::class,
            'barang_id'
        );
    }
}