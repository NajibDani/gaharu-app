<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

class HargaBarangB2b extends Model
{
    use HasFactory;

    protected $table = 'harga_barang_b2b';

    protected $fillable = [
        'customer_id',
        'barang_id',
        'harga_b2b',
        'keterangan',
    ];

    /**
     * Auto-create table harga_barang_b2b if not exists
     */
    public static function ensureTableExists(): void
    {
        if (!Schema::hasTable('harga_barang_b2b')) {
            Schema::create('harga_barang_b2b', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('customer_id');
                $table->unsignedBigInteger('barang_id');
                $table->decimal('harga_b2b', 15, 2)->default(0);
                $table->string('keterangan')->nullable();
                $table->timestamps();

                $table->foreign('customer_id')->references('id')->on('customers')->onDelete('cascade');
                $table->foreign('barang_id')->references('id')->on('master_barang')->onDelete('cascade');
                $table->unique(['customer_id', 'barang_id'], 'unique_customer_barang_b2b');
            });
        }
    }

    /**
     * Relasi ke Customer / Outlet Pemesan
     */
    public function customer()
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    /**
     * Relasi ke MasterBarang
     */
    public function barang()
    {
        return $this->belongsTo(MasterBarang::class, 'barang_id');
    }

    /**
     * Helper statis untuk mendapatkan harga jual B2B khusus outlet dari menu Data Master Harga B2B.
     * Mengembalikan float jika sudah disetting khusus per outlet, atau null jika belum diatur.
     */
    public static function getHargaB2bKhusus($customerId, $barangId): ?float
    {
        try {
            static::ensureTableExists();

            if ($customerId && $barangId) {
                $customPrice = static::where('customer_id', $customerId)
                    ->where('barang_id', $barangId)
                    ->value('harga_b2b');

                if ($customPrice !== null && floatval($customPrice) > 0) {
                    return floatval($customPrice);
                }
            }
        } catch (\Exception $e) {
            // Fallback safe
        }

        return null;
    }

    /**
     * Helper statis untuk mendapatkan harga jual B2B berdasarkan customer/outlet dan barang
     */
    public static function getHargaB2b($customerId, $barangId): float
    {
        try {
            static::ensureTableExists();

            if ($customerId) {
                $customPrice = static::where('customer_id', $customerId)
                    ->where('barang_id', $barangId)
                    ->value('harga_b2b');

                if ($customPrice !== null && floatval($customPrice) > 0) {
                    return floatval($customPrice);
                }
            }

            $barang = MasterBarang::find($barangId);
            if ($barang) {
                if (floatval($barang->harga_jual_b2b) > 0) {
                    return floatval($barang->harga_jual_b2b);
                }
                if (floatval($barang->harga_jual_pos) > 0) {
                    return floatval($barang->harga_jual_pos);
                }
            }
        } catch (\Exception $e) {
            // Fallback safe
        }

        return 0.0;
    }
}

