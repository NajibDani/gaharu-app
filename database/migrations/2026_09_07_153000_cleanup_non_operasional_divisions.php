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
        // 1. Pastikan gudang non-operasional (Central Kitchen, Cold Kitchen, Gudang Utama) tidak memiliki divisi
        if (Schema::hasTable('gudang_divisi') && Schema::hasTable('master_gudang')) {
            $nonOperasionalIds = DB::table('master_gudang')
                ->whereRaw("LOWER(kategori) != 'operasional'")
                ->pluck('id');

            if ($nonOperasionalIds->isNotEmpty()) {
                DB::table('gudang_divisi')
                    ->whereIn('gudang_id', $nonOperasionalIds)
                    ->delete();
            }

            // 2. Sesuaikan nama Gudang Cold Kitchen jika masih Gudang B2B
            DB::table('master_gudang')
                ->where('id', 4)
                ->where('nama', 'Gudang B2B')
                ->update(['nama' => 'Gudang Cold Kitchen']);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No rollback needed
    }
};
