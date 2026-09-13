<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('fifo:sync-hpp {barang_id? : ID Master Barang (opsional, kosongkan untuk semua)}', function ($barang_id = null) {
    $fifoService = app(\App\Services\FifoService::class);

    if ($barang_id) {
        $barang = \App\Models\MasterBarang::withoutGlobalScopes()->find($barang_id);
        if (!$barang) {
            $this->error("Barang dengan ID {$barang_id} tidak ditemukan.");
            return 1;
        }

        $oldHpp = (float) $barang->hpp_referensi;
        $newHpp = $fifoService->syncBarangHpp((int) $barang_id);

        $this->info("Berhasil sinkronisasi HPP FIFO untuk [{$barang->kode_barang}] {$barang->nama}:");
        $this->line(" - HPP Sebelumnya : Rp " . number_format($oldHpp, 2, ',', '.'));
        $this->line(" - HPP Baru (FIFO): Rp " . number_format($newHpp, 2, ',', '.'));
        return 0;
    }

    $this->info("Memulai sinkronisasi HPP FIFO untuk semua barang...");
    $synced = $fifoService->syncAllBarangHpp();

    if (empty($synced)) {
        $this->info("Semua barang sudah sesuai dengan HPP FIFO aktif.");
    } else {
        $this->table(
            ['ID', 'Kode Barang', 'Nama Barang', 'HPP Lama', 'HPP Baru (FIFO)'],
            collect($synced)->map(fn($item, $id) => [
                $id,
                $item['kode'],
                $item['nama'],
                'Rp ' . number_format($item['old_hpp'], 2, ',', '.'),
                'Rp ' . number_format($item['new_hpp'], 2, ',', '.'),
            ])
        );
        $this->info("Total " . count($synced) . " barang berhasil diperbarui.");
    }

    return 0;
})->purpose('Sinkronisasi ulang nilai HPP Master Barang berdasarkan batch FIFO yang aktif');

