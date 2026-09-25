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
        Schema::create('pembelian', function (Blueprint $table) {
            $table->id();
            $table->string('kode_pembelian')->unique();
            $table->foreignId('supplier_id')->constrained('suppliers');
            $table->foreignId('gudang_id')->constrained('master_gudang')->onDelete('restrict');
            $table->dateTime('tanggal');
            $table->decimal('total', 15, 2);
            $table->enum('metode_pembayaran', ['cod', 'termin', 'dp'])->nullable();
            $table->date('tanggal_jatuh_tempo')->nullable();
            $table->tinyInteger('persen_dp')->nullable();
            $table->decimal('nominal_dp', 15, 2)->nullable();
            $table->date('tanggal_pelunasan')->nullable();
            $table->text('catatan_pembayaran')->nullable();
            $table->unsignedBigInteger('dicatat_oleh')->nullable();
            $table->timestamp('dicatat_pada')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->boolean('is_diterima')->default(false);
            $table->boolean('is_lunas')->default(false);
            $table->timestamp('lunas_at')->nullable();
            $table->decimal('nominal_pelunasan', 15, 2)->nullable();
            $table->text('catatan_pelunasan')->nullable();
            $table->timestamp('diterima_at')->nullable();
            $table->unsignedBigInteger('diterima_oleh')->nullable();
        });

        Schema::create('pembelian_detail', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pembelian_id')->constrained('pembelian');
            $table->foreignId('barang_id')->constrained('master_barang');
            $table->decimal('qty', 15, 2);
            $table->decimal('harga', 15, 2);
            $table->decimal('harga_per_qty', 15, 2)->nullable();
            $table->string('batch_number')->nullable();
        });

        Schema::create('transaksi_stok', function (Blueprint $table) {
            $table->id();
            $table->dateTime('tanggal');
            $table->string('tipe');
            $table->string('source_type');
            $table->integer('source_id');
            $table->foreignId('gudang_asal_id')->nullable()->constrained('master_gudang');
            $table->foreignId('gudang_tujuan_id')->nullable()->constrained('master_gudang');
            $table->foreignId('barang_id')->constrained('master_barang');
            $table->decimal('qty', 15, 2);
            $table->decimal('total_harga', 15, 2);
            $table->foreignId('created_by')->constrained('users');
        });

        Schema::create('fifo_layers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('barang_id')->constrained('master_barang');
            $table->foreignId('gudang_id')->constrained('master_gudang');
            $table->decimal('qty_masuk', 15, 2);
            $table->decimal('qty_sisa', 15, 2);
            $table->decimal('harga_per_unit', 15, 2);
            $table->string('batch_number')->nullable();
            $table->dateTime('tanggal_masuk');
            $table->foreignId('referensi_transaksi')->constrained('transaksi_stok');
        });

        Schema::create('stok_gudang', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gudang_id')->constrained('master_gudang');
            $table->foreignId('barang_id')->constrained('master_barang');
            $table->decimal('jumlah', 15, 2)->default(0.00);
            $table->timestamps();

            $table->unique(['gudang_id', 'barang_id'], 'stok_gudang_gudang_barang_unique');
        });

        Schema::create('stok_gudang_batch', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gudang_id')->constrained('master_gudang');
            $table->foreignId('supplier_id')->constrained('suppliers');
            $table->foreignId('barang_id')->constrained('master_barang');
            $table->foreignId('pembelian_id')->nullable()->constrained('pembelian');
            $table->foreignId('pembelian_detail_id')->nullable()->constrained('pembelian_detail');
            $table->string('batch_number');
            $table->decimal('qty_masuk', 15, 2);
            $table->decimal('qty_keluar', 15, 2)->default(0.00);
            $table->decimal('qty_sisa', 15, 2);
            $table->decimal('harga_per_qty', 15, 2);
            $table->boolean('is_habis')->default(false)->index();
            $table->timestamps();

            $table->index(['gudang_id', 'barang_id'], 'stok_batch_gudang_barang_idx');
            $table->index('qty_sisa', 'stok_batch_qty_sisa_idx');
        });

        Schema::create('pengeluaran_bahan_baku', function (Blueprint $table) {
            $table->id();
            $table->string('kode_pengeluaran')->unique();
            $table->dateTime('tanggal');
            $table->foreignId('gudang_id')->constrained('master_gudang');
            $table->string('status')->default('draft');
            $table->text('keterangan')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->dateTime('approved_at')->nullable();
            $table->timestamps();
        });

        Schema::create('pengeluaran_bahan_baku_detail', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pengeluaran_id')->constrained('pengeluaran_bahan_baku')->onDelete('cascade');
            $table->foreignId('barang_id')->constrained('master_barang');
            $table->decimal('qty', 15, 2);
            $table->string('satuan');
            $table->decimal('harga_satuan', 15, 2)->default(0.00);
            $table->decimal('total_harga', 15, 2)->default(0.00);
            $table->decimal('hpp_total', 18, 2)->default(0.00);
            $table->timestamps();
        });

        Schema::create('pengeluaran_bahan_baku_fifo', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pengeluaran_id');
            $table->unsignedBigInteger('detail_id');
            $table->unsignedBigInteger('batch_id');
            $table->string('batch_number');
            $table->decimal('qty_keluar', 15, 2);
            $table->decimal('harga_per_qty', 18, 2);
            $table->decimal('total_harga', 18, 2);
            $table->timestamps();
        });

        Schema::create('stock_opname', function (Blueprint $table) {
            $table->id();
            $table->string('kode_opname')->unique();
            $table->dateTime('tanggal');
            $table->foreignId('gudang_id')->constrained('master_gudang');
            $table->enum('status', ['draft', 'approved'])->default('draft');
            $table->text('keterangan')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
        });

        Schema::create('stock_opname_detail', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_opname_id')->constrained('stock_opname')->onDelete('cascade');
            $table->foreignId('barang_id')->constrained('master_barang');
            $table->decimal('stok_sistem', 15, 2)->default(0.00);
            $table->decimal('stok_fisik', 15, 2)->default(0.00);
            $table->decimal('selisih', 15, 2)->default(0.00);
            $table->decimal('nilai_selisih', 18, 2)->default(0.00);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_opname_detail');
        Schema::dropIfExists('stock_opname');
        Schema::dropIfExists('pengeluaran_bahan_baku_fifo');
        Schema::dropIfExists('pengeluaran_bahan_baku_detail');
        Schema::dropIfExists('pengeluaran_bahan_baku');
        Schema::dropIfExists('stok_gudang_batch');
        Schema::dropIfExists('stok_gudang');
        Schema::dropIfExists('fifo_layers');
        Schema::dropIfExists('transaksi_stok');
        Schema::dropIfExists('pembelian_detail');
        Schema::dropIfExists('pembelian');
    }
};
