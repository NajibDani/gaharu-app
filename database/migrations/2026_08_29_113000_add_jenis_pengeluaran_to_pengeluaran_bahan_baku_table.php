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
        if (Schema::hasTable('pengeluaran_bahan_baku')) {
            Schema::table('pengeluaran_bahan_baku', function (Blueprint $table) {
                if (!Schema::hasColumn('pengeluaran_bahan_baku', 'jenis_pengeluaran')) {
                    $table->string('jenis_pengeluaran')->default('transfer')->after('divisi_id');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('pengeluaran_bahan_baku') && Schema::hasColumn('pengeluaran_bahan_baku', 'jenis_pengeluaran')) {
            Schema::table('pengeluaran_bahan_baku', function (Blueprint $table) {
                $table->dropColumn('jenis_pengeluaran');
            });
        }
    }
};
