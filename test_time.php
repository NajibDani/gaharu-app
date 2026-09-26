<?php 
use App\Http\Controllers\PenjualanPosController;
use Illuminate\Http\Request;
$start = microtime(true);
$controller = new PenjualanPosController();
$response = $controller->show(4);
$end = microtime(true);
echo 'Time taken: ' . ($end - $start) . ' seconds\n';

