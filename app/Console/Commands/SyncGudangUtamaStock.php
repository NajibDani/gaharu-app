<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\MasterGudang;
use App\Models\PengeluaranBahanBaku;
use App\Models\StokGudang;
use App\Models\StokGudangBatch;
use App\Models\TransaksiStok;

class SyncGudangUtamaStock extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:sync-gudang-utama';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Rekonsiliasi data stok Gudang Utama dan sinkronisasi mutasi POS ke TransaksiStok';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Memulai rekonsiliasi stok Gudang Utama dan transaksi POS...');

        $gudangUtama = MasterGudang::getGudangUtama();
        $gudangUtamaId = MasterGudang::getGudangUtamaId();
        $outletIds = MasterGudang::getOutletGudangIds();
        $gudangGaharuId = MasterGudang::resolveOutletId('gaharu');
        $gudangKejinggaId = MasterGudang::resolveOutletId('kejingga');

        // 1. Perbaiki seluruh header PenjualanPos yang tercatat di Gudang Utama
        $misplacedPosHeaders = \App\Models\PenjualanPos::whereNotIn('gudang_id', $outletIds)->get();
        foreach ($misplacedPosHeaders as $posHeader) {
            $targetOutletId = MasterGudang::resolveOutletId($posHeader->kode_transaksi);
            $posHeader->update(['gudang_id' => $targetOutletId]);
        }

        // 2. Cari seluruh pengeluaran AUTO_POS yang salah masuk ke Gudang Utama
        $misplacedPosOutputs = PengeluaranBahanBaku::where('keterangan', 'like', 'AUTO_POS%')
            ->where('gudang_id', $gudangUtamaId)
            ->with(['details'])
            ->get();

        $countFixed = 0;
        foreach ($misplacedPosOutputs as $pbk) {
            $targetGudangId = MasterGudang::resolveOutletId($pbk->keterangan ?? $pbk->kode_pengeluaran);

            foreach ($pbk->details as $d) {
                $qty = (float) $d->qty;
                $barangId = $d->barang_id;

                // A. Kembalikan stok ke Gudang Utama
                $stokUtama = StokGudang::where('gudang_id', $gudangUtamaId)
                    ->where('barang_id', $barangId)
                    ->first();
                if ($stokUtama) {
                    $stokUtama->increment('jumlah', $qty);
                }

                // Kembalikan sisa batch FIFO di Gudang Utama
                $fifoRecords = DB::table('pengeluaran_bahan_baku_fifo')
                    ->where('pengeluaran_id', $pbk->id)
                    ->where('detail_id', $d->id)
                    ->get();

                foreach ($fifoRecords as $fr) {
                    if ($fr->batch_id) {
                        $batch = StokGudangBatch::find($fr->batch_id);
                        if ($batch && $batch->gudang_id == $gudangUtamaId) {
                            $batch->increment('qty_sisa', $fr->qty_keluar);
                            $batch->decrement('qty_keluar', $fr->qty_keluar);
                            $batch->update(['is_habis' => false]);
                        }
                    }
                }

                // B. Kurangkan stok di Gudang Outlet yang sebenarnya
                $stokOutlet = StokGudang::firstOrCreate(
                    ['gudang_id' => $targetGudangId, 'barang_id' => $barangId],
                    ['jumlah' => 0]
                );
                $stokOutlet->decrement('jumlah', $qty);
            }

            $pbk->update(['gudang_id' => $targetGudangId]);
            $countFixed++;
        }

        // 3. Pindahkan mutasi TransaksiStok POS yang salah memotong Gudang Utama
        $misplacedTxs = TransaksiStok::where('source_type', 'penjualan_pos')
            ->where('gudang_asal_id', $gudangUtamaId)
            ->get();

        $countMovedTx = 0;
        foreach ($misplacedTxs as $tx) {
            $targetOutletId = $gudangGaharuId;
            $pos = \App\Models\PenjualanPos::find($tx->source_id);
            if ($pos) {
                $targetOutletId = MasterGudang::resolveOutletId($pos->kode_transaksi);
            } else {
                $pbk = PengeluaranBahanBaku::find($tx->source_id);
                if ($pbk) {
                    $targetOutletId = MasterGudang::resolveOutletId($pbk->keterangan ?? $pbk->kode_pengeluaran);
                }
            }

            $alreadyExists = TransaksiStok::where('source_type', 'penjualan_pos')
                ->where('source_id', $tx->source_id)
                ->where('barang_id', $tx->barang_id)
                ->where('gudang_asal_id', $targetOutletId)
                ->exists();

            if ($alreadyExists) {
                $tx->delete();
            } else {
                $tx->update(['gudang_asal_id' => $targetOutletId]);
            }
            $countMovedTx++;
        }

        // 4. Pastikan seluruh transaksi AUTO_POS memiliki catatan di TransaksiStok
        $allPosOutputs = PengeluaranBahanBaku::where('keterangan', 'like', 'AUTO_POS%')
            ->with('details')
            ->get();

        $countSynced = 0;
        foreach ($allPosOutputs as $pbk) {
            foreach ($pbk->details as $d) {
                $exists = TransaksiStok::where('source_type', 'penjualan_pos')
                    ->where('source_id', $pbk->id)
                    ->where('barang_id', $d->barang_id)
                    ->exists();

                if (!$exists) {
                    TransaksiStok::create([
                        'tanggal'        => $pbk->tanggal ?? now(),
                        'tipe'           => 'keluar',
                        'source_type'    => 'penjualan_pos',
                        'source_id'      => $pbk->id,
                        'gudang_asal_id' => $pbk->gudang_id,
                        'barang_id'      => $d->barang_id,
                        'qty'            => (float) $d->qty,
                        'total_harga'    => (float) $d->hpp_total,
                        'created_by'     => $pbk->created_by ?? 1,
                    ]);
                    $countSynced++;
                }
            }
        }

        // 5. Rekonsiliasi ringkasan stok seluruh barang
        StokGudang::reconcileStockSummary();

        // Hapus migration record lama jika ada
        DB::table('migrations')->where('migration', 'like', '%reconcile_gudang_utama%')->delete();

        $this->info("Rekonsiliasi selesai!");
        $this->line("- Header PenjualanPos disesuaikan ke outlet: " . $misplacedPosHeaders->count());
        $this->line("- Dokumen AUTO_POS salah gudang dipindahkan: {$countFixed}");
        $this->line("- Mutasi TransaksiStok dipindahkan ke outlet: {$countMovedTx}");
        $this->line("- Mutasi TransaksiStok POS disinkronkan: {$countSynced}");

        return Command::SUCCESS;
    }
}
