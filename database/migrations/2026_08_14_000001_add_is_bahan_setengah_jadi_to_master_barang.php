<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('master_barang', function (Blueprint $table) {
            $table->boolean('is_bahan_setengah_jadi')->default(false)->after('is_direct_consumption');
        });
    }

    public function down(): void
    {
        Schema::table('master_barang', function (Blueprint $table) {
            $table->dropColumn('is_bahan_setengah_jadi');
        });
    }
};
