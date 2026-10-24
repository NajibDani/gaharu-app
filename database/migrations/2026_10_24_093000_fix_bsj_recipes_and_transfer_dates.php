<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\StokGudangController;
use App\Http\Controllers\PosController;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. UPDATE RECIPES TO USE BSJ INSTEAD OF RAW MATERIALS
        // Roasted Iga BBQ Sauce (FNB468) -> use IGA 150 GR (BSJ163) instead of IGA (BBB250)
        $iga150gr = DB::table('master_barang')->where('kode_barang', 'BSJ163')->first();
        $igaRaw   = DB::table('master_barang')->where('kode_barang', 'BBB250')->first();
        $pahaGrill = DB::table('master_barang')->where('kode_barang', 'BSJ029')->first();
        $filletPaha = DB::table('master_barang')->where('kode_barang', 'BBB187')->first();
        $sateLilit = DB::table('master_barang')->where('kode_barang', 'BSJ124')->first();

        // 1a. Roasted Iga BBQ Sauce (id: 1294 / FNB468)
        $menuIga = DB::table('master_barang')->where('kode_barang', 'FNB468')->first();
        if ($menuIga && $iga150gr) {
            if ($igaRaw) {
                DB::table('resep_bahanbaku')
                    ->where('barang_id', $menuIga->id)
                    ->where('bahan_baku_id', $igaRaw->id)
                    ->update([
                        'bahan_baku_id' => $iga150gr->id,
                        'jumlah' => 150,
                        'satuan' => 'GR'
                    ]);
            }
        }

        // 1b. Chicken Burger (id: 804 / FNB038) -> use PAHA GRILL (BSJ029) instead of FILLET PAHA (BBB187)
        $menuBurger = DB::table('master_barang')->where('kode_barang', 'FNB038')->first();
        if ($menuBurger && $pahaGrill) {
            if ($filletPaha) {
                DB::table('resep_bahanbaku')
                    ->where('barang_id', $menuBurger->id)
                    ->where('bahan_baku_id', $filletPaha->id)
                    ->update([
                        'bahan_baku_id' => $pahaGrill->id,
                        'jumlah' => 150,
                        'satuan' => 'GR'
                    ]);
            }
        }

        // 1c. Balinese Mix Rice With Satay (id: 1156 / FNB330) -> use PAHA GRILL (BSJ029) instead of SATE LILIT (BSJ124)
        $menuBali = DB::table('master_barang')->where('kode_barang', 'FNB330')->first();
        if ($menuBali && $pahaGrill && $sateLilit) {
            DB::table('resep_bahanbaku')
                ->where('barang_id', $menuBali->id)
                ->where('bahan_baku_id', $sateLilit->id)
                ->update([
                    'bahan_baku_id' => $pahaGrill->id,
                    'jumlah' => 150,
                    'satuan' => 'GR'
                ]);
        }

        // 2. DEDUPLICATE resep_bahanbaku (keep highest ID per barang_id + bahan_baku_id)
        $duplicates = DB::table('resep_bahanbaku')
            ->select('barang_id', 'bahan_baku_id', DB::raw('COUNT(*) as total'))
            ->groupBy('barang_id', 'bahan_baku_id')
            ->having('total', '>', 1)
            ->get();

        foreach ($duplicates as $dup) {
            $keepId = DB::table('resep_bahanbaku')
                ->where('barang_id', $dup->barang_id)
                ->where('bahan_baku_id', $dup->bahan_baku_id)
                ->max('id');

            DB::table('resep_bahanbaku')
                ->where('barang_id', $dup->barang_id)
                ->where('bahan_baku_id', $dup->bahan_baku_id)
                ->where('id', '!=', $keepId)
                ->delete();
        }

        // 3. EXECUTE HEALING VIA StokGudangController & PosController
        try {
            $controller = new StokGudangController();
            $posController = new PosController();

            // Run CK production transfers auto-heal (this aligns transfer timestamps to WO / H+1)
            $controller->autoHealApprovedCkProductionTransfers();

            // Clean orphan POS mutations
            $controller->autoCleanOrphanMutations();

            // Auto-heal approved POS sales mutations
            $controller->autoHealApprovedPosStockMutations();

            // Re-reconcile all stock batches & balances
            $posGudangs = DB::table('gudang')->pluck('id')->all();
            foreach ($posGudangs as $gId) {
                $controller->reconcileStockBalances($gId);
            }
        } catch (\Throwable $e) {
            \Log::error('Migration fix_bsj_recipes_and_transfer_dates error: ' . $e->getMessage());
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No down migration necessary as this is a data healing and recipe fix
    }
};
