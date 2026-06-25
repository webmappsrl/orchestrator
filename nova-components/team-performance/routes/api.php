<?php

use App\Http\Controllers\Nova\TeamPerformanceController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    Route::get('/data', [TeamPerformanceController::class, 'data']);
});
