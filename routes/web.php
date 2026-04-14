<?php

use App\Http\Controllers\DeadlineController;
use App\Http\Controllers\QuoteController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ScrumController;
use App\Models\User;
use Illuminate\Support\Facades\Route;

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
Route::get('report/{year?}', [ReportController::class, 'index']);

//route to test the mailable
Route::get('/mailable', function () {
    //get the first user where roles contains customer
    $user = User::whereJsonContains('roles', 'customer')->firstOrFail();

    return new App\Mail\CustomerStoriesDigest($user);
});
Route::get('/logs', [\Rap2hpoutre\LaravelLogViewer\LogViewerController::class, 'index']);

Route::get('/download-products-pdf', [\App\Http\Controllers\ProductPdfController::class, 'download'])
    ->name('products.pdf.download')
    ->middleware(['nova']);

Route::get('/app-report/{id}', [\App\Http\Controllers\AppReportController::class, 'download'])
    ->name('app.report.download')
    ->middleware(['nova']);

Route::get('/export/stories-excel/{fileName}', [StoriesExcelExportController::class, 'download'])
    ->name('stories.excel.download')
    ->middleware(['nova']);

Route::get('/scrum-meeting/{meetCode}', [ScrumController::class, 'createOrUpdateScrumStory'])
    ->middleware(['auth'])->name('scrum.meeting');
