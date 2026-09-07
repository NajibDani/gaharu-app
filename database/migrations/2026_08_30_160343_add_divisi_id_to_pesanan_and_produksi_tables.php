<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('pesanan') && !Schema::hasColumn('pesanan', 'divisi_id')) {
            Schema::table('pesanan', function (Blueprint $table) {
                $table->foreignId('divisi_id')->nullable()->after('gudang_id')->constrained('gudang_divisi')->onDelete('set null');
            });
        }

        if (Schema::hasTable('produksi') && !Schema::hasColumn('produksi', 'divisi_id')) {
            Schema::table('produksi', function (Blueprint $table) {
                $table->foreignId('divisi_id')->nullable()->after('gudang_hasil_id')->constrained('gudang_divisi')->onDelete('set null');
            });
        }

        // Central Kitchen & Cold Kitchen are non-operasional warehouses and do NOT have divisions
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('produksi') && Schema::hasColumn('produksi', 'divisi_id')) {
            Schema::table('produksi', function (Blueprint $table) {
                $table->dropForeign(['divisi_id']);
                $table->dropColumn('divisi_id');
            });
        }

        if (Schema::hasTable('pesanan') && Schema::hasColumn('pesanan', 'divisi_id')) {
            Schema::table('pesanan', function (Blueprint $table) {
                $table->dropForeign(['divisi_id']);
                $table->dropColumn('divisi_id');
            });
        }
    }
};
