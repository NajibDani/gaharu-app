<?php

namespace App\Services;

use App\Models\StokGudang;
use App\Models\TransaksiStok;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class StockService
{
    /**
     * STOCK MASUK
     * Contoh:
     * - Pembelian
     * - Hasil produksi
     * - Retur customer
     * - Transfer masuk ke divisi
     */
    public function stockIn(array $data)
    {
        return DB::transaction(function () use ($data) {

            $this->increaseStock($data);

            return $this->createTransaction($data, 'masuk');
        });
    }

    /**
     * STOCK KELUAR
     * Contoh:
     * - Penjualan
     * - Pemakaian produksi
     * - Pemakaian operasional / divisi
     */
    public function stockOut(array $data)
    {
        return DB::transaction(function () use ($data) {

            // Validasi stok
            $this->validateStock($data);

            // Kurangi stok
            $this->decreaseStock($data);

            // Catat transaksi
            return $this->createTransaction($data, 'keluar');
        });
    }

    /**
     * TRANSFER STOCK ANTAR GUDANG / DIVISI
     */
    public function transfer(array $data)
    {
        return DB::transaction(function () use ($data) {

            /*
            |--------------------------------------------------------------------------
            | VALIDASI
            |--------------------------------------------------------------------------
            */

            $asalGudang = $data['gudang_asal_id'] ?? null;
            $tujuanGudang = $data['gudang_tujuan_id'] ?? null;
            $asalDivisi = $data['divisi_asal_id'] ?? null;
            $tujuanDivisi = $data['divisi_tujuan_id'] ?? null;

            if ($asalGudang == $tujuanGudang && $asalDivisi == $tujuanDivisi) {
                throw new RuntimeException(
                    'Gudang dan divisi asal tidak boleh sama dengan tujuan'
                );
            }

            /*
            |--------------------------------------------------------------------------
            | VALIDASI STOK
            |--------------------------------------------------------------------------
            */

            $this->validateStock([
                'barang_id'      => $data['barang_id'],
                'gudang_asal_id' => $asalGudang,
                'divisi_asal_id' => $asalDivisi,
                'qty'            => $data['qty'],
            ]);

            /*
            |--------------------------------------------------------------------------
            | KURANGI STOK GUDANG ASAL
            |--------------------------------------------------------------------------
            */

            $this->decreaseStock([
                'barang_id'      => $data['barang_id'],
                'gudang_asal_id' => $asalGudang,
                'divisi_asal_id' => $asalDivisi,
                'qty'            => $data['qty'],
            ]);

            /*
            |--------------------------------------------------------------------------
            | TAMBAH STOK GUDANG TUJUAN
            |--------------------------------------------------------------------------
            */

            $this->increaseStock([
                'barang_id'        => $data['barang_id'],
                'gudang_tujuan_id' => $tujuanGudang,
                'divisi_tujuan_id' => $tujuanDivisi,
                'qty'              => $data['qty'],
            ]);

            /*
            |--------------------------------------------------------------------------
            | TRANSAKSI STOK
            |--------------------------------------------------------------------------
            */

            return $this->createTransaction($data, 'transfer');
        });
    }

    /**
     * TAMBAH STOK
     */
    protected function increaseStock(array $data): void
    {
        $gudangId = $data['gudang_tujuan_id'] ?? $data['gudang_id'] ?? null;
        $divisiId = $data['divisi_tujuan_id'] ?? $data['divisi_id'] ?? null;

        try {
            $query = StokGudang::where('barang_id', $data['barang_id'])
                ->where('gudang_id', $gudangId);

            if ($divisiId) {
                $query->where('divisi_id', $divisiId);
            } else {
                $query->whereNull('divisi_id');
            }

            $stok = $query->lockForUpdate()->first();

            if ($stok) {
                $stok->increment('jumlah', $data['qty']);
            } else {
                StokGudang::create([
                    'barang_id' => $data['barang_id'],
                    'gudang_id' => $gudangId,
                    'divisi_id' => $divisiId,
                    'jumlah'    => $data['qty'],
                ]);
            }
        } catch (\Illuminate\Database\UniqueConstraintViolationException | \Illuminate\Database\QueryException $e) {
            $query = StokGudang::where('barang_id', $data['barang_id'])
                ->where('gudang_id', $gudangId);

            if ($divisiId) {
                $query->where('divisi_id', $divisiId);
            } else {
                $query->whereNull('divisi_id');
            }

            $stok = $query->lockForUpdate()->first();

            if ($stok) {
                $stok->increment('jumlah', $data['qty']);
            } else {
                throw $e;
            }
        }
    }

    /**
     * KURANGI STOK
     */
    protected function decreaseStock(array $data): void
    {
        $gudangId = $data['gudang_asal_id'] ?? $data['gudang_id'] ?? null;
        $divisiId = $data['divisi_asal_id'] ?? $data['divisi_id'] ?? null;

        $query = StokGudang::where('barang_id', $data['barang_id'])
            ->where('gudang_id', $gudangId);

        if ($divisiId) {
            $query->where('divisi_id', $divisiId);
        } else {
            $query->whereNull('divisi_id');
        }

        $stok = $query->lockForUpdate()->first();

        if (!$stok) {
            throw new RuntimeException('Stok tidak ditemukan');
        }

        if ($stok->jumlah < $data['qty']) {
            throw new RuntimeException('Stok tidak cukup');
        }

        $stok->decrement('jumlah', $data['qty']);
    }

    /**
     * VALIDASI STOK
     */
    protected function validateStock(array $data): void
    {
        $gudangId = $data['gudang_asal_id'] ?? $data['gudang_id'] ?? null;
        $divisiId = $data['divisi_asal_id'] ?? $data['divisi_id'] ?? null;

        $query = StokGudang::where('barang_id', $data['barang_id'])
            ->where('gudang_id', $gudangId);

        if ($divisiId) {
            $query->where('divisi_id', $divisiId);
        } else {
            $query->whereNull('divisi_id');
        }

        $stok = $query->first();

        if (!$stok) {
            throw new RuntimeException('Stok tidak ditemukan');
        }

        if ($stok->jumlah < $data['qty']) {
            throw new RuntimeException('Stok tidak cukup');
        }
    }

    /**
     * CATAT TRANSAKSI STOK
     */
    protected function createTransaction(
        array $data,
        string $tipe
    ) {
        return TransaksiStok::create([
            'tanggal'          => now(),
            'tipe'             => $tipe,
            'source_type'      => $data['source_type'] ?? null,
            'source_id'        => $data['source_id'] ?? null,
            'gudang_asal_id'   => $data['gudang_asal_id'] ?? null,
            'divisi_asal_id'   => $data['divisi_asal_id'] ?? null,
            'gudang_tujuan_id' => $data['gudang_tujuan_id'] ?? null,
            'divisi_tujuan_id' => $data['divisi_tujuan_id'] ?? null,
            'barang_id'        => $data['barang_id'],
            'qty'              => $data['qty'],
            'total_harga'      => $data['total_harga'] ?? 0,
            'created_by'       => $data['user_id'],
        ]);
    }
}