<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

DB::table('migrations')->where('migration', 'like', '%cleanup_premature_draft_so_mutations%')->delete();
echo "Migration entry deleted from DB." . PHP_EOL;
