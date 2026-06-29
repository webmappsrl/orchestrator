<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AppController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\StoryController;
use App\Http\Controllers\Api\TagController;
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
    Route::get('/me', function (Request $request) {
        return response()->json([
            'id'    => $request->user()->id,
            'name'  => $request->user()->name,
            'email' => $request->user()->email,
        ]);
    });
    Route::get('/stories/{story}', [StoryController::class, 'show']);
    Route::post('/stories', [StoryController::class, 'store']);
    Route::patch('/stories/{story}', [StoryController::class, 'update']);

    Route::get('/tags', [TagController::class, 'index']);
    Route::get('/tags/{tag}', [TagController::class, 'show']);
    Route::post('/tags', [TagController::class, 'store']);
    Route::patch('/tags/{tag}', [TagController::class, 'update']);
    Route::post('/tags/{tag}/stories/{story}', [TagController::class, 'attachStory']);
    Route::delete('/tags/{tag}/stories/{story}', [TagController::class, 'detachStory']);
});
