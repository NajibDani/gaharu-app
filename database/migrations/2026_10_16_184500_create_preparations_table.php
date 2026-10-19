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
        Schema::create('preparations', function (Blueprint $table) {
            $table->id();
            $table->string('kode', 50)->unique();
            $table->date('tanggal');
            $table->foreignId('gudang_id')->constrained('master_gudang')->onDelete('restrict');
            $table->foreignId('divisi_id')->nullable()->constrained('gudang_divisi')->onDelete('set null');
            $table->enum('status', ['menunggu', 'disetujui', 'ditolak'])->default('menunggu');
            $table->decimal('total_biaya', 15, 4)->default(0);
            $table->text('keterangan')->nullable();
            $table->text('alasan_tolak')->nullable();
            $table->foreignId('created_by')->constrained('users')->onDelete('restrict');
            $table->foreignId('approved_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
        });

        Schema::create('preparation_inputs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('preparation_id')->constrained('preparations')->onDelete('cascade');
            $table->foreignId('barang_id')->constrained('master_barang')->onDelete('restrict');
            $table->decimal('qty', 15, 4);
            $table->string('satuan', 50);
            $table->decimal('konversi', 15, 4)->default(1);
            $table->decimal('qty_base', 15, 4);
            $table->decimal('hpp_satuan', 15, 4)->default(0);
            $table->decimal('total_biaya', 15, 4)->default(0);
            $table->json('fifo_breakdown')->nullable();
            $table->timestamps();
        });

        Schema::create('preparation_outputs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('preparation_id')->constrained('preparations')->onDelete('cascade');
            $table->foreignId('barang_id')->constrained('master_barang')->onDelete('restrict');
            $table->decimal('qty', 15, 4);
            $table->string('satuan', 50);
            $table->decimal('konversi', 15, 4)->default(1);
            $table->decimal('qty_base', 15, 4);
            $table->decimal('hpp_satuan', 15, 4)->default(0);
            $table->decimal('total_nilai', 15, 4)->default(0);
            $table->unsignedBigInteger('batch_id')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('preparation_outputs');
        Schema::dropIfExists('preparation_inputs');
        Schema::dropIfExists('preparations');
    }
};
