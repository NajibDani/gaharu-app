<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use App\Models\MasterBarang;
use App\Services\FifoService;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Modify columns in stok_gudang_batch to be nullable (not direct purchase)
        if (Schema::getConnection()->getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE stok_gudang_batch MODIFY pembelian_id bigint(20) unsigned NULL");
            DB::statement("ALTER TABLE stok_gudang_batch MODIFY pembelian_detail_id bigint(20) unsigned NULL");
            DB::statement("ALTER TABLE stok_gudang_batch MODIFY supplier_id bigint(20) unsigned NULL");
        }

        // 2. Break fake relation pembelian_detail_id = 1 on Saldo Awal batches (SA-*)
        DB::table('stok_gudang_batch')
            ->where('batch_number', 'like', 'SA-%')
            ->update([
                'pembelian_id' => null,
                'pembelian_detail_id' => null,
            ]);

        // 3. Restore quantity and price_per_qty for all Saldo Awal batches from persediaan_awal_detail
        MasterBarang::autoHealSaldoAwalBatches();

        // 4. Restore quantity and price_per_qty for mutation batches (*-MUT) that were distorted
        MasterBarang::autoHealMutasiBatches();

        // 5. Fix original purchase batches that were never converted
        MasterBarang::autoHealUnconvertedPembelianBatches();

        // 6. Resync HPP references for all master_barang according to active FIFO batches
        try {
            app(FifoService::class)->syncAllBarangHpp();
        } catch (\Throwable $e) {
            // Ignore during migration if any dependency isn't available
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert nullable columns back if needed
    }
};
