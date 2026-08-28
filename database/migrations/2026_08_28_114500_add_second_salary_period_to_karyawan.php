<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('karyawan', function (Blueprint $table) {
            // Periode Gaji 2 (Periode Berikutnya / Reguler)
            $table->date('tanggal_mulai_2')->nullable()->after('tanggal_selesai');
            $table->date('tanggal_selesai_2')->nullable()->after('tanggal_mulai_2');
            $table->decimal('gaji_pokok_2', 15, 2)->nullable()->after('tanggal_selesai_2');
            $table->decimal('uang_makan_2', 15, 2)->nullable()->after('gaji_pokok_2');
            $table->decimal('uang_transport_2', 15, 2)->nullable()->after('uang_makan_2');
        });
    }

    public function down(): void
    {
        Schema::table('karyawan', function (Blueprint $table) {
            $table->dropColumn([
                'tanggal_mulai_2',
                'tanggal_selesai_2',
                'gaji_pokok_2',
                'uang_makan_2',
                'uang_transport_2'
            ]);
        });
    }
};
