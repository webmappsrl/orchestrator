<?php

use App\Http\Controllers\HetznerMonitoringController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    Route::get('/data', [HetznerMonitoringController::class, 'data']);
    Route::post('/refresh', [HetznerMonitoringController::class, 'refresh']);
    Route::get('/export', [HetznerMonitoringController::class, 'export']);
});
