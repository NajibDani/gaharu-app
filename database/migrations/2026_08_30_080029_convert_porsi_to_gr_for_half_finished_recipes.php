<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Update master_barang where is_bahan_setengah_jadi = 1 and satuan = 'PORSI'
        DB::table('master_barang')
            ->where('is_bahan_setengah_jadi', 1)
            ->where(function($q) {
                $q->where('satuan', 'PORSI')->orWhere('satuan', 'porsi');
            })
            ->update(['satuan' => 'GR']);

        // 2. Update resep_btkl_bop where product is is_bahan_setengah_jadi and satuan_output = 'PORSI'
        $bsjIds = DB::table('master_barang')->where('is_bahan_setengah_jadi', 1)->pluck('id')->toArray();
        if (!empty($bsjIds)) {
            DB::table('resep_btkl_bop')
                ->whereIn('produk_id', $bsjIds)
                ->where(function($q) {
                    $q->where('satuan_output', 'PORSI')->orWhere('satuan_output', 'porsi');
                })
                ->update(['satuan_output' => 'GR']);
        }

        // 3. Update resep_bahanbaku where bahan_id is is_bahan_setengah_jadi and satuan = 'PORSI'
        if (!empty($bsjIds)) {
            DB::table('resep_bahanbaku')
                ->whereIn('bahan_id', $bsjIds)
                ->where(function($q) {
                    $q->where('satuan', 'PORSI')->orWhere('satuan', 'porsi');
                })
                ->update(['satuan' => 'GR']);
        }

        // 4. Update pengeluaran_bahan_baku_detail where barang_id is is_bahan_setengah_jadi and satuan = 'PORSI'
        if (!empty($bsjIds)) {
            DB::table('pengeluaran_bahan_baku_detail')
                ->whereIn('barang_id', $bsjIds)
                ->where(function($q) {
                    $q->where('satuan', 'PORSI')->orWhere('satuan', 'porsi');
                })
                ->update(['satuan' => 'GR']);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Reverting this migration is not strictly required as PORSI is deprecated, but we leave it empty.
    }
};
