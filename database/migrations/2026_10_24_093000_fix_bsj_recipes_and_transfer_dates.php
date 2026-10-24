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
        $iga150gr   = DB::table('master_barang')->where('kode_barang', 'BSJ163')->first();
        $igaRaw     = DB::table('master_barang')->where('kode_barang', 'BBB250')->first();
        $pahaGrill  = DB::table('master_barang')->where('kode_barang', 'BSJ029')->first();
        $filletPaha = DB::table('master_barang')->where('kode_barang', 'BBB187')->first();
        $sateLilit  = DB::table('master_barang')->where('kode_barang', 'BSJ124')->first();

        // 1a. Roasted Iga BBQ Sauce (FNB468) -> use IGA 150 GR (BSJ163) instead of IGA (BBB250)
        $menuIga = DB::table('master_barang')->where('kode_barang', 'FNB468')->first();
        if ($menuIga && $menuIga->resep_id && $iga150gr && $igaRaw) {
            DB::table('resep_bahanbaku')
                ->where('resep_id', $menuIga->resep_id)
                ->where('bahan_id', $igaRaw->id)
                ->update([
                    'bahan_id'  => $iga150gr->id,
                    'qty_bahan' => 150,
                    'satuan'    => 'GR'
                ]);
        }

        // 1b. Chicken Burger (FNB038) -> use PAHA GRILL (BSJ029) instead of FILLET PAHA (BBB187)
        $menuBurger = DB::table('master_barang')->where('kode_barang', 'FNB038')->first();
        if ($menuBurger && $menuBurger->resep_id && $pahaGrill && $filletPaha) {
            DB::table('resep_bahanbaku')
                ->where('resep_id', $menuBurger->resep_id)
                ->where('bahan_id', $filletPaha->id)
                ->update([
                    'bahan_id'  => $pahaGrill->id,
                    'qty_bahan' => 150,
                    'satuan'    => 'GR'
                ]);
        }

        // 1c. Balinese Mix Rice With Satay (FNB330) -> use PAHA GRILL (BSJ029) instead of SATE LILIT (BSJ124)
        $menuBali = DB::table('master_barang')->where('kode_barang', 'FNB330')->first();
        if ($menuBali && $menuBali->resep_id && $pahaGrill && $sateLilit) {
            DB::table('resep_bahanbaku')
                ->where('resep_id', $menuBali->resep_id)
                ->where('bahan_id', $sateLilit->id)
                ->update([
                    'bahan_id'  => $pahaGrill->id,
                    'qty_bahan' => 150,
                    'satuan'    => 'GR'
                ]);
        }

        // 2. DEDUPLICATE resep_bahanbaku (keep highest ID per resep_id + bahan_id)
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

        // 3. EXECUTE HEALING VIA StokGudangController & PosController
        try {
            $controller = new StokGudangController();
            $posController = new PosController();

            // Run CK production transfers auto-heal
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