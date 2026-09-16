<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\StockOpname;
use App\Models\PengeluaranBahanBaku;
use App\Models\TransaksiStok;
use Illuminate\Support\Facades\DB;

echo "=== STOCK OPNAME ===" . PHP_EOL;
$sos = StockOpname::where('kode_opname', 'like', '%20260914145415%')->get();
foreach ($sos as $so) {
    echo "ID: {$so->id}, Kode: {$so->kode_opname}, Status: {$so->status}, Tgl: {$so->tanggal}" . PHP_EOL;
}

echo PHP_EOL . "=== PENGELUARAN BAHAN BAKU ===" . PHP_EOL;
$pbks = PengeluaranBahanBaku::where('kode_pengeluaran', 'like', '%20260914145415%')->orWhere('keterangan', 'like', '%20260914145415%')->get();
foreach ($pbks as $pbk) {
    echo "ID: {$pbk->id}, Kode: {$pbk->kode_pengeluaran}, Status: {$pbk->status}, Ket: {$pbk->keterangan}" . PHP_EOL;
}

echo PHP_EOL . "=== ALL PBK-SO IN DB ===" . PHP_EOL;
$pbkSos = PengeluaranBahanBaku::where('kode_pengeluaran', 'like', '%PBK-SO%')->orWhere('jenis_pengeluaran', 'stock_opname')->get();
foreach ($pbkSos as $p) {
    echo "ID: {$p->id}, Kode: {$p->kode_pengeluaran}, Status: {$p->status}, Jenis: {$p->jenis_pengeluaran}" . PHP_EOL;
}

echo PHP_EOL . "=== TRANSAKSI STOK SOURCE STOCK OPNAME ===" . PHP_EOL;
$txs = TransaksiStok::where('source_type', 'stock_opname')->get();
foreach ($txs as $tx) {
    echo "ID: {$tx->id}, Barang: {$tx->barang_id}, Qty: {$tx->qty}, Tipe: {$tx->tipe}, SrcID: {$tx->source_id}, Tgl: {$tx->tanggal}" . PHP_EOL;
}
