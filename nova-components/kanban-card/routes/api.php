<?php

use Illuminate\Support\Facades\Route;
use Webmapp\KanbanCard\Http\Controllers\KanbanController;

Route::middleware('auth')->group(function () {
    Route::get('/items', [KanbanController::class, 'items']);
    Route::get('/counts', [KanbanController::class, 'counts']);
    Route::put('/items/{id}/status', [KanbanController::class, 'updateStatus']);
    Route::put('/items/reorder', [KanbanController::class, 'reorder']);
});
