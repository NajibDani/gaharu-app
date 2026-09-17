<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Http\Controllers\PengeluaranBahanBakuController;

$controller = app(PengeluaranBahanBakuController::class);

$pbks = \App\Models\PengeluaranBahanBaku::take(10)->get();

echo "Testing detailJson for 10 PBKs:\n";
foreach ($pbks as $p) {
    try {
        $res = $controller->detailJson($p->id);
        echo "[OK] PBK ID {$p->id} ({$p->kode_pengeluaran}) -> Status Code: " . $res->getStatusCode() . "\n";
    } catch (\Throwable $e) {
        echo "[ERROR] PBK ID {$p->id} ({$p->kode_pengeluaran}): " . $e->getMessage() . "\n";
        echo $e->getTraceAsString() . "\n";
    }
}
