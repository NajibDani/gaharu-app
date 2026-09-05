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
        Schema::table('pembelian_detail', function (Blueprint $table) {
            $table->foreignId('supplier_id')->nullable()->after('barang_id')->constrained('suppliers')->onDelete('set null');
            $table->string('metode_pembayaran')->nullable()->after('harga_per_qty');
            $table->tinyInteger('persen_dp')->nullable()->after('metode_pembayaran');
            $table->decimal('nominal_dp', 15, 2)->nullable()->after('persen_dp');
            $table->date('tanggal_jatuh_tempo')->nullable()->after('nominal_dp');
            $table->date('tanggal_pelunasan')->nullable()->after('tanggal_jatuh_tempo');
            $table->text('catatan_pembayaran')->nullable()->after('tanggal_pelunasan');
            $table->boolean('is_lunas')->default(false)->after('catatan_pembayaran');
            $table->timestamp('lunas_at')->nullable()->after('is_lunas');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pembelian_detail', function (Blueprint $table) {
            $table->dropForeign(['supplier_id']);
            $table->dropColumn([
                'supplier_id',
                'metode_pembayaran',
                'persen_dp',
                'nominal_dp',
                'tanggal_jatuh_tempo',
                'tanggal_pelunasan',
                'catatan_pembayaran',
                'is_lunas',
                'lunas_at',
            ]);
        });
    }
};
