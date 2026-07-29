<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('master_barang', function (Blueprint $table) {
            $table->string('satuan_pembelian')->nullable()->after('satuan');
            $table->decimal('konversi_pembelian', 15, 2)->default(1.00)->after('satuan_pembelian');
        });

        Schema::table('pembelian_detail', function (Blueprint $table) {
            $table->string('satuan_pembelian')->nullable()->after('barang_id');
            $table->decimal('konversi_pembelian', 15, 2)->default(1.00)->after('satuan_pembelian');
        });
    }

    public function down(): void
    {
        Schema::table('master_barang', function (Blueprint $table) {
            $table->dropColumn(['satuan_pembelian', 'konversi_pembelian']);
        });

        Schema::table('pembelian_detail', function (Blueprint $table) {
            $table->dropColumn(['satuan_pembelian', 'konversi_pembelian']);
        });
    }
};
