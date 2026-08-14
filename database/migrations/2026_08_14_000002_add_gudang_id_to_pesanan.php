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
        if (!Schema::hasColumn('pesanan', 'gudang_id')) {
            Schema::table('pesanan', function (Blueprint $table) {
                $table->foreignId('gudang_id')->nullable()->after('created_by')->constrained('master_gudang')->onDelete('set null');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('pesanan', 'gudang_id')) {
            Schema::table('pesanan', function (Blueprint $table) {
                $table->dropForeign(['gudang_id']);
                $table->dropColumn('gudang_id');
            });
        }
    }
};
