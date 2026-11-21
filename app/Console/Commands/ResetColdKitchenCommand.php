<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\ColdKitchenResetService;

class ResetColdKitchenCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'coldkitchen:reset-all {--before= : Hanya hapus transaksi sebelum tanggal YYYY-MM-DD (cth: 2026-11-20)} {--force : Lewati konfirmasi}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Hapus transaksi Cold Kitchen (Permintaan/Pesanan B2B, Work Order, dan Hasil Produksi) seluruhnya atau sebelum tanggal cut-off';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $cutoffDate = $this->option('before');

        if (!$this->option('force')) {
            $msg = $cutoffDate
                ? "PERINGATAN: Perintah ini akan menghapus seluruh data Permintaan Cold Kitchen, Work Order, dan Hasil Produksi yang dibuat SEBELUM tanggal {$cutoffDate}. Lanjutkan?"
                : 'PERINGATAN: Perintah ini akan menghapus SELURUH data Permintaan Pesanan, Work Order, dan Hasil Produksi Cold Kitchen. Lanjutkan?';

            if (!$this->confirm($msg)) {
                $this->info('Operasi dibatalkan.');
                return 0;
            }
        }

        $this->info($cutoffDate ? "Menjalankan pembersihan transaksi Cold Kitchen sebelum {$cutoffDate}..." : 'Menjalankan reset seluruh transaksi Cold Kitchen...');

        $result = $cutoffDate
            ? ColdKitchenResetService::resetBeforeDate($cutoffDate)
            : ColdKitchenResetService::resetAll();

        $this->info('Pembersihan Cold Kitchen Berhasil:');
        $this->line("- Pesanan Cold Kitchen terhapus: {$result['pesanan_deleted']}");
        $this->line("- Work Order Cold Kitchen terhapus: {$result['wo_deleted']}");
        $this->line("- Produksi Cold Kitchen terhapus: {$result['produksi_deleted']}");
        $this->line("- Pengiriman terhapus: {$result['pengiriman_deleted']}");
        $this->line("- Pembayaran terhapus: {$result['pembayaran_deleted']}");

        return 0;
    }
}
