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
        Schema::create('resep_bahanbaku_alternatif', function (Blueprint $table) {
            $table->id();
            $table->foreignId('resep_bahanbaku_id')->constrained('resep_bahanbaku')->onDelete('cascade');
            $table->foreignId('bahan_id')->constrained('master_barang')->onDelete('cascade');
            $table->integer('prioritas')->default(2); // 2, 3, dst.
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('resep_bahanbaku_alternatif');
    }
};
