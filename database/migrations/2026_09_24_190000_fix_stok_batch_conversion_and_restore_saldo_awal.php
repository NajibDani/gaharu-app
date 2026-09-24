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
        // 1. Modifikasi kolom di stok_gudang_batch agar nullable (bukan pembelian langsung)
        DB::statement("ALTER TABLE stok_gudang_batch MODIFY pembelian_id bigint(20) unsigned NULL");
        DB::statement("ALTER TABLE stok_gudang_batch MODIFY pembelian_detail_id bigint(20) unsigned NULL");
        DB::statement("ALTER TABLE stok_gudang_batch MODIFY supplier_id bigint(20) unsigned NULL");

        // 2. Putuskan relasi palsu pembelian_detail_id = 1 pada batch Saldo Awal (SA-*)
        DB::table('stok_gudang_batch')
            ->where('batch_number', 'like', 'SA-%')
            ->update([
                'pembelian_id'        => null,
                'pembelian_detail_id' => null,
            ]);

        // 3. Pulihkan kuantitas dan harga_per_qty seluruh batch Saldo Awal (SA-*) dari persediaan_awal_detail
        MasterBarang::autoHealSaldoAwalBatches();

        // 4. Pulihkan kuantitas dan harga_per_qty batch mutasi (*-MUT) yang sempat terdistorsi
        MasterBarang::autoHealMutasiBatches();

        // 5. Jalankan perbaikan batch pembelian asli yang benar-benar belum terkonversi
        MasterBarang::autoHealUnconvertedPembelianBatches();

        // 6. Sinkronisasi ulang HPP referensi seluruh master_barang sesuai batch FIFO aktif
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
