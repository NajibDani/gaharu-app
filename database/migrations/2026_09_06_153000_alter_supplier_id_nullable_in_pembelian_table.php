<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        try {
            DB::statement('ALTER TABLE `pembelian` MODIFY `supplier_id` BIGINT UNSIGNED NULL');
        } catch (\Throwable $e) {
            try {
                Schema::table('pembelian', function (Blueprint $table) {
                    $table->unsignedBigInteger('supplier_id')->nullable()->change();
                });
            } catch (\Throwable $ex) {
                // Ignore if already nullable
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No-op
    }
};
