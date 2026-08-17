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
        // 1. Buat tabel gudang_divisi
        if (!Schema::hasTable('gudang_divisi')) {
            Schema::create('gudang_divisi', function (Blueprint $table) {
                $table->id();
                $table->foreignId('gudang_id')->constrained('master_gudang')->onDelete('cascade');
                $table->string('nama');
                $table->text('keterangan')->nullable();
                $table->timestamps();
            });
        }

        // 2. Tambah kolom divisi_id di stok_gudang
        if (Schema::hasTable('stok_gudang')) {
            Schema::table('stok_gudang', function (Blueprint $table) {
                if (!Schema::hasColumn('stok_gudang', 'divisi_id')) {
                    $table->foreignId('divisi_id')->nullable()->after('gudang_id')->constrained('gudang_divisi')->onDelete('cascade');
                }
            });

            // Update unique index pada stok_gudang agar mendukung per divisi
            try {
                Schema::table('stok_gudang', function (Blueprint $table) {
                    $table->dropUnique('stok_gudang_gudang_barang_unique');
                });
            } catch (\Exception $e) {
                // Ignore if unique index was already dropped or has different name
            }

            try {
                Schema::table('stok_gudang', function (Blueprint $table) {
                    $table->unique(['gudang_id', 'divisi_id', 'barang_id'], 'stok_gudang_gudang_divisi_barang_unique');
                });
            } catch (\Exception $e) {
                // Ignore if already exists
            }
        }

        // 3. Tambah kolom divisi_id di stok_gudang_batch
        if (Schema::hasTable('stok_gudang_batch')) {
            Schema::table('stok_gudang_batch', function (Blueprint $table) {
                if (!Schema::hasColumn('stok_gudang_batch', 'divisi_id')) {
                    $table->foreignId('divisi_id')->nullable()->after('gudang_id')->constrained('gudang_divisi')->onDelete('cascade');
                    $table->index(['gudang_id', 'divisi_id', 'barang_id'], 'stok_batch_gudang_divisi_barang_idx');
                }
            });
        }

        // 4. Tambah kolom divisi_id di pengeluaran_bahan_baku
        if (Schema::hasTable('pengeluaran_bahan_baku')) {
            Schema::table('pengeluaran_bahan_baku', function (Blueprint $table) {
                if (!Schema::hasColumn('pengeluaran_bahan_baku', 'divisi_id')) {
                    $table->foreignId('divisi_id')->nullable()->after('gudang_id')->constrained('gudang_divisi')->onDelete('set null');
                }
            });
        }

        // 5. Tambah kolom divisi_id di stock_opname
        if (Schema::hasTable('stock_opname')) {
            Schema::table('stock_opname', function (Blueprint $table) {
                if (!Schema::hasColumn('stock_opname', 'divisi_id')) {
                    $table->foreignId('divisi_id')->nullable()->after('gudang_id')->constrained('gudang_divisi')->onDelete('set null');
                }
            });
        }

        // 6. Tambah kolom divisi di transaksi_stok
        if (Schema::hasTable('transaksi_stok')) {
            Schema::table('transaksi_stok', function (Blueprint $table) {
                if (!Schema::hasColumn('transaksi_stok', 'divisi_asal_id')) {
                    $table->foreignId('divisi_asal_id')->nullable()->after('gudang_asal_id')->constrained('gudang_divisi')->onDelete('set null');
                }
                if (!Schema::hasColumn('transaksi_stok', 'divisi_tujuan_id')) {
                    $table->foreignId('divisi_tujuan_id')->nullable()->after('gudang_tujuan_id')->constrained('gudang_divisi')->onDelete('set null');
                }
            });
        }

        // 7. Tambah kolom divisi_id di fifo_layers
        if (Schema::hasTable('fifo_layers')) {
            Schema::table('fifo_layers', function (Blueprint $table) {
                if (!Schema::hasColumn('fifo_layers', 'divisi_id')) {
                    $table->foreignId('divisi_id')->nullable()->after('gudang_id')->constrained('gudang_divisi')->onDelete('set null');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('fifo_layers') && Schema::hasColumn('fifo_layers', 'divisi_id')) {
            Schema::table('fifo_layers', function (Blueprint $table) {
                $table->dropForeign(['divisi_id']);
                $table->dropColumn('divisi_id');
            });
        }

        if (Schema::hasTable('transaksi_stok')) {
            Schema::table('transaksi_stok', function (Blueprint $table) {
                if (Schema::hasColumn('transaksi_stok', 'divisi_tujuan_id')) {
                    $table->dropForeign(['divisi_tujuan_id']);
                    $table->dropColumn('divisi_tujuan_id');
                }
                if (Schema::hasColumn('transaksi_stok', 'divisi_asal_id')) {
                    $table->dropForeign(['divisi_asal_id']);
                    $table->dropColumn('divisi_asal_id');
                }
            });
        }

        if (Schema::hasTable('stock_opname') && Schema::hasColumn('stock_opname', 'divisi_id')) {
            Schema::table('stock_opname', function (Blueprint $table) {
                $table->dropForeign(['divisi_id']);
                $table->dropColumn('divisi_id');
            });
        }

        if (Schema::hasTable('pengeluaran_bahan_baku') && Schema::hasColumn('pengeluaran_bahan_baku', 'divisi_id')) {
            Schema::table('pengeluaran_bahan_baku', function (Blueprint $table) {
                $table->dropForeign(['divisi_id']);
                $table->dropColumn('divisi_id');
            });
        }

        if (Schema::hasTable('stok_gudang_batch') && Schema::hasColumn('stok_gudang_batch', 'divisi_id')) {
            Schema::table('stok_gudang_batch', function (Blueprint $table) {
                $table->dropForeign(['divisi_id']);
                $table->dropColumn('divisi_id');
            });
        }

        if (Schema::hasTable('stok_gudang') && Schema::hasColumn('stok_gudang', 'divisi_id')) {
            Schema::table('stok_gudang', function (Blueprint $table) {
                $table->dropForeign(['divisi_id']);
                $table->dropColumn('divisi_id');
            });
        }

        Schema::dropIfExists('gudang_divisi');
    }
};
