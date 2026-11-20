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
    protected $signature = 'coldkitchen:reset-all {--force : Lewati konfirmasi}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Hapus semua transaksi Cold Kitchen (Permintaan/Pesanan B2B, Work Order, dan Hasil Produksi) agar bersih total 0 transaksi';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        if (!$this->option('force')) {
            if (!$this->confirm('PERINGATAN: Perintah ini akan menghapus seluruh data Permintaan Pesanan, Work Order, dan Hasil Produksi Cold Kitchen. Lanjutkan?')) {
                $this->info('Operasi dibatalkan.');
                return 0;
            }
        }

        $this->info('Menjalankan reset transaksi Cold Kitchen...');

        $result = ColdKitchenResetService::resetAll();

        $this->info('Reset Cold Kitchen Berhasil:');
        $this->line("- Pesanan Cold Kitchen terhapus: {$result['pesanan_deleted']}");
        $this->line("- Work Order Cold Kitchen terhapus: {$result['wo_deleted']}");
        $this->line("- Produksi Cold Kitchen terhapus: {$result['produksi_deleted']}");
        $this->line("- Pengiriman terhapus: {$result['pengiriman_deleted']}");
        $this->line("- Pembayaran terhapus: {$result['pembayaran_deleted']}");

        return 0;
    }
}
