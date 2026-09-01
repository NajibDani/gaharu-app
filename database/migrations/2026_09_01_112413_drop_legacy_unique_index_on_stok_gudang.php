<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('stok_gudang')) {
            try {
                DB::statement('ALTER TABLE stok_gudang DROP INDEX stok_gudang_gudang_barang_unique');
            } catch (\Exception $e) {
                // Ignore if already dropped
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('stok_gudang')) {
            try {
                Schema::table('stok_gudang', function (Blueprint $table) {
                    $table->unique(['gudang_id', 'barang_id'], 'stok_gudang_gudang_barang_unique');
                });
            } catch (\Exception $e) {
                // Ignore
            }
        }
    }
};

