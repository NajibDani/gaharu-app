<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Membuat pembelian_id dan pembelian_detail_id nullable
     * agar batch saldo awal (SA-*) bisa di-update tanpa error constraint.
     */
    public function up(): void
    {
        Schema::table('stok_gudang_batch', function (Blueprint $table) {
            // Drop foreign key constraints dulu sebelum mengubah kolom
            $table->dropForeign(['pembelian_id']);
            $table->dropForeign(['pembelian_detail_id']);

            // Ubah kolom menjadi nullable
            $table->unsignedBigInteger('pembelian_id')->nullable()->change();
            $table->unsignedBigInteger('pembelian_detail_id')->nullable()->change();

            // Re-tambahkan foreign key dengan nullable
            $table->foreign('pembelian_id')->references('id')->on('pembelian')->nullOnDelete();
            $table->foreign('pembelian_detail_id')->references('id')->on('pembelian_detail')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stok_gudang_batch', function (Blueprint $table) {
            $table->dropForeign(['pembelian_id']);
            $table->dropForeign(['pembelian_detail_id']);

            $table->unsignedBigInteger('pembelian_id')->nullable(false)->change();
            $table->unsignedBigInteger('pembelian_detail_id')->nullable(false)->change();

            $table->foreign('pembelian_id')->references('id')->on('pembelian');
            $table->foreign('pembelian_detail_id')->references('id')->on('pembelian_detail');
        });
    }
};
