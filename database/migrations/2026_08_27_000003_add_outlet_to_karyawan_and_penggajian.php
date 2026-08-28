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
            if (!Schema::hasColumn('karyawan', 'outlet')) {
                $table->string('outlet')->default('Gaharu')->after('departemen');
            }
        });

        Schema::table('penggajian', function (Blueprint $table) {
            if (!Schema::hasColumn('penggajian', 'outlet')) {
                $table->string('outlet')->default('Gaharu')->after('karyawan_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('karyawan', function (Blueprint $table) {
            if (Schema::hasColumn('karyawan', 'outlet')) {
                $table->dropColumn('outlet');
            }
        });

        Schema::table('penggajian', function (Blueprint $table) {
            if (Schema::hasColumn('penggajian', 'outlet')) {
                $table->dropColumn('outlet');
            }
        });
    }
};
