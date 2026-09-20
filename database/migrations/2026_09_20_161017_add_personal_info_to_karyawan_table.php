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
        Schema::table('karyawan', function (Blueprint $table) {
            $table->string('nik', 50)->nullable()->after('nama_karyawan');
            $table->string('ttl', 100)->nullable()->after('nik');
            $table->string('whatsapp', 50)->nullable()->after('ttl');
            $table->string('email', 100)->nullable()->after('whatsapp');
            $table->string('nomor_darurat', 100)->nullable()->after('email');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('karyawan', function (Blueprint $table) {
            $table->dropColumn(['nik', 'ttl', 'whatsapp', 'email', 'nomor_darurat']);
        });
    }
};
