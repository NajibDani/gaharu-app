<?php 
use App\Http\Controllers\PenjualanPosController;
use Illuminate\Http\Request;
$controller = new PenjualanPosController();
$start = microtime(true);
$response = $controller->show(4);
$end = microtime(true);
echo 'Time taken 1: ' . ($end - $start) . ' seconds\n';
$start = microtime(true);
$response = $controller->show(4);
$end = microtime(true);
echo 'Time taken 2: ' . ($end - $start) . ' seconds\n';

