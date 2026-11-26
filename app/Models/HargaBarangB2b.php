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
        'mengikuti_hpp',
        'keterangan',
    ];

    protected $casts = [
        'mengikuti_hpp' => 'boolean',
        'harga_b2b'     => 'float',
    ];

    protected static bool $schemaEnsured = false;

    /**
     * Auto-create table harga_barang_b2b if not exists, and ensure column mengikuti_hpp exists
     */
    public static function ensureTableExists(): void
    {
        if (static::$schemaEnsured) {
            return;
        }

        // Jangan jalankan DDL (CREATE/ALTER TABLE) saat transaksi DB aktif
        // karena MySQL melakukan implicit commit yang membatalkan transaksi aktif.
        if (\Illuminate\Support\Facades\DB::transactionLevel() > 0) {
            return;
        }

        if (!Schema::hasTable('harga_barang_b2b')) {
            Schema::create('harga_barang_b2b', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('customer_id');
                $table->unsignedBigInteger('barang_id');
                $table->decimal('harga_b2b', 15, 2)->default(0);
                $table->boolean('mengikuti_hpp')->default(false);
                $table->string('keterangan')->nullable();
                $table->timestamps();

                $table->foreign('customer_id')->references('id')->on('customers')->onDelete('cascade');
                $table->foreign('barang_id')->references('id')->on('master_barang')->onDelete('cascade');
                $table->unique(['customer_id', 'barang_id'], 'unique_customer_barang_b2b');
            });
        } else {
            if (!Schema::hasColumn('harga_barang_b2b', 'mengikuti_hpp')) {
                Schema::table('harga_barang_b2b', function (Blueprint $table) {
                    $table->boolean('mengikuti_hpp')->default(false)->after('harga_b2b');
                });
            }
        }

        static::$schemaEnsured = true;
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
     * Cek apakah customer/outlet memiliki pengaturan harga mengikuti HPP riil untuk barang ini
     */
    public static function isMengikutiHpp($customerId, $barangId): bool
    {
        if (!$customerId || !$barangId) return false;
        try {
            static::ensureTableExists();
            return (bool) static::where('customer_id', $customerId)
                ->where('barang_id', $barangId)
                ->value('mengikuti_hpp');
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Helper statis untuk mendapatkan HPP riil terkini dari formulasi resep atau master barang
     */
    public static function getRealtimeHpp($barangId): float
    {
        if (!$barangId) return 0.0;
        try {
            $controller = app(\App\Http\Controllers\HargaBarangB2bController::class);
            $detail = $controller->getHppDetail($barangId)->getData(true);
            if (isset($detail['hpp_per_unit']) && floatval($detail['hpp_per_unit']) > 0) {
                return floatval($detail['hpp_per_unit']);
            }
        } catch (\Exception $e) {
            // Fallback safe
        }

        try {
            $barang = MasterBarang::withoutGlobalScopes()->find($barangId);
            if ($barang) {
                if (floatval($barang->hpp_referensi ?? 0) > 0) {
                    return floatval($barang->hpp_referensi);
                }
                if (floatval($barang->harga_satuan_terkecil ?? 0) > 0) {
                    return floatval($barang->harga_satuan_terkecil);
                }
            }
        } catch (\Exception $e) {
            // Fallback safe
        }

        return 0.0;
    }

    /**
     * Helper statis untuk mendapatkan harga jual B2B khusus outlet dari menu Data Master Harga B2B.
     * Mengembalikan float jika sudah disetting khusus per outlet, atau null jika belum diatur.
     * Jika opsi mengikuti_hpp aktif, langsung mengembalikan nilai HPP riil saat ini.
     */
    public static function getHargaB2bKhusus($customerId, $barangId): ?float
    {
        try {
            static::ensureTableExists();

            if ($customerId && $barangId) {
                $customRow = static::where('customer_id', $customerId)
                    ->where('barang_id', $barangId)
                    ->first();

                if ($customRow) {
                    if ((bool) $customRow->mengikuti_hpp) {
                        return static::getRealtimeHpp($barangId);
                    }
                    if ($customRow->harga_b2b !== null && floatval($customRow->harga_b2b) > 0) {
                        return floatval($customRow->harga_b2b);
                    }
                }
            }
        } catch (\Exception $e) {
            // Fallback safe
        }

        return null;
    }

    /**
     * Helper statis untuk mendapatkan harga jual B2B berdasarkan customer/outlet dan barang.
     * Jika opsi mengikuti_hpp aktif, langsung mengembalikan nilai HPP riil saat ini.
     */
    public static function getHargaB2b($customerId, $barangId): float
    {
        try {
            static::ensureTableExists();

            if ($customerId) {
                $customRow = static::where('customer_id', $customerId)
                    ->where('barang_id', $barangId)
                    ->first();

                if ($customRow) {
                    if ((bool) $customRow->mengikuti_hpp) {
                        return static::getRealtimeHpp($barangId);
                    }
                    if ($customRow->harga_b2b !== null && floatval($customRow->harga_b2b) > 0) {
                        return floatval($customRow->harga_b2b);
                    }
                }
            }

            $barang = MasterBarang::withoutGlobalScopes()->find($barangId);
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

