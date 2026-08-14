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
        Schema::table('master_barang', function (Blueprint $table) {
            $table->integer('minimum_stock_ck')->nullable()->after('minimum_stock');
            $table->integer('minimum_stock_kejingga')->nullable()->after('minimum_stock_ck');
            $table->integer('minimum_stock_gaharu')->nullable()->after('minimum_stock_kejingga');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('master_barang', function (Blueprint $table) {
            $table->dropColumn(['minimum_stock_ck', 'minimum_stock_kejingga', 'minimum_stock_gaharu']);
        });
    }
};
