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
        Schema::table('pembelian_detail', function (Blueprint $table) {
            if (!Schema::hasColumn('pembelian_detail', 'tanggal_diterima')) {
                $table->dateTime('tanggal_diterima')->nullable()->after('qty_diterima');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pembelian_detail', function (Blueprint $table) {
            if (Schema::hasColumn('pembelian_detail', 'tanggal_diterima')) {
                $table->dropColumn('tanggal_diterima');
            }
        });
    }
};
