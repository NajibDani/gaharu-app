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
        if (Schema::hasTable('pesanan') && !Schema::hasColumn('pesanan', 'divisi_id')) {
            Schema::table('pesanan', function (Blueprint $table) {
                $table->foreignId('divisi_id')->nullable()->after('gudang_id')->constrained('gudang_divisi')->onDelete('set null');
            });
        }

        if (Schema::hasTable('produksi') && !Schema::hasColumn('produksi', 'divisi_id')) {
            Schema::table('produksi', function (Blueprint $table) {
                $table->foreignId('divisi_id')->nullable()->after('gudang_hasil_id')->constrained('gudang_divisi')->onDelete('set null');
            });
        }

        // Seed divisions for Gudang Central Kitchen (Gudang ID: 1)
        $gudangCk = DB::table('master_gudang')->where('nama', 'like', '%Central Kitchen%')->first();
        if ($gudangCk) {
            $divisions = ['Kitchen', 'Barista', 'Server'];
            foreach ($divisions as $divName) {
                $exists = DB::table('gudang_divisi')
                    ->where('gudang_id', $gudangCk->id)
                    ->where('nama', $divName)
                    ->exists();

                if (!$exists) {
                    DB::table('gudang_divisi')->insert([
                        'gudang_id'  => $gudangCk->id,
                        'nama'       => $divName,
                        'keterangan' => 'Divisi ' . $divName . ' untuk ' . $gudangCk->nama,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('produksi') && Schema::hasColumn('produksi', 'divisi_id')) {
            Schema::table('produksi', function (Blueprint $table) {
                $table->dropForeign(['divisi_id']);
                $table->dropColumn('divisi_id');
            });
        }

        if (Schema::hasTable('pesanan') && Schema::hasColumn('pesanan', 'divisi_id')) {
            Schema::table('pesanan', function (Blueprint $table) {
                $table->dropForeign(['divisi_id']);
                $table->dropColumn('divisi_id');
            });
        }
    }
};
