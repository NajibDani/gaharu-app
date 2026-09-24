<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$barang = App\Models\MasterBarang::where('kode_barang', 'BBB682')->orWhere('nama', 'like', '%vanilla drip%')->first();
if (!$barang) {
    echo "Barang tidak ditemukan\n";
    exit;
}

echo "=== BARANG INFO ===\n";
echo "ID: {$barang->id} | Kode: {$barang->kode_barang} | Nama: {$barang->nama}\n";
echo "Satuan: {$barang->satuan} | Satuan Beli: {$barang->satuan_pembelian} | Konversi: {$barang->konversi_pembelian}\n";
echo "HPP Referensi: {$barang->hpp_referensi} | Harga Beli: {$barang->harga_beli}\n\n";

echo "=== STOK GUDANG BATCH ===\n";
$batches = DB::table('stok_gudang_batch')->where('barang_id', $barang->id)->get();
foreach ($batches as $b) {
    echo "ID: {$b->id} | Gudang: {$b->gudang_id} | Divisi: {$b->divisi_id} | Batch: {$b->batch_number} | Masuk: {$b->qty_masuk} | Keluar: {$b->qty_keluar} | Sisa: {$b->qty_sisa} | Harga: {$b->harga_per_qty} | Habis: {$b->is_habis}\n";
}

echo "\n=== PEMBELIAN DETAIL ===\n";
$pDetails = DB::table('pembelian_detail')->where('barang_id', $barang->id)->get();
foreach ($pDetails as $pd) {
    echo "ID: {$pd->id} | Pembelian ID: {$pd->pembelian_id} | Qty: {$pd->qty} | Satuan: {$pd->satuan_pembelian} | Konversi: {$pd->konversi_pembelian} | Harga/Qty: {$pd->harga_per_qty} | Subtotal: {$pd->subtotal}\n";
}

$gudangUtamaId = App\Models\MasterGudang::getGudangUtamaId();
echo "\n=== FIFO SERVICE TESTS (Gudang Utama ID: {$gudangUtamaId}) ===\n";
$fifo = app(App\Services\FifoService::class);
echo "getFifoHpp: " . $fifo->getFifoHpp($barang->id, $gudangUtamaId) . "\n";
echo "getHargaTerakhirBahan: " . $fifo->getHargaTerakhirBahan($barang->id, $gudangUtamaId) . "\n";
$est = $fifo->getEstimatedHargaFIFO($barang->id, 760, $gudangUtamaId);
echo "getEstimatedHargaFIFO(760 ML): Satuan=" . $est['harga_satuan'] . ", Total=" . $est['total_harga'] . "\n";
