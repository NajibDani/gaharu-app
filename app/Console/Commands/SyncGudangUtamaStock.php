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

        $gudangGaharu = MasterGudang::where('nama', 'like', '%Gaharu%')->first();
        $gudangGaharuId = $gudangGaharu ? $gudangGaharu->id : 3;

        $gudangKejingga = MasterGudang::where('nama', 'like', '%KeJingga%')->first();
        $gudangKejinggaId = $gudangKejingga ? $gudangKejingga->id : 5;

        // 1. Cari seluruh pengeluaran AUTO_POS yang salah masuk ke Gudang Utama
        $misplacedPosOutputs = PengeluaranBahanBaku::where('keterangan', 'like', 'AUTO_POS%')
            ->where('gudang_id', $gudangUtamaId)
            ->with(['details'])
            ->get();

        $countFixed = 0;
        foreach ($misplacedPosOutputs as $pbk) {
            $isKejingga = str_contains(strtolower($pbk->keterangan ?? ''), 'kj') || str_contains(strtolower($pbk->kode_pengeluaran ?? ''), 'kj');
            $targetGudangId = $isKejingga ? $gudangKejinggaId : $gudangGaharuId;

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

                // C. Catat ke TransaksiStok untuk Gudang Outlet jika belum ada
                $exists = TransaksiStok::where('source_type', 'penjualan_pos')
                    ->where('source_id', $pbk->id)
                    ->where('barang_id', $barangId)
                    ->exists();

                if (!$exists) {
                    TransaksiStok::create([
                        'tanggal'        => $pbk->tanggal ?? now(),
                        'tipe'           => 'keluar',
                        'source_type'    => 'penjualan_pos',
                        'source_id'      => $pbk->id,
                        'gudang_asal_id' => $targetGudangId,
                        'barang_id'      => $barangId,
                        'qty'            => $qty,
                        'total_harga'    => (float) $d->hpp_total,
                        'created_by'     => $pbk->created_by ?? 1,
                    ]);
                }
            }

            $pbk->update(['gudang_id' => $targetGudangId]);
            $countFixed++;
        }

        // 2. Pastikan seluruh transaksi AUTO_POS yang sudah ada memiliki catatan di TransaksiStok
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

        // Hapus migration record lama jika ada
        DB::table('migrations')->where('migration', 'like', '%reconcile_gudang_utama%')->delete();

        $this->info("Rekonsiliasi selesai!");
        $this->line("- Dokumen AUTO_POS salah gudang dipindahkan: {$countFixed}");
        $this->line("- Mutasi TransaksiStok POS disinkronkan: {$countSynced}");

        return Command::SUCCESS;
    }
}
