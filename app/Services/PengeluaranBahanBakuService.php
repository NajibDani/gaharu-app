<?php

namespace App\Services;

use App\Models\PengeluaranBahanBaku;
use App\Models\MasterGudang;
use App\Models\StokGudangBatch;

use App\Services\StockService;
use App\Services\FifoService;

use Illuminate\Support\Facades\DB;

class PengeluaranBahanBakuService
{
    /*
    |--------------------------------------------------------------------------
    | CONSTRUCTOR
    |--------------------------------------------------------------------------
    */

    public function __construct(
        protected StockService $stockService,
        protected FifoService $fifoService
    ) {
    }

    /**
     * APPROVE PENGELUARAN
     */
    public function approve(
        PengeluaranBahanBaku $pengeluaran,
        int $userId
    ) {
        return DB::transaction(function () use (
            $pengeluaran,
            $userId
        ) {
            /*
            |--------------------------------------------------------------------------
            | VALIDASI STATUS
            |--------------------------------------------------------------------------
            */

            if ($pengeluaran->status == 'disetujui' || $pengeluaran->status == 'approved') {
                throw new \Exception(
                    'Pengeluaran sudah disetujui'
                );
            }

            /*
            |--------------------------------------------------------------------------
            | GUDANG ASAL
            |--------------------------------------------------------------------------
            |
            | Untuk transfer antar gudang, stok keluar dari Gudang Utama (pusat pembelian).
            |
            */

            $gudangUtama = MasterGudang::where('nama', 'Gudang Utama')->first();
            $gudangAsalId = $gudangUtama ? $gudangUtama->id : 1;

            /*
            |--------------------------------------------------------------------------
            | LOOP DETAIL BARANG
            |--------------------------------------------------------------------------
            */

            foreach ($pengeluaran->details as $detail) {

                /*
                |--------------------------------------------------------------------------
                | EKSEKUSI TRANSFER BATCH FIFO
                |--------------------------------------------------------------------------
                |
                | 1. Panggil consumeFIFO untuk memotong batch lama dari Gudang Asal
                | 2. Looping layer yang terpotong untuk dimasukkan ke Gudang Tujuan (+ Divisi)
                |
                */

                // Kurangi batch FIFO tertua di Gudang Asal
                $fifoResult = $this->fifoService->consumeFIFO(
                    $detail->barang_id,
                    $detail->qty,
                    $gudangAsalId
                );

                $hppTotal = 0;

                // Buat batch FIFO baru di Gudang Tujuan (+ Divisi) dengan harga modal aslinya
                foreach ($fifoResult as $layer) {
                    $totalHarga = $layer['qty_keluar'] * $layer['harga_per_qty'];
                    $hppTotal  += $totalHarga;
                    
                    // Tarik data batch asal untuk menyalin ID Supplier dan ID Pembelian
                    $originalBatch = StokGudangBatch::find($layer['batch_id']);

                    StokGudangBatch::create([
                        'gudang_id'           => $pengeluaran->gudang_id, // Gudang tujuan
                        'divisi_id'           => $pengeluaran->divisi_id, // Divisi tujuan (Kitchen/Barista/Server)
                        'supplier_id'         => $originalBatch ? $originalBatch->supplier_id : 1,
                        'barang_id'           => $detail->barang_id,
                        'pembelian_id'        => $originalBatch ? $originalBatch->pembelian_id : 1,
                        'pembelian_detail_id' => $originalBatch ? $originalBatch->pembelian_detail_id : 1,
                        'batch_number'        => $layer['batch_number'] . '-MUT', // Beri penanda mutasi
                        'qty_masuk'           => $layer['qty_keluar'],
                        'qty_keluar'          => 0,
                        'qty_sisa'            => $layer['qty_keluar'],
                        'harga_per_qty'       => $layer['harga_per_qty'], // Harga modal ikut terbawa otomatis!
                        'is_habis'            => false,
                    ]);
                }

                // Update detail dengan nominal HPP FIFO yang benar
                $avgHppSatuan = $detail->qty > 0 ? ($hppTotal / $detail->qty) : 0;
                $detail->update([
                    'harga_satuan' => $avgHppSatuan,
                    'total_harga'  => $hppTotal,
                    'hpp_total'    => $hppTotal,
                ]);

                /*
                |--------------------------------------------------------------------------
                | KURANGI STOK SUMMARY GUDANG ASAL
                |--------------------------------------------------------------------------
                */

                $this->stockService->stockOut([
                    'barang_id'       => $detail->barang_id,
                    'gudang_asal_id'  => $gudangAsalId,
                    'qty'             => $detail->qty,
                    'total_harga'     => $hppTotal,
                    'source_type'     => 'pengeluaran_bahan_baku',
                    'source_id'       => $pengeluaran->id,
                    'user_id'         => $userId,
                ]);

                /*
                |--------------------------------------------------------------------------
                | TAMBAH STOK KE GUDANG & DIVISI TUJUAN
                |--------------------------------------------------------------------------
                */

                $this->stockService->stockIn([
                    'barang_id'        => $detail->barang_id,
                    'gudang_tujuan_id' => $pengeluaran->gudang_id,
                    'divisi_tujuan_id' => $pengeluaran->divisi_id,
                    'qty'              => $detail->qty,
                    'total_harga'      => $hppTotal,
                    'source_type'      => 'pengeluaran_bahan_baku',
                    'source_id'        => $pengeluaran->id,
                    'user_id'         => $userId,
                ]);
            }

            /*
            |--------------------------------------------------------------------------
            | UPDATE STATUS
            |--------------------------------------------------------------------------
            */

            $pengeluaran->update([
                'status'      => 'disetujui',
                'approved_by' => $userId,
                'approved_at' => now(),
            ]);

            return $pengeluaran;
        });
    }
}