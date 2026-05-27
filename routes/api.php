<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AppController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\StoryController;
/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::prefix('app')->name('app.')->group(function () {
    Route::get("/{id}/config.json", [AppController::class, 'config'])->name('config');
});

Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
});

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/stories/{story}', [StoryController::class, 'show']);
    Route::post('/stories', [StoryController::class, 'store']);
    Route::patch('/stories/{story}', [StoryController::class, 'update']);
});
