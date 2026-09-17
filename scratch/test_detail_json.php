<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

auth()->loginUsingId(1);

$pbks = App\Models\PengeluaranBahanBaku::latest()->take(5)->get();

foreach ($pbks as $pbk) {
    echo "Testing PBK ID {$pbk->id} ({$pbk->kode_pengeluaran}):\n";
    try {
        $ctrl = app(App\Http\Controllers\PengeluaranBahanBakuController::class);
        $res = $ctrl->detailJson($pbk->id);
        echo "  Status: " . $res->status() . "\n";
        $data = json_decode($res->getContent(), true);
        echo "  Details count: " . count($data['details'] ?? []) . "\n";
    } catch (\Throwable $e) {
        echo "  ERROR: " . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine() . "\n";
        echo $e->getTraceAsString() . "\n";
    }
}
