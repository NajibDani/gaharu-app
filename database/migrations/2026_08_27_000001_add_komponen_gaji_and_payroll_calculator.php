<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Tambah komponen harian pada tabel karyawan
        Schema::table('karyawan', function (Blueprint $table) {
            if (!Schema::hasColumn('karyawan', 'uang_makan')) {
                $table->decimal('uang_makan', 15, 2)->default(0.00)->after('gaji_pokok');
            }
            if (!Schema::hasColumn('karyawan', 'uang_transport')) {
                $table->decimal('uang_transport', 15, 2)->default(0.00)->after('uang_makan');
            }
        });

        // 2. Tambah rincian kalkulasi penggajian pada tabel penggajian
        Schema::table('penggajian', function (Blueprint $table) {
            if (!Schema::hasColumn('penggajian', 'hari_kerja')) {
                $table->integer('hari_kerja')->default(0)->after('periode_bulan_tahun');
            }
            if (!Schema::hasColumn('penggajian', 'tarif_harian_total')) {
                $table->decimal('tarif_harian_total', 15, 2)->default(0.00)->after('hari_kerja');
            }
            if (!Schema::hasColumn('penggajian', 'gaji_utama')) {
                $table->decimal('gaji_utama', 15, 2)->default(0.00)->after('tarif_harian_total');
            }
            if (!Schema::hasColumn('penggajian', 'jam_lembur')) {
                $table->decimal('jam_lembur', 8, 2)->default(0.00)->after('lembur');
            }
            if (!Schema::hasColumn('penggajian', 'banyak_target')) {
                $table->integer('banyak_target')->default(0)->after('bonus_target');
            }
            if (!Schema::hasColumn('penggajian', 'banyak_tanggal_merah')) {
                $table->integer('banyak_tanggal_merah')->default(0)->after('bonus_tanggal_merah');
            }
            if (!Schema::hasColumn('penggajian', 'banyak_birthday_service')) {
                $table->integer('banyak_birthday_service')->default(0)->after('bonus_birthday');
            }
            if (!Schema::hasColumn('penggajian', 'potongan_kasbon')) {
                $table->decimal('potongan_kasbon', 15, 2)->default(0.00)->after('potongan_terlambat');
            }
            if (!Schema::hasColumn('penggajian', 'potongan_dll')) {
                $table->decimal('potongan_dll', 15, 2)->default(0.00)->after('potongan_kasbon');
            }
            if (!Schema::hasColumn('penggajian', 'total_earnings')) {
                $table->decimal('total_earnings', 15, 2)->default(0.00)->after('potongan_dll');
            }
            if (!Schema::hasColumn('penggajian', 'total_deductions')) {
                $table->decimal('total_deductions', 15, 2)->default(0.00)->after('total_earnings');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('karyawan', function (Blueprint $table) {
            $table->dropColumn(['uang_makan', 'uang_transport']);
        });

        Schema::table('penggajian', function (Blueprint $table) {
            $table->dropColumn([
                'hari_kerja', 'tarif_harian_total', 'gaji_utama',
                'jam_lembur', 'banyak_target', 'banyak_tanggal_merah',
                'banyak_birthday_service', 'potongan_kasbon', 'potongan_dll',
                'total_earnings', 'total_deductions'
            ]);
        });
    }
};
