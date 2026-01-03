<?php

use App\Http\Controllers\DeadlineController;
use App\Http\Controllers\QuoteController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ScrumController;
use App\Http\Middleware\TestRouteAccess;
use App\Jobs\TestJob;
use App\Mail\CustomerStoryFromMail;
use App\Mail\OrchestratorUserNotFound;
use App\Models\Story;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
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

Route::get('/download-documentation-pdf/{id}', [\App\Http\Controllers\DocumentationPdfController::class, 'download'])
    ->name('documentation.pdf.download')
    ->middleware(['nova']);


Route::get('/generate-activity-report-pdf/{id}', [\App\Http\Controllers\ActivityReportPdfController::class, 'generate'])
    ->name('activity-report.pdf.generate')
    ->middleware(['nova']);

Route::get('/scrum-meeting/{meetCode}', [ScrumController::class, 'createOrUpdateScrumStory'])
    ->middleware(['auth'])->name('scrum.meeting');

// Changelog routes
Route::get('/changelog', [\App\Http\Controllers\ChangelogController::class, 'index'])
    ->middleware(['nova'])->name('changelog.index');

Route::get('/changelog/{minorVersion}', [\App\Http\Controllers\ChangelogController::class, 'showMinorRelease'])
    ->middleware(['nova'])->name('changelog.minor-release');

// Rotta per gestire la selezione del developer nella dashboard
Route::post('/set-dashboard-developer', function () {
    $developerId = request('developer_id');

    if ($developerId) {
        session(['selected_developer_id' => $developerId]);
    } else {
        session()->forget('selected_developer_id');
    }

    // Redirect to the previous page (works for both kanban and kanban-2)
    return back();
})->middleware(['auth'])->name('dashboard.set.developer');

// Rotta per gestire i filtri activity (utente e date range)
Route::post('/set-activity-filters', function () {
    $userId = request('user_id');
    $startDate = request('start_date');
    $endDate = request('end_date');

    if ($userId) {
        session(['activity_selected_user_id' => $userId]);
    } else {
        session()->forget('activity_selected_user_id');
    }

    if ($startDate) {
        session(['activity_start_date' => $startDate]);
    } else {
        session()->forget('activity_start_date');
    }

    if ($endDate) {
        session(['activity_end_date' => $endDate]);
    } else {
        session()->forget('activity_end_date');
    }

    return back();
})->middleware(['auth'])->name('activity.set.filters');

// Rotta per gestire i filtri activity-user (date range)
Route::post('/set-activity-user-filters', function () {
    $startDate = request('start_date');
    $endDate = request('end_date');

    if ($startDate) {
        session(['activity_user_start_date' => $startDate]);
    } else {
        session()->forget('activity_user_start_date');
    }

    if ($endDate) {
        session(['activity_user_end_date' => $endDate]);
    } else {
        session()->forget('activity_user_end_date');
    }

    return back();
})->middleware(['auth'])->name('activity.user.set.filters');

// Rotta per gestire i filtri activity-tags (date range e tag)
Route::post('/set-activity-tags-filters', function () {
    $startDate = request('start_date');
    $endDate = request('end_date');
    $tagFilter = request('tag_filter');

    if ($startDate) {
        session(['activity_tags_start_date' => $startDate]);
    } else {
        session()->forget('activity_tags_start_date');
    }

    if ($endDate) {
        session(['activity_tags_end_date' => $endDate]);
    } else {
        session()->forget('activity_tags_end_date');
    }

    if ($tagFilter) {
        session(['activity_tags_tag_filter' => $tagFilter]);
    } else {
        session()->forget('activity_tags_tag_filter');
    }

    return back();
})->middleware(['auth'])->name('activity.tags.set.filters');

// Rotta per gestire i filtri activity-customer (date range e customer)
Route::post('/set-activity-customer-filters', function () {
    $startDate = request('start_date');
    $endDate = request('end_date');
    $customerFilter = request('customer_filter');

    if ($startDate) {
        session(['activity_customer_start_date' => $startDate]);
    } else {
        session()->forget('activity_customer_start_date');
    }

    if ($endDate) {
        session(['activity_customer_end_date' => $endDate]);
    } else {
        session()->forget('activity_customer_end_date');
    }

    if ($customerFilter) {
        session(['activity_customer_customer_filter' => $customerFilter]);
    } else {
        session()->forget('activity_customer_customer_filter');
    }

    return back();
})->middleware(['auth'])->name('activity.customer.set.filters');

// Rotta per gestire i filtri activity-organizations (date range e organization)
Route::post('/set-activity-organizations-filters', function () {
    $startDate = request('start_date');
    $endDate = request('end_date');
    $organizationFilter = request('organization_filter');

    if ($startDate) {
        session(['activity_organizations_start_date' => $startDate]);
    } else {
        session()->forget('activity_organizations_start_date');
    }

    if ($endDate) {
        session(['activity_organizations_end_date' => $endDate]);
    } else {
        session()->forget('activity_organizations_end_date');
    }

    if ($organizationFilter) {
        session(['activity_organizations_organization_filter' => $organizationFilter]);
    } else {
        session()->forget('activity_organizations_organization_filter');
    }

    return back();
})->middleware(['auth'])->name('activity.organizations.set.filters');

// Rotta per gestire i filtri activity-tags-details (date range e tag_id)
Route::post('/set-activity-tags-details-filters', function () {
    $startDate = request('start_date');
    $endDate = request('end_date');
    $tagId = request('tag_id');

    if ($startDate) {
        session(['activity_tags_details_start_date' => $startDate]);
    } else {
        session()->forget('activity_tags_details_start_date');
    }

    if ($endDate) {
        session(['activity_tags_details_end_date' => $endDate]);
    } else {
        session()->forget('activity_tags_details_end_date');
    }

    if ($tagId) {
        session(['activity_tags_details_tag_id' => $tagId]);
    } else {
        session()->forget('activity_tags_details_tag_id');
    }

    return back();
})->middleware(['auth'])->name('activity.tags.details.set.filters');

// Rotta per gestire i filtri activity-customer-details (date range e customer_id)
Route::post('/set-activity-customer-details-filters', function () {
    $startDate = request('start_date');
    $endDate = request('end_date');
    $customerId = request('customer_id');

    if ($startDate) {
        session(['activity_customer_details_start_date' => $startDate]);
    } else {
        session()->forget('activity_customer_details_start_date');
    }

    if ($endDate) {
        session(['activity_customer_details_end_date' => $endDate]);
    } else {
        session()->forget('activity_customer_details_end_date');
    }

    if ($customerId) {
        session(['activity_customer_details_customer_id' => $customerId]);
    } else {
        session()->forget('activity_customer_details_customer_id');
    }

    return back();
})->middleware(['auth'])->name('activity.customer.details.set.filters');

// Rotta per gestire i filtri activity-organizations-details (date range e organization_id)
Route::post('/set-activity-organizations-details-filters', function () {
    $startDate = request('start_date');
    $endDate = request('end_date');
    $organizationId = request('organization_id');

    if ($startDate) {
        session(['activity_organizations_details_start_date' => $startDate]);
    } else {
        session()->forget('activity_organizations_details_start_date');
    }

    if ($endDate) {
        session(['activity_organizations_details_end_date' => $endDate]);
    } else {
        session()->forget('activity_organizations_details_end_date');
    }

    if ($organizationId) {
        session(['activity_organizations_details_organization_id' => $organizationId]);
    } else {
        session()->forget('activity_organizations_details_organization_id');
    }

    return back();
})->middleware(['auth'])->name('activity.organizations.details.set.filters');

