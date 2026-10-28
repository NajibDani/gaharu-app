<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    /**
     * Sinkronisasi seluruh tanggal transaksi mutasi masuk & keluar Central Kitchen
     * untuk Bahan Setengah Jadi (BSJ) agar 100% mengikuti tanggal permintaan pesanan outlet.
     */
    public function up(): void
    {
        try {
            // 1. Update transaksi_stok masuk ke outlet kitchen untuk transfer BSJ dari Central Kitchen
            DB::update("
                UPDATE transaksi_stok ts
                JOIN alokasi_produksi_pesanan app ON app.produksi_id = ts.source_id AND app.produk_id = ts.barang_id
                JOIN pesanan pes ON pes.id = app.pesanan_id
                JOIN master_barang mb ON mb.id = ts.barang_id
                SET ts.tanggal = CONCAT(DATE(pes.tanggal), ' ', TIME(COALESCE(pes.created_at, '08:00:00')))
                WHERE ts.source_type = 'transfer_ck'
                  AND ts.tipe = 'masuk'
                  AND mb.is_bahan_setengah_jadi = 1
                  AND pes.tanggal IS NOT NULL
            ");

            // 2. Update transaksi_stok keluar dari Central Kitchen untuk transfer BSJ
            DB::update("
                UPDATE transaksi_stok ts
                JOIN alokasi_produksi_pesanan app ON app.produksi_id = ts.source_id AND app.produk_id = ts.barang_id
                JOIN pesanan pes ON pes.id = app.pesanan_id
                JOIN master_barang mb ON mb.id = ts.barang_id
                SET ts.tanggal = CONCAT(DATE(pes.tanggal), ' ', TIME(COALESCE(pes.created_at, '08:00:00')))
                WHERE ts.source_type = 'transfer_ck'
                  AND ts.tipe = 'keluar'
                  AND mb.is_bahan_setengah_jadi = 1
                  AND pes.tanggal IS NOT NULL
            ");

            // 3. Update tanggal batch outlet
            DB::update("
                UPDATE stok_gudang_batch sgb
                JOIN alokasi_produksi_pesanan app ON sgb.barang_id = app.produk_id
                JOIN produksi prd ON prd.id = app.produksi_id
                JOIN pesanan pes ON pes.id = app.pesanan_id
                JOIN master_barang mb ON mb.id = sgb.barang_id
                SET sgb.created_at = CONCAT(DATE(pes.tanggal), ' ', TIME(COALESCE(pes.created_at, '08:00:00')))
                WHERE (sgb.batch_number LIKE CONCAT('%', prd.kode_produksi, '%') OR sgb.batch_number LIKE CONCAT('%-', pes.id, '%'))
                  AND mb.is_bahan_setengah_jadi = 1
                  AND pes.tanggal IS NOT NULL
            ");

            // 4. Update tanggal pengiriman (Surat Jalan DO-CK)
            DB::update("
                UPDATE pengiriman p
                JOIN pesanan pes ON pes.id = p.pesanan_id
                SET p.tanggal_pengiriman = DATE(pes.tanggal)
                WHERE pes.tanggal IS NOT NULL
                  AND p.no_pengiriman LIKE 'DO-CK-%'
            ");

            // 5. Jalankan auto-heal lengkap untuk memverifikasi dan rekonsiliasi seluruh ringkasan stok
            \App\Http\Controllers\StokGudangController::autoHealApprovedCkProductionTransfers();

            Log::info('Migration sync_all_bsj_ck_transfer_dates_to_outlet_order successfully executed.');
        } catch (\Throwable $e) {
            Log::error('Migration sync_all_bsj_ck_transfer_dates_to_outlet_order error: ' . $e->getMessage());
        }
    }

    public function down(): void
    {
        // Data correction migration: no rollback needed
    }
};
