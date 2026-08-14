<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('master_barang', function (Blueprint $table) {
            $table->boolean('is_bahan_baku')->default(false)->change();
            $table->boolean('is_barang_jadi')->default(false)->change();
            $table->boolean('is_operational')->default(false)->change();
            $table->boolean('is_direct_consumption')->default(false)->change();
            $table->decimal('harga_jual_b2b', 15, 2)->default(0)->change();
            $table->decimal('harga_jual_pos', 15, 2)->default(0)->change();
            $table->decimal('hpp_referensi', 15, 2)->default(0)->change();
        });
    }

    public function down(): void
    {
        // No-op
    }
};
