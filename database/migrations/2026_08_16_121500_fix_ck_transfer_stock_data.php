<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Perbaiki data stok aktual untuk pengiriman CK yang sudah terjadi
     * dengan kode buggy (stok transfer masuk ke Gudang CK, bukan outlet).
     *
     * Fixes:
     * 1. stok_gudang_batch: pindahkan TRANSFER-CK batch dari Gudang CK ke outlet
     * 2. stok_gudang: kurangi CK, tambah outlet
     * 3. transaksi_stok: perbaiki gudang_tujuan_id
     */
    public function up(): void
    {
        $gudangCk = DB::table('master_gudang')
            ->where('nama', 'Gudang Central Kitchen')
            ->first();

        if (!$gudangCk) return;

        // Cari semua batch TRANSFER-CK yang salah berada di Gudang CK
        $wrongBatches = DB::table('stok_gudang_batch')
            ->where('gudang_id', $gudangCk->id)
            ->where('batch_number', 'like', 'TRANSFER-CK-%')
            ->get();

        if ($wrongBatches->isEmpty()) return;

        // Parse no_pengiriman dari batch_number: "TRANSFER-CK-SJ-XXXXXXXX-XXXX" -> "SJ-XXXXXXXX-XXXX"
        foreach ($wrongBatches as $batch) {
            $noPengiriman = str_replace('TRANSFER-CK-', '', $batch->batch_number);

            $pengiriman = DB::table('pengiriman')
                ->where('no_pengiriman', $noPengiriman)
                ->first();

            if (!$pengiriman) continue;

            // Dapatkan pesanan dan tentukan gudang outlet tujuan yang benar
            $pesanan = DB::table('pesanan')
                ->where('id', $pengiriman->pesanan_id)
                ->first();

            if (!$pesanan) continue;

            // gudang_id pesanan sudah diperbaiki oleh migration sebelumnya
            // Tapi tambahkan fallback dari customer name untuk keamanan
            $gudangTujuanId = null;

            if ($pesanan->gudang_id && $pesanan->gudang_id != $gudangCk->id) {
                $gudangTujuanId = $pesanan->gudang_id;
            } else {
                // Fallback dari nama customer
                $customer = DB::table('customers')
                    ->where('id', $pesanan->customer_id)
                    ->first();

                if ($customer) {
                    $custNama = strtolower($customer->nama);
                    if (str_contains($custNama, 'kejingga')) {
                        $g = DB::table('master_gudang')
                            ->where(function ($q) {
                                $q->where('nama', 'like', '%KeJingga%')
                                  ->orWhere('nama', 'like', '%Kejingga%');
                            })->first();
                        $gudangTujuanId = $g ? $g->id : null;
                    } elseif (str_contains($custNama, 'gaharu')) {
                        $g = DB::table('master_gudang')
                            ->where('nama', 'like', '%Gaharu%')
                            ->where('kategori', 'Operasional')
                            ->first();
                        $gudangTujuanId = $g ? $g->id : null;
                    }
                }
            }

            if (!$gudangTujuanId || $gudangTujuanId == $gudangCk->id) continue;

            $barangId = $batch->barang_id;
            $qty = floatval($batch->qty_sisa) + floatval($batch->qty_keluar);
            // qty_sisa karena batch belum dikonsumsi (kemungkinan besar)
            $qtySisa = floatval($batch->qty_sisa);

            // 1. Pindahkan batch ke gudang outlet yang benar
            DB::table('stok_gudang_batch')
                ->where('id', $batch->id)
                ->update([
                    'gudang_id' => $gudangTujuanId,
                    'updated_at' => now(),
                ]);

            // 2. Perbaiki stok_gudang: kurangi CK, tambah outlet
            // Kurangi CK (stok ini tidak seharusnya ada di CK)
            $stokCk = DB::table('stok_gudang')
                ->where('gudang_id', $gudangCk->id)
                ->where('barang_id', $barangId)
                ->first();

            if ($stokCk) {
                $newJumlahCk = max(0, floatval($stokCk->jumlah) - $qtySisa);
                DB::table('stok_gudang')
                    ->where('id', $stokCk->id)
                    ->update(['jumlah' => $newJumlahCk]);
            }

            // Tambah outlet
            $stokOutlet = DB::table('stok_gudang')
                ->where('gudang_id', $gudangTujuanId)
                ->where('barang_id', $barangId)
                ->first();

            if ($stokOutlet) {
                DB::table('stok_gudang')
                    ->where('id', $stokOutlet->id)
                    ->increment('jumlah', $qtySisa);
            } else {
                DB::table('stok_gudang')->insert([
                    'gudang_id' => $gudangTujuanId,
                    'barang_id' => $barangId,
                    'jumlah'    => $qtySisa,
                ]);
            }

            // 3. Perbaiki transaksi_stok: gudang_tujuan_id
            DB::table('transaksi_stok')
                ->where('source_type', 'transfer_ck')
                ->where('source_id', $pengiriman->id)
                ->where('barang_id', $barangId)
                ->where('gudang_tujuan_id', $gudangCk->id)
                ->update([
                    'gudang_tujuan_id' => $gudangTujuanId,
                ]);
        }
    }

    public function down(): void
    {
        // No rollback
    }
};
