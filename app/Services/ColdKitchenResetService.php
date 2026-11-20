<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use App\Models\StokGudang;
use App\Models\StokGudangBatch;
use App\Models\TransaksiStok;

class ColdKitchenResetService
{
    /**
     * Hapus SEMUA transaksi Cold Kitchen:
     * - Permintaan Cold Kitchen (Pesanan B2B, detail, pembayaran, pengiriman)
     * - Work Orders Cold Kitchen (WO & detail)
     * - Hasil Produksi Cold Kitchen (Produksi, detail, alokasi, transaksi stok, batch)
     */
    public static function resetAll(): array
    {
        return DB::transaction(function () {
            // 1. Ambil seluruh ID Pesanan Cold Kitchen (B2B / tipe null)
            $coldPesananIds = DB::table('pesanan')
                ->where(function ($q) {
                    $q->where('tipe_pesanan', 'b2b')
                      ->orWhereNull('tipe_pesanan');
                })
                ->pluck('id')
                ->toArray();

            // Ambil ID yang milik Central Kitchen agar TIDAK tersentuh sama sekali
            $ckPesananIds = DB::table('pesanan')
                ->where('tipe_pesanan', 'central_kitchen')
                ->pluck('id')
                ->toArray();

            $ckWoIds = DB::table('work_order_detail')
                ->whereIn('pesanan_id', $ckPesananIds)
                ->pluck('work_order_id')
                ->merge(DB::table('work_order')->where('kode_wo', 'like', 'WO-CK-%')->pluck('id'))
                ->unique()
                ->toArray();

            $ckProdIds = DB::table('produksi')
                ->whereIn('pesanan_id', $ckPesananIds)
                ->pluck('id')
                ->merge(DB::table('produksi')->where('kode_produksi', 'like', 'PRD-CK-%')->pluck('id'))
                ->unique()
                ->toArray();

            // 2. Identifikasi Produksi Cold Kitchen
            $coldProdIds = DB::table('produksi')
                ->whereNotIn('id', $ckProdIds)
                ->where(function ($q) use ($coldPesananIds, $ckPesananIds) {
                    $q->whereIn('pesanan_id', $coldPesananIds)
                      ->orWhere('kode_produksi', 'not like', 'PRD-CK-%')
                      ->orWhere(function ($sq) use ($ckPesananIds) {
                          $sq->whereNull('pesanan_id')->whereNotIn('pesanan_id', $ckPesananIds);
                      });
                })
                ->pluck('id')
                ->toArray();

            // 3. Rollback & Bersihkan Stok dari Hasil Produksi Cold Kitchen
            $prodCount = count($coldProdIds);
            if ($prodCount > 0) {
                $coldProds = DB::table('produksi')->whereIn('id', $coldProdIds)->get();
                foreach ($coldProds as $prod) {
                    $gudangHasilId = $prod->gudang_hasil_id ?? 4; // Gudang Cold Kitchen
                    $gudangBahanId = $prod->gudang_bahan_id ?? 4;

                    // Rollback stok hasil produksi
                    $prodDetails = DB::table('produksi_detail')->where('produksi_id', $prod->id)->get();
                    foreach ($prodDetails as $pDet) {
                        $stokGudang = StokGudang::where('gudang_id', $gudangHasilId)
                            ->where('barang_id', $pDet->produk_id)
                            ->first();
                        if ($stokGudang) {
                            $stokGudang->decrement('jumlah', min(floatval($stokGudang->jumlah), floatval($pDet->qty)));
                        }

                        StokGudangBatch::where('gudang_id', $gudangHasilId)
                            ->where('barang_id', $pDet->produk_id)
                            ->where(function ($q) use ($prod) {
                                $q->where('batch_number', 'like', '%' . $prod->kode_produksi . '%')
                                  ->orWhere('batch_number', 'like', 'PROD-' . $prod->kode_produksi . '%');
                            })
                            ->delete();
                    }

                    // Rollback bahan baku yang terpotong saat produksi Cold Kitchen
                    $txKeluarList = TransaksiStok::where('source_type', 'produksi')
                        ->where('source_id', $prod->id)
                        ->where('tipe', 'keluar')
                        ->get();

                    foreach ($txKeluarList as $tx) {
                        $stokBahan = StokGudang::where('gudang_id', $gudangBahanId)
                            ->where('barang_id', $tx->barang_id)
                            ->first();
                        if ($stokBahan) {
                            $stokBahan->increment('jumlah', floatval($tx->qty));
                        }

                        $batchBahan = StokGudangBatch::where('gudang_id', $gudangBahanId)
                            ->where('barang_id', $tx->barang_id)
                            ->latest('id')
                            ->first();
                        if ($batchBahan) {
                            $batchBahan->increment('qty_sisa', floatval($tx->qty));
                            $batchBahan->decrement('qty_keluar', min(floatval($batchBahan->qty_keluar), floatval($tx->qty)));
                            $batchBahan->is_habis = false;
                            $batchBahan->save();
                        }
                    }
                }

                // Hapus TransaksiStok untuk produksi cold kitchen
                DB::table('transaksi_stok')
                    ->where('source_type', 'produksi')
                    ->whereIn('source_id', $coldProdIds)
                    ->delete();

                // Hapus alokasi produksi pesanan
                if (Schema::hasTable('alokasi_produksi_pesanan')) {
                    DB::table('alokasi_produksi_pesanan')
                        ->whereIn('produksi_id', $coldProdIds)
                        ->orWhereIn('pesanan_id', $coldPesananIds)
                        ->delete();
                }

                // Hapus produksi_detail dan produksi
                DB::table('produksi_detail')->whereIn('produksi_id', $coldProdIds)->delete();
                DB::table('produksi')->whereIn('id', $coldProdIds)->delete();
            }

            // 4. Identifikasi & Hapus Work Orders Cold Kitchen
            $coldWoIds = DB::table('work_order_detail')
                ->whereIn('pesanan_id', $coldPesananIds)
                ->pluck('work_order_id')
                ->merge(DB::table('work_order')->where('kode_wo', 'like', 'WO-B2B-%')->pluck('id'))
                ->diff($ckWoIds)
                ->unique()
                ->toArray();

            $woCount = count($coldWoIds);
            if ($woCount > 0) {
                DB::table('work_order_detail')->whereIn('work_order_id', $coldWoIds)->delete();
                DB::table('work_order')->whereIn('id', $coldWoIds)->delete();
            }
            // Bersihkan sisa work_order_detail yang mungkin mengarah ke pesanan cold kitchen
            if (!empty($coldPesananIds)) {
                DB::table('work_order_detail')->whereIn('pesanan_id', $coldPesananIds)->delete();
            }

            // 5. Bersihkan Pengiriman Cold Kitchen
            $pengirimanCount = 0;
            if (!empty($coldPesananIds) && Schema::hasTable('pengiriman')) {
                $pengirimanIds = DB::table('pengiriman')
                    ->whereIn('pesanan_id', $coldPesananIds)
                    ->pluck('id')
                    ->toArray();
                $pengirimanCount = count($pengirimanIds);
                if ($pengirimanCount > 0) {
                    if (Schema::hasTable('pengiriman_details')) {
                        DB::table('pengiriman_details')->whereIn('pengiriman_id', $pengirimanIds)->delete();
                    }
                    DB::table('pengiriman')->whereIn('id', $pengirimanIds)->delete();
                }
            }

            // 6. Bersihkan Pembayaran Cold Kitchen
            $bayarCount = 0;
            if (!empty($coldPesananIds) && Schema::hasTable('pembayaran')) {
                $bayarCount = DB::table('pembayaran')->whereIn('pesanan_id', $coldPesananIds)->delete();
            }

            // 7. Bersihkan Pesanan Detail & Pesanan Cold Kitchen
            $pesananCount = count($coldPesananIds);
            if ($pesananCount > 0) {
                DB::table('pesanan_detail')->whereIn('pesanan_id', $coldPesananIds)->delete();
                DB::table('pesanan')->whereIn('id', $coldPesananIds)->delete();
            }

            // Flush cache
            Cache::flush();

            return [
                'pesanan_deleted'    => $pesananCount,
                'wo_deleted'         => $woCount,
                'produksi_deleted'   => $prodCount,
                'pengiriman_deleted' => $pengirimanCount,
                'pembayaran_deleted' => $bayarCount,
            ];
        });
    }
}
