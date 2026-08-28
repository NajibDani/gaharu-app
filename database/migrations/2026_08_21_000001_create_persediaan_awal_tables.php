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
        Schema::create('persediaan_awal', function (Blueprint $table) {
            $table->id();
            $table->string('kode_transaksi')->unique();
            $table->date('tanggal');
            $table->foreignId('gudang_id')->constrained('master_gudang')->onDelete('restrict');
            $table->foreignId('divisi_id')->nullable()->constrained('gudang_divisi')->onDelete('restrict');
            $table->integer('total_item')->default(0);
            $table->decimal('total_qty', 15, 2)->default(0.00);
            $table->decimal('total_nilai', 18, 2)->default(0.00);
            $table->text('keterangan')->nullable();
            $table->string('status')->default('posted');
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
        });

        Schema::create('persediaan_awal_detail', function (Blueprint $table) {
            $table->id();
            $table->foreignId('persediaan_awal_id')->constrained('persediaan_awal')->onDelete('cascade');
            $table->foreignId('barang_id')->constrained('master_barang')->onDelete('restrict');
            $table->decimal('qty', 15, 2)->default(0.00);
            $table->string('satuan', 50)->default('pcs');
            $table->decimal('harga_satuan', 15, 2)->default(0.00);
            $table->decimal('total_nilai', 18, 2)->default(0.00);
            $table->string('batch_number')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('persediaan_awal_detail');
        Schema::dropIfExists('persediaan_awal');
    }
};
