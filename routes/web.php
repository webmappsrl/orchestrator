<?php

use App\Models\User;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\QuoteController;
use App\Http\Controllers\DeadlineController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/quote/{id}', [QuoteController::class, 'show'])->name('quote');
Route::get('/deadline/{id}', [DeadlineController::class, 'email'])->name('deadline.email');

//route to test the mailable
Route::get('/mailable', function () {
    //get the first user where roles contains customer
    $user = User::whereJsonContains('roles', 'customer')->firstOrFail();
    return new App\Mail\CustomerStoriesDigest($user);
});
