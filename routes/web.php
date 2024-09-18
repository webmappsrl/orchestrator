<?php

use App\Models\User;
use App\Jobs\TestJob;
use App\Models\Story;
use App\Mail\CustomerStoryFromMail;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Route;
use App\Mail\OrchestratorUserNotFound;
use App\Http\Controllers\QuoteController;
use App\Http\Controllers\DeadlineController;
use App\Http\Middleware\TestRouteAccess;
use Laravel\Nova\Http\Controllers\LoginController;

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
Route::get('/logs', [\Rap2hpoutre\LaravelLogViewer\LogViewerController::class, 'index']);

//testing routes
Route::middleware(TestRouteAccess::class)->group(function () {

    Route::get('/test-horizon', function () {
        TestJob::dispatch();
        return 'Test job dispatched';
    });

    // Rotta per testare l'email CustomerStoryFromMail
    Route::get('/test-customer-story-email', function () {
        // Creazione di una nuova istanza della mail
        $story = Story::inRandomOrder()->first();
        $email = new CustomerStoryFromMail($story);
        $environment = env('APP_ENV');

        // Invio dell'email a un indirizzo di test
        Mail::to(config('mail.from.address'))->send($email);

        return 'Email di test per CustomerStoryFromMail inviata!';
    });

    // Rotta per testare l'email OrchestratorUserNotFound
    Route::get('/test-user-not-found-email', function () {
        // Creazione di una nuova istanza della mail con un soggetto di esempio
        $subject = 'Test di segnalazione utente non trovato';
        $email = new OrchestratorUserNotFound($subject);

        // Invio dell'email a un indirizzo di test
        Mail::to('gennaromanzo@webmapp.it')->send($email);

        return 'Email di test per OrchestratorUserNotFound inviata!';
    });

    // Rotta per testare il rendering dell'email CustomerStoryFromMail
    Route::get('/test-render-customer-story-email', function () {
        // Creazione di una nuova istanza della mail con una storia di esempio
        $story = Story::inRandomOrder()->first(); // Assicurati che ci sia almeno una storia nel database
        $email = new CustomerStoryFromMail($story);

        // Rendering della vista della mail
        $view = View::make($email->content()->view, ['story' => $story]);

        return $view->render();
    });

    // Rotta per testare il rendering dell'email OrchestratorUserNotFound
    Route::get('/test-render-user-not-found-email', function () {
        // Creazione di una nuova istanza della mail con un soggetto di esempio
        $subject = 'Test di segnalazione utente non trovato';
        $email = new OrchestratorUserNotFound($subject);

        // Rendering della vista della mail
        $view = View::make($email->content()->view, ['sub' => $subject]);

        return $view->render();
    });
});

Route::get('/download-products-pdf', [\App\Http\Controllers\ProductPdfController::class, 'download'])
    ->name('products.pdf.download')
    ->middleware(['nova']);
