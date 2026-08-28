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
        if (!Schema::hasTable('barang_minimum_stock')) {
            Schema::create('barang_minimum_stock', function (Blueprint $table) {
                $table->id();
                $table->foreignId('barang_id')->constrained('master_barang')->onDelete('cascade');
                $table->foreignId('gudang_id')->constrained('master_gudang')->onDelete('cascade');
                $table->foreignId('divisi_id')->nullable()->constrained('gudang_divisi')->onDelete('cascade');
                $table->decimal('minimum_stock', 15, 2)->default(0);
                $table->timestamps();

                $table->unique(['barang_id', 'gudang_id', 'divisi_id'], 'barang_gudang_divisi_min_stock_unique');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('barang_minimum_stock');
    }
};
