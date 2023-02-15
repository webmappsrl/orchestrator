<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;

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

// Public routes

Route::post('/login', [AuthController::class, 'login']);





// Protected routes

/**
 * Only users with special token ability can register users
 */
Route::post('/register', [AuthController::class, 'register'])
    ->middleware(['auth:sanctum', 'abilities:create-users']);

/**
 *
 */
Route::group(['middleware' => ['auth:sanctum']], function () {
    Route::get('/user', function (Request $request) {
        return $request->user();
    });
    Route::post('/logout', [AuthController::class, 'logout']);
});
