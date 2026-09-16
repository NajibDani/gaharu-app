<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\PengeluaranBahanBaku;
use App\Models\StockOpname;
use App\Models\TransaksiStok;
use App\Models\StokGudang;
use App\Models\StokGudangBatch;
use Illuminate\Support\Facades\DB;

echo "=== CLEANING UP PREMATURE TRANSAKSI STOK FOR DRAFT SO/PBK ===" . PHP_EOL;

// Find all draft PBKs from Stock Opname
$draftPbks = PengeluaranBahanBaku::where('status', 'draft')
    ->where(function($q) {
        $q->where('jenis_pengeluaran', 'stock_opname')
          ->orWhere('kode_pengeluaran', 'like', 'PBK-SO-%')
          ->orWhere('keterangan', 'like', '%Stock Opname%');
    })
    ->get();

echo "Found " . $draftPbks->count() . " Draft PBK records for Stock Opname." . PHP_EOL;

foreach ($draftPbks as $pbk) {
    echo "Processing PBK ID: {$pbk->id}, Kode: {$pbk->kode_pengeluaran}, Ket: {$pbk->keterangan}" . PHP_EOL;

    // Extract SO code
    $kodeOpname = null;
    if (preg_match('/SO-\d+/', $pbk->kode_pengeluaran, $matches)) {
        $kodeOpname = $matches[0];
    } elseif (preg_match('/SO-\d+/', $pbk->keterangan, $matches)) {
        $kodeOpname = $matches[0];
    }

    $opname = null;
    if ($kodeOpname) {
        $opname = StockOpname::where('kode_opname', $kodeOpname)->first();
    }

    if ($opname) {
        echo " -> Found linked StockOpname ID: {$opname->id}, Kode: {$opname->kode_opname}" . PHP_EOL;

        // Revert premature TransaksiStok if any
        $txs = TransaksiStok::where('source_type', 'stock_opname')
            ->where('source_id', $opname->id)
            ->get();

        echo "    Found " . $txs->count() . " TransaksiStok records to revert." . PHP_EOL;

        foreach ($txs as $tx) {
            $qty = (float) $tx->qty;
            if ($tx->tipe === 'keluar') {
                // Revert stock reduction: increment stok_gudang
                $stok = StokGudang::where('barang_id', $tx->barang_id)
                    ->where('gudang_id', $tx->gudang_asal_id)
                    ->when($tx->divisi_asal_id, fn($q) => $q->where('divisi_id', $tx->divisi_asal_id), fn($q) => $q->whereNull('divisi_id'))
                    ->first();
                if ($stok) {
                    $stok->increment('jumlah', $qty);
                    echo "    Reverted shortage for barang {$tx->barang_id}: +{$qty} to gudang {$tx->gudang_asal_id}" . PHP_EOL;
                }
            } elseif ($tx->tipe === 'masuk') {
                // Revert stock addition: decrement stok_gudang
                $stok = StokGudang::where('barang_id', $tx->barang_id)
                    ->where('gudang_id', $tx->gudang_tujuan_id)
                    ->when($tx->divisi_tujuan_id, fn($q) => $q->where('divisi_id', $tx->divisi_tujuan_id), fn($q) => $q->whereNull('divisi_id'))
                    ->first();
                if ($stok) {
                    $stok->decrement('jumlah', $qty);
                    echo "    Reverted surplus for barang {$tx->barang_id}: -{$qty} from gudang {$tx->gudang_tujuan_id}" . PHP_EOL;
                }
            }
            $tx->delete();
        }

        // Revert surplus batches if any
        $surplusBatches = StokGudangBatch::where('batch_number', 'SO-SURPLUS-' . $opname->kode_opname)->get();
        foreach ($surplusBatches as $sb) {
            echo "    Deleting surplus batch: {$sb->batch_number}" . PHP_EOL;
            $sb->delete();
        }

        // Delete JurnalPenyesuaian if any
        $jps = DB::table('jurnal_penyesuaian')->where('source_type', 'stock_opname')->where('source_id', $opname->id)->get();
        foreach ($jps as $jp) {
            DB::table('journal_items')->where('journal_id', $jp->id)->where('journal_type', 'jurnal_penyesuaian')->delete();
            DB::table('jurnal_penyesuaian')->where('id', $jp->id)->delete();
            echo "    Deleted JurnalPenyesuaian ID: {$jp->id}" . PHP_EOL;
        }

        // Reset StockOpname status if needed
        $opname->update(['status' => 'draft']);
    }
}
echo "Done!" . PHP_EOL;
