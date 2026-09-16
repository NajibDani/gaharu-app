<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$opname = DB::table('stock_opname')->where('kode_opname', 'like', '%SO-20260914145415%')->first();
echo "=== SO RECORD ===" . PHP_EOL;
print_r($opname);

if ($opname) {
    $details = DB::table('stock_opname_detail as sod')
        ->join('master_barang as b', 'sod.barang_id', '=', 'b.id')
        ->where('sod.stock_opname_id', $opname->id)
        ->where('b.nama', 'like', '%AJINOMOTO%')
        ->select('sod.*', 'b.nama as nama_barang')
        ->get();
    echo "=== SO DETAIL FOR AJINOMOTO ===" . PHP_EOL;
    print_r($details);
}

$pbk = DB::table('pengeluaran_bahan_baku')->where('keterangan', 'like', '%SO-20260914145415%')->first();
echo "=== PBK RECORD ===" . PHP_EOL;
print_r($pbk);

if ($pbk) {
    $pbkDetails = DB::table('pengeluaran_bahan_baku_detail as pbd')
        ->join('master_barang as b', 'pbd.barang_id', '=', 'b.id')
        ->where('pbd.pengeluaran_id', $pbk->id)
        ->where('b.nama', 'like', '%AJINOMOTO%')
        ->select('pbd.*', 'b.nama as nama_barang')
        ->get();
    echo "=== PBK DETAIL FOR AJINOMOTO ===" . PHP_EOL;
    print_r($pbkDetails);
}

// Check StokGudang for Ajinomoto (ID 13)
$stokGudang = DB::table('stok_gudang')->where('barang_id', 13)->get();
echo "=== STOK GUDANG FOR AJINOMOTO ===" . PHP_EOL;
print_r($stokGudang);

// Check TransaksiStok for Ajinomoto
$txs = DB::table('transaksi_stok')->where('barang_id', 13)->orderBy('id', 'desc')->take(10)->get();
echo "=== TRANSAKSI STOK FOR AJINOMOTO ===" . PHP_EOL;
print_r($txs);
