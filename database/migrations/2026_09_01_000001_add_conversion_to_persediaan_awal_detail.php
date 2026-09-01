<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('persediaan_awal_detail', function (Blueprint $table) {
            $table->string('satuan_pembelian')->nullable()->after('satuan');
            $table->decimal('konversi_pembelian', 15, 2)->default(1.00)->after('satuan_pembelian');
            $table->decimal('qty_pembelian', 15, 2)->nullable()->after('konversi_pembelian');
            $table->decimal('harga_pembelian', 15, 2)->nullable()->after('qty_pembelian');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('persediaan_awal_detail', function (Blueprint $table) {
            $table->dropColumn(['satuan_pembelian', 'konversi_pembelian', 'qty_pembelian', 'harga_pembelian']);
        });
    }
};
