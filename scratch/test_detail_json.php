<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

auth()->loginUsingId(1);

$pbks = App\Models\PengeluaranBahanBaku::latest()->take(10)->get();

foreach ($pbks as $pbk) {
    $start = microtime(true);
    try {
        $ctrl = app(App\Http\Controllers\PengeluaranBahanBakuController::class);
        $res = $ctrl->detailJson($pbk->id);
        $duration = round((microtime(true) - $start) * 1000, 2);
        echo "PBK ID {$pbk->id} ({$pbk->kode_pengeluaran}): {$duration} ms | Status: {$res->status()}\n";
    } catch (\Throwable $e) {
        $duration = round((microtime(true) - $start) * 1000, 2);
        echo "PBK ID {$pbk->id} ({$pbk->kode_pengeluaran}): {$duration} ms | ERROR: {$e->getMessage()}\n";
    }
}
