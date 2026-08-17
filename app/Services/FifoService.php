<?php

namespace App\Services;

use App\Models\Pembelian;
use App\Models\PembelianDetail;
use App\Models\StokGudangBatch;
use Illuminate\Support\Facades\DB;

class FifoService
{
    /*
    |--------------------------------------------------------------------------
    | CREATE BATCH SAAT PEMBELIAN
    |--------------------------------------------------------------------------
    |
    | Setiap pembelian akan membuat batch FIFO baru.
    |
    */

    public function createBatchStock(
        Pembelian $pembelian,
        PembelianDetail $detail
    ): void {

        StokGudangBatch::create([
            'gudang_id'           => $pembelian->gudang_id,
            'divisi_id'           => null,
            'supplier_id'         => $pembelian->supplier_id,
            'barang_id'           => $detail->barang_id,
            'pembelian_id'        => $pembelian->id,
            'pembelian_detail_id' => $detail->id,
            'batch_number'        => $detail->batch_number,
            'qty_masuk'           => $detail->qty_diterima ?? $detail->qty,
            'qty_keluar'          => 0,
            'qty_sisa'            => $detail->qty_diterima ?? $detail->qty,
            'harga_per_qty'       => $detail->harga_per_qty,
            'is_habis'            => false,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | FIFO CONSUME
    |--------------------------------------------------------------------------
    |
    | Mengurangi stok berdasarkan batch tertua.
    |
    | Parameter $allowNegative:
    | - false (default) : throw Exception jika stok tidak cukup
    | - true            : lanjutkan meski stok kurang (untuk Stock Opname),
    |                     sisa qty yang tidak ada batch-nya akan menggunakan
    |                     harga fallback (avg historis / hpp_referensi)
    |
    | Parameter $divisiId (optional):
    | - null            : konsumsi dari batch umum / tanpa filter divisi
    | - int             : konsumsi dari batch spesifik divisi terkait
    |
    */

    public function consumeFIFO(
        int $barangId,
        float $qtyKeluar,
        int $gudangId,
        bool $allowNegative = false,
        ?int $divisiId = null
    ): array {

        /*
        |--------------------------------------------------------------------------
        | AMBIL BATCH FIFO
        |--------------------------------------------------------------------------
        */

        $query = StokGudangBatch::where('barang_id', $barangId)
            ->where('gudang_id', $gudangId)
            ->where('qty_sisa', '>', 0)
            ->where('is_habis', false);

        if ($divisiId) {
            $query->where('divisi_id', $divisiId);
        }

        $batches = $query->orderBy('id')->get();

        /*
        |--------------------------------------------------------------------------
        | VALIDASI STOK FIFO
        |--------------------------------------------------------------------------
        */

        $totalSisa = $batches->sum('qty_sisa');

        if ($totalSisa < $qtyKeluar && !$allowNegative) {
            throw new \Exception(
                'Stok FIFO tidak mencukupi. Tersedia: ' . $totalSisa . ', Dibutuhkan: ' . $qtyKeluar . '.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | FIFO LOOP
        |--------------------------------------------------------------------------
        */

        $sisaPermintaan = $qtyKeluar;
        $result = [];

        foreach ($batches as $batch) {
            if ($sisaPermintaan <= 0) {
                break;
            }

            $ambilQty = min(
                $batch->qty_sisa,
                $sisaPermintaan
            );

            $batch->qty_keluar += $ambilQty;
            $batch->qty_sisa -= $ambilQty;

            if ($batch->qty_sisa <= 0) {
                $batch->qty_sisa = 0;
                $batch->is_habis = true;
            }

            $batch->save();

            $result[] = [
                'batch_id'      => $batch->id,
                'batch_number'  => $batch->batch_number,
                'qty_keluar'    => $ambilQty,
                'harga_per_qty' => $batch->harga_per_qty,
            ];

            $sisaPermintaan -= $ambilQty;
        }

        /*
        |--------------------------------------------------------------------------
        | FALLBACK: SISA QTY TIDAK ADA BATCH-NYA (allowNegative = true)
        |--------------------------------------------------------------------------
        */

        if ($sisaPermintaan > 0 && $allowNegative) {
            $fallbackQuery = DB::table('stok_gudang_batch')
                ->where('gudang_id', $gudangId)
                ->where('barang_id', $barangId);

            if ($divisiId) {
                $fallbackQuery->where('divisi_id', $divisiId);
            }

            $hargaFallback = $fallbackQuery->avg('harga_per_qty');

            if (!$hargaFallback) {
                $hargaFallback = DB::table('stok_gudang_batch')
                    ->where('gudang_id', $gudangId)
                    ->where('barang_id', $barangId)
                    ->avg('harga_per_qty');
            }

            if (!$hargaFallback) {
                $hargaFallback = DB::table('master_barang')
                    ->where('id', $barangId)
                    ->value('hpp_referensi') ?? 0;
            }

            $result[] = [
                'batch_id'      => null,
                'batch_number'  => 'FALLBACK-OPNAME',
                'qty_keluar'    => $sisaPermintaan,
                'harga_per_qty' => (float) $hargaFallback,
            ];
        }

        return $result;
    }

    public function getEstimatedHargaFIFO(int $barangId, float $qtyKeluar, int $gudangId, ?int $divisiId = null): array
    {
        $query = StokGudangBatch::where('barang_id', $barangId)
            ->where('gudang_id', $gudangId)
            ->where('qty_sisa', '>', 0)
            ->where('is_habis', false);

        if ($divisiId) {
            $query->where('divisi_id', $divisiId);
        }

        $batches = $query->orderBy('id')->get();

        $sisaPermintaan = $qtyKeluar;
        $totalHpp = 0;

        foreach ($batches as $batch) {
            if ($sisaPermintaan <= 0) {
                break;
            }
            $ambilQty = min($batch->qty_sisa, $sisaPermintaan);
            $totalHpp += $ambilQty * $batch->harga_per_qty;
            $sisaPermintaan -= $ambilQty;
        }

        if ($sisaPermintaan > 0) {
            $fbQuery = DB::table('stok_gudang_batch')
                ->where('barang_id', $barangId)
                ->where('gudang_id', $gudangId);

            if ($divisiId) {
                $fbQuery->where('divisi_id', $divisiId);
            }

            $hargaFallback = $fbQuery->avg('harga_per_qty');

            if (!$hargaFallback) {
                $hargaFallback = DB::table('stok_gudang_batch')
                    ->where('barang_id', $barangId)
                    ->where('gudang_id', $gudangId)
                    ->avg('harga_per_qty');
            }

            if (!$hargaFallback) {
                $hargaFallback = DB::table('master_barang')
                    ->where('id', $barangId)
                    ->value('hpp_referensi') ?? 0;
            }

            $totalHpp += $sisaPermintaan * $hargaFallback;
        }

        $hargaSatuan = $qtyKeluar > 0 ? ($totalHpp / $qtyKeluar) : 0;

        return [
            'harga_satuan' => $hargaSatuan,
            'total_harga'  => $totalHpp
        ];
    }
}