<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use Illuminate\Http\Request;

$user = User::first();
auth()->login($user);

$controller = app(\App\Http\Controllers\StokGudangController::class);

$request = Request::create('/stok-gudang/buku-pembantu', 'GET', [
    'gudang_id' => 3,
    'divisi_id' => 1,
    'start_date' => '2026-09-01',
    'end_date' => '2026-09-16'
]);

$response = $controller->bukuPembantuIndex($request);
echo "bukuPembantuIndex Response Status: OK, View Name: " . $response->name() . PHP_EOL;

$barangId = \App\Models\MasterBarang::value('id');
$requestMutasi = Request::create('/stok-gudang/buku-pembantu/mutasi', 'GET', [
    'barang_id' => $barangId,
    'gudang_id' => 3,
    'divisi_id' => 1,
    'start_date' => '2026-09-01',
    'end_date' => '2026-09-16'
]);

$resMutasi = $controller->bukuPembantuMutasi($requestMutasi);
echo "bukuPembantuMutasi Response: " . json_encode($resMutasi->getData()) . PHP_EOL;
