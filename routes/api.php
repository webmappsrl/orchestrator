<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AppController;
use App\Http\Controllers\AIStoryController;
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

// AI Ticket Intelligence API Routes
Route::prefix('ai/stories')->middleware(['auth:sanctum'])->group(function () {
    // Trova storie simili a una storia specifica
    Route::get('/{story}/similar', [AIStoryController::class, 'findSimilar'])->name('ai.stories.similar');
    
    // Cerca storie simili a un testo
    Route::post('/search', [AIStoryController::class, 'searchByText'])->name('ai.stories.search');
    
    // Genera embedding per una storia
    Route::post('/{story}/generate-embedding', [AIStoryController::class, 'generateEmbedding'])->name('ai.stories.generate-embedding');
});
