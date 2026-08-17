<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Perbaiki gudang_id pada pesanan Central Kitchen yang sudah ada.
     * 
     * Bug: gudang_id diset ke Gudang Central Kitchen (sumber produksi),
     * seharusnya ke gudang outlet tujuan (Gudang Gaharu / Gudang KeJingga).
     * 
     * Akibat bug: Saat pengiriman, stok BSJ dipotong dari CK lalu ditambahkan
     * kembali ke CK (bukan ke outlet). Outlet tidak pernah menerima stok BSJ.
     */
    public function up(): void
    {
        // Cari ID Gudang Central Kitchen (sumber)
        $gudangCk = DB::table('master_gudang')
            ->where('nama', 'Gudang Central Kitchen')
            ->first();

        if (!$gudangCk) return;

        // Ambil semua pesanan CK yang gudang_id-nya masih mengarah ke Gudang CK
        $pesananCk = DB::table('pesanan')
            ->where('tipe_pesanan', 'central_kitchen')
            ->where('gudang_id', $gudangCk->id)
            ->get();

        if ($pesananCk->isEmpty()) return;

        // Preload outlet warehouses
        $gudangGaharu = DB::table('master_gudang')
            ->where('nama', 'like', '%Gaharu%')
            ->where('kategori', 'Operasional')
            ->first();

        $gudangKejingga = DB::table('master_gudang')
            ->where(function ($q) {
                $q->where('nama', 'like', '%KeJingga%')
                  ->orWhere('nama', 'like', '%Kejingga%');
            })
            ->where('kategori', 'Operasional')
            ->first();

        foreach ($pesananCk as $pesanan) {
            $customer = DB::table('customers')
                ->where('id', $pesanan->customer_id)
                ->first();

            if (!$customer) continue;

            $custNama = strtolower($customer->nama);
            $gudangTujuanId = null;

            if (str_contains($custNama, 'kejingga') && $gudangKejingga) {
                $gudangTujuanId = $gudangKejingga->id;
            } elseif (str_contains($custNama, 'gaharu') && $gudangGaharu) {
                $gudangTujuanId = $gudangGaharu->id;
            } elseif ($gudangGaharu) {
                // Default fallback ke Gudang Gaharu jika nama tak dikenali
                $gudangTujuanId = $gudangGaharu->id;
            }

            if ($gudangTujuanId) {
                DB::table('pesanan')
                    ->where('id', $pesanan->id)
                    ->update([
                        'gudang_id'  => $gudangTujuanId,
                        'updated_at' => now(),
                    ]);
            }
        }
    }

    /**
     * Rollback tidak perlu karena data yang salah tetap salah.
     */
    public function down(): void
    {
        // No rollback — the previous gudang_id was incorrect
    }
};
