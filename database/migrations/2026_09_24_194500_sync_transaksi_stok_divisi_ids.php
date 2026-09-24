<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use App\Models\StokGudang;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Backfill divisi_tujuan_id dari pengeluaran_bahan_baku (PBK Masuk ke Divisi)
        DB::update("
            UPDATE transaksi_stok ts
            JOIN pengeluaran_bahan_baku pbk ON ts.source_id = pbk.id
            SET ts.divisi_tujuan_id = pbk.divisi_id
            WHERE ts.source_type = 'pengeluaran_bahan_baku'
              AND ts.tipe = 'masuk'
              AND pbk.divisi_id IS NOT NULL
              AND (ts.divisi_tujuan_id IS NULL OR ts.divisi_tujuan_id != pbk.divisi_id)
        ");

        // 2. Backfill divisi_asal_id dari pengeluaran_bahan_baku / wasted (PBK Keluar dari Divisi)
        DB::update("
            UPDATE transaksi_stok ts
            JOIN pengeluaran_bahan_baku pbk ON ts.source_id = pbk.id
            SET ts.divisi_asal_id = pbk.divisi_id
            WHERE ts.source_type IN ('pengeluaran_bahan_baku', 'pengeluaran_wasted')
              AND ts.tipe = 'keluar'
              AND pbk.divisi_id IS NOT NULL
              AND ts.gudang_asal_id = pbk.gudang_id
              AND (ts.divisi_asal_id IS NULL OR ts.divisi_asal_id != pbk.divisi_id)
        ");

        // 3. Backfill divisi_tujuan_id dari stock_opname (Surplus Divisi)
        DB::update("
            UPDATE transaksi_stok ts
            JOIN stock_opname so ON ts.source_id = so.id
            SET ts.divisi_tujuan_id = so.divisi_id
            WHERE ts.source_type = 'stock_opname'
              AND ts.tipe = 'masuk'
              AND so.divisi_id IS NOT NULL
              AND (ts.divisi_tujuan_id IS NULL OR ts.divisi_tujuan_id != so.divisi_id)
        ");

        // 4. Backfill divisi_asal_id dari stock_opname (Shortage Divisi)
        DB::update("
            UPDATE transaksi_stok ts
            JOIN stock_opname so ON ts.source_id = so.id
            SET ts.divisi_asal_id = so.divisi_id
            WHERE ts.source_type = 'stock_opname'
              AND ts.tipe = 'keluar'
              AND so.divisi_id IS NOT NULL
              AND (ts.divisi_asal_id IS NULL OR ts.divisi_asal_id != so.divisi_id)
        ");

        // 5. Backfill divisi_tujuan_id dari persediaan_awal (Saldo Awal Divisi)
        DB::update("
            UPDATE transaksi_stok ts
            JOIN persediaan_awal pa ON ts.source_id = pa.id
            SET ts.divisi_tujuan_id = pa.divisi_id
            WHERE ts.source_type = 'persediaan_awal'
              AND ts.tipe = 'masuk'
              AND pa.divisi_id IS NOT NULL
              AND (ts.divisi_tujuan_id IS NULL OR ts.divisi_tujuan_id != pa.divisi_id)
        ");

        // 6. Rekonsiliasi ringkasan stok seluruh gudang dan divisi
        StokGudang::reconcileStockSummary();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No down operation needed
    }
};
