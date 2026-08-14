<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasColumn('pesanan', 'tipe_pesanan')) {
            Schema::table('pesanan', function (Blueprint $table) {
                $table->enum('tipe_pesanan', ['b2b', 'central_kitchen'])->default('b2b')->after('kode_pesanan');
            });
        }

        if (!Schema::hasColumn('pesanan', 'gudang_id')) {
            Schema::table('pesanan', function (Blueprint $table) {
                $table->foreignId('gudang_id')->nullable()->after('created_by')->constrained('master_gudang')->onDelete('set null');
            });
        }

        // Pastikan Gudang Central Kitchen tersedia di master_gudang
        $existsGudang = DB::table('master_gudang')->where('nama', 'Gudang Central Kitchen')->exists();
        if (!$existsGudang) {
            DB::table('master_gudang')->insert([
                'nama' => 'Gudang Central Kitchen',
                'kategori' => 'Produksi',
            ]);
        }

        // Pastikan customer Outlet Gaharu & Outlet KeJingga tersedia di tabel customers
        $existsGaharu = DB::table('customers')->where('nama', 'Outlet Gaharu')->exists();
        if (!$existsGaharu) {
            DB::table('customers')->insert([
                'nama' => 'Outlet Gaharu',
                'jenis' => 'Outlet Internal',
                'no_hp' => '081200000001',
                'alamat' => 'Gaharu Outlet Location',
            ]);
        }

        $existsKejingga = DB::table('customers')->where('nama', 'Outlet KeJingga')->exists();
        if (!$existsKejingga) {
            DB::table('customers')->insert([
                'nama' => 'Outlet KeJingga',
                'jenis' => 'Outlet Internal',
                'no_hp' => '081200000002',
                'alamat' => 'KeJingga Outlet Location',
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('pesanan', 'tipe_pesanan')) {
            Schema::table('pesanan', function (Blueprint $table) {
                $table->dropColumn('tipe_pesanan');
            });
        }
    }
};
