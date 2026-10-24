<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    /**
     * Perbaikan:
     * 1. Update resep BSJ - gunakan kolom yang BENAR: resep_id, bahan_id, qty_bahan, satuan
     * 2. Deduplikasi resep_bahanbaku
     * 3. Sinkronisasi stok_gudang = sum(batch.qty_sisa) untuk semua BSJ di semua outlet
     * 4. Hapus transaksi orphan pengeluaran_bahan_baku yang parent-nya tidak ada
     * 5. Update tanggal transfer CK existing agar sesuai WO / H+1
     */
    public function up(): void
    {
        // ============================================================
        // 1. PERBAIKAN RESEP (menggunakan kolom BENAR: bahan_id, qty_bahan, resep_id)
        // ============================================================

        // Cari barang yang dibutuhkan
        $iga150gr   = DB::table('master_barang')->where('kode_barang', 'BSJ163')->first(); // IGA 150 GR (BSJ)
        $igaRaw     = DB::table('master_barang')->where('kode_barang', 'BBB250')->first(); // IGA mentah
        $pahaGrill  = DB::table('master_barang')->where('kode_barang', 'BSJ029')->first(); // PAHA GRILL (BSJ)
        $filletPaha = DB::table('master_barang')->where('kode_barang', 'BBB187')->first(); // FILLET PAHA mentah
        $sateLilit  = DB::table('master_barang')->where('kode_barang', 'BSJ124')->first(); // SATE LILIT

        // 1a. Roasted Iga BBQ Sauce (FNB468) - ganti IGA mentah (BBB250) ke IGA 150 GR (BSJ163)
        $menuIga = DB::table('master_barang')->where('kode_barang', 'FNB468')->first();
        if ($menuIga && $iga150gr && $igaRaw) {
            // Hapus row lama yang menggunakan bahan baku mentah (BBB250) di resep ini
            DB::table('resep_bahanbaku')
                ->where('resep_id', $menuIga->resep_id)
                ->where('bahan_id', $igaRaw->id)
                ->delete();

            // Tambah atau update ke BSJ163 jika belum ada
            $existingBsj = DB::table('resep_bahanbaku')
                ->where('resep_id', $menuIga->resep_id)
                ->where('bahan_id', $iga150gr->id)
                ->first();

            if (!$existingBsj) {
                DB::table('resep_bahanbaku')->insert([
                    'resep_id'  => $menuIga->resep_id,
                    'bahan_id'  => $iga150gr->id,
                    'qty_bahan' => 150,
                    'satuan'    => 'GR',
                ]);
            } else {
                DB::table('resep_bahanbaku')
                    ->where('resep_id', $menuIga->resep_id)
                    ->where('bahan_id', $iga150gr->id)
                    ->update(['qty_bahan' => 150, 'satuan' => 'GR']);
            }
        }

        // 1b. Chicken Burger (FNB038) - ganti FILLET PAHA mentah (BBB187) ke PAHA GRILL (BSJ029)
        $menuBurger = DB::table('master_barang')->where('kode_barang', 'FNB038')->first();
        if ($menuBurger && $pahaGrill && $filletPaha) {
            DB::table('resep_bahanbaku')
                ->where('resep_id', $menuBurger->resep_id)
                ->where('bahan_id', $filletPaha->id)
                ->delete();

            $existingBsj = DB::table('resep_bahanbaku')
                ->where('resep_id', $menuBurger->resep_id)
                ->where('bahan_id', $pahaGrill->id)
                ->first();

            if (!$existingBsj) {
                DB::table('resep_bahanbaku')->insert([
                    'resep_id'  => $menuBurger->resep_id,
                    'bahan_id'  => $pahaGrill->id,
                    'qty_bahan' => 150,
                    'satuan'    => 'GR',
                ]);
            } else {
                DB::table('resep_bahanbaku')
                    ->where('resep_id', $menuBurger->resep_id)
                    ->where('bahan_id', $pahaGrill->id)
                    ->update(['qty_bahan' => 150, 'satuan' => 'GR']);
            }
        }

        // 1c. Balinese Mix Rice With Satay (FNB330) - ganti SATE LILIT (BSJ124) ke PAHA GRILL (BSJ029)
        $menuBali = DB::table('master_barang')->where('kode_barang', 'FNB330')->first();
        if ($menuBali && $pahaGrill && $sateLilit) {
            DB::table('resep_bahanbaku')
                ->where('resep_id', $menuBali->resep_id)
                ->where('bahan_id', $sateLilit->id)
                ->delete();

            $existingBsj = DB::table('resep_bahanbaku')
                ->where('resep_id', $menuBali->resep_id)
                ->where('bahan_id', $pahaGrill->id)
                ->first();

            if (!$existingBsj) {
                DB::table('resep_bahanbaku')->insert([
                    'resep_id'  => $menuBali->resep_id,
                    'bahan_id'  => $pahaGrill->id,
                    'qty_bahan' => 150,
                    'satuan'    => 'GR',
                ]);
            }
        }

        // ============================================================
        // 2. DEDUPLIKASI resep_bahanbaku (simpan baris dengan ID tertinggi)
        // ============================================================
        $duplicates = DB::table('resep_bahanbaku')
            ->select('resep_id', 'bahan_id', DB::raw('COUNT(*) as total'))
            ->groupBy('resep_id', 'bahan_id')
            ->having('total', '>', 1)
            ->get();

        foreach ($duplicates as $dup) {
            $keepId = DB::table('resep_bahanbaku')
                ->where('resep_id', $dup->resep_id)
                ->where('bahan_id', $dup->bahan_id)
                ->max('id');

            DB::table('resep_bahanbaku')
                ->where('resep_id', $dup->resep_id)
                ->where('bahan_id', $dup->bahan_id)
                ->where('id', '!=', $keepId)
                ->delete();
        }

        // ============================================================
        // 3. SINKRONISASI stok_gudang BSJ = sum(batch.qty_sisa) untuk semua outlet
        //    Berlaku hanya jika batch_sisa > 0 (jika 0 artinya memang habis, biarkan)
        // ============================================================
        $outletGudangIds = DB::table('master_gudang')
            ->where('tipe', 'outlet')
            ->pluck('id')
            ->toArray();

        // Fallback jika kolom tipe tidak ada: gunakan gudang_id yang diketahui
        if (empty($outletGudangIds)) {
            $outletGudangIds = [3, 5]; // Gudang Gaharu & Gudang KeJingga
        }

        foreach ($outletGudangIds as $gudangId) {
            $bsjItems = DB::table('stok_gudang as sg')
                ->where('sg.gudang_id', $gudangId)
                ->join('master_barang as mb', 'sg.barang_id', '=', 'mb.id')
                ->where('mb.is_bahan_setengah_jadi', 1)
                ->select('sg.id', 'sg.barang_id', 'sg.jumlah', 'sg.divisi_id', 'mb.kode_barang')
                ->get();

            foreach ($bsjItems as $item) {
                $batchSisa = DB::table('stok_gudang_batch')
                    ->where('gudang_id', $gudangId)
                    ->where('barang_id', $item->barang_id)
                    ->sum('qty_sisa');

                if ($batchSisa > 0 && abs($item->jumlah - $batchSisa) > 0.01) {
                    DB::table('stok_gudang')
                        ->where('id', $item->id)
                        ->update(['jumlah' => $batchSisa]);
                }
            }
        }

        // ============================================================
        // 4. FIX BATCH SEPT yang tidak balance di gudang 5
        //    (is_habis=1 tapi qty_keluar != qty_masuk)
        // ============================================================
        // Untuk setiap batch dengan is_habis=1 dan sisa=0 tapi masuk != keluar
        $unbalancedBatches = DB::table('stok_gudang_batch')
            ->where('is_habis', 1)
            ->where('qty_sisa', 0)
            ->whereRaw('qty_masuk != qty_keluar')
            ->get();

        foreach ($unbalancedBatches as $batch) {
            DB::table('stok_gudang_batch')
                ->where('id', $batch->id)
                ->update(['qty_keluar' => $batch->qty_masuk]);
        }

        // ============================================================
        // 5. UPDATE TANGGAL TRANSFER CK (menggunakan metode yang sudah diperbaiki)
        //    via autoHealApprovedCkProductionTransfers
        // ============================================================
        try {
            $controller = app(\App\Http\Controllers\StokGudangController::class);
            $controller->autoHealApprovedCkProductionTransfers();
        } catch (\Throwable $e) {
            Log::error('Migration fix_bsj v2 - autoHealCkTransfers error: ' . $e->getMessage());
        }

        // ============================================================
        // 6. HAPUS TRANSAKSI ORPHAN pengeluaran_bahan_baku yang tidak ada induknya
        // ============================================================
        $orphanTxIds = DB::table('transaksi_stok as ts')
            ->where('ts.source_type', 'pengeluaran_bahan_baku')
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))
                  ->from('pengeluaran_bahan_baku as pbb')
                  ->whereColumn('pbb.id', 'ts.source_id');
            })
            ->pluck('ts.id')
            ->toArray();

        if (!empty($orphanTxIds)) {
            DB::table('transaksi_stok')->whereIn('id', $orphanTxIds)->delete();
            Log::info('Migration fix_bsj v2: Hapus ' . count($orphanTxIds) . ' transaksi orphan pengeluaran_bahan_baku.');
        }
    }

    public function down(): void
    {
        // Tidak ada rollback karena ini data healing
    }
};