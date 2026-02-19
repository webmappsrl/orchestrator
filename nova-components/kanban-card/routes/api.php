<?php

use Illuminate\Support\Facades\Route;
use Webmapp\KanbanCard\Http\Controllers\KanbanController;

Route::get('/items', [KanbanController::class, 'items']);
Route::put('/items/{id}/status', [KanbanController::class, 'updateStatus']);
