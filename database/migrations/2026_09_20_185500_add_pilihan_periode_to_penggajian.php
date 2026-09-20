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
        if (!Schema::hasColumn('penggajian', 'pilihan_periode')) {
            Schema::table('penggajian', function (Blueprint $table) {
                $table->tinyInteger('pilihan_periode')->default(1)->after('satuan_gaji_2');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('penggajian', 'pilihan_periode')) {
            Schema::table('penggajian', function (Blueprint $table) {
                $table->dropColumn('pilihan_periode');
            });
        }
    }
};
