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
            if (!Schema::hasColumn('karyawan', 'satuan_gaji_2')) {
                $table->string('satuan_gaji_2', 30)->nullable()->default('Harian')->after('satuan_gaji');
            }
        });

        Schema::table('penggajian', function (Blueprint $table) {
            if (!Schema::hasColumn('penggajian', 'satuan_gaji_2')) {
                $table->string('satuan_gaji_2', 30)->nullable()->default('Harian')->after('satuan_gaji');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('karyawan', function (Blueprint $table) {
            if (Schema::hasColumn('karyawan', 'satuan_gaji_2')) {
                $table->dropColumn('satuan_gaji_2');
            }
        });

        Schema::table('penggajian', function (Blueprint $table) {
            if (Schema::hasColumn('penggajian', 'satuan_gaji_2')) {
                $table->dropColumn('satuan_gaji_2');
            }
        });
    }
};
