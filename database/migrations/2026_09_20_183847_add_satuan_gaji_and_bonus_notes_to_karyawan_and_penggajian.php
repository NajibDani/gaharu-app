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
        Schema::table('karyawan', function (Blueprint $table) {
            $table->string('satuan_gaji', 30)->default('Harian')->after('jenis_tenaga_kerja');
        });

        Schema::table('penggajian', function (Blueprint $table) {
            $table->string('satuan_gaji', 30)->default('Harian')->after('hari_kerja');
            $table->string('catatan_bonus_target', 255)->nullable()->after('bonus_target');
            $table->string('catatan_bonus_tanggal_merah', 255)->nullable()->after('bonus_tanggal_merah');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('karyawan', function (Blueprint $table) {
            $table->dropColumn('satuan_gaji');
        });

        Schema::table('penggajian', function (Blueprint $table) {
            $table->dropColumn(['satuan_gaji', 'catatan_bonus_target', 'catatan_bonus_tanggal_merah']);
        });
    }
};
