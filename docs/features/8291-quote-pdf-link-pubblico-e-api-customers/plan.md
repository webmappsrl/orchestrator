> Ticket: oc:8291

# API Quotes: PDF via API con link pubblico firmato ed endpoint customers — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.
>
> **⚠️ Override Webmapp:** in questo progetto i commit sono vietati durante l'esecuzione automatica. I passi "Commit" di ogni task sono istruzioni testuali per lo sviluppatore/dev che esegue il piano — nessun `git commit`/`git add`/`git push` va eseguito autonomamente da un agente. La fase di commit reale avviene solo dopo review-gate esplicito dell'utente (vedi `wm-plan` → `execution: review-gate`).

**Goal:** Rendere l'intero ciclo dei preventivi (PDF, link pubblico, lettura clienti, ordinamento/paginazione, dettaglio con relazioni) eseguibile via API, senza passare dal backend Nova nel browser.

**Architecture:** Estrazione della generazione PDF in un `QuotePdfService` condiviso tra rotta web esistente e nuove rotte API/pubblica; nuovo `CustomerController` API con lo stesso pattern di autorizzazione inline già usato da `ProductController`; arricchimento non-breaking di `Api/QuoteController@index/show` (paginazione/include sempre opt-in).

**Tech Stack:** Laravel 10, PostgreSQL, Sanctum (bearer auth), `barryvdh/laravel-dompdf`, PHPUnit (classi con `/** @test */`, non Pest), `DatabaseTransactions`.

## Global Constraints

- Test: `docker exec php81_orchestrator php artisan test --filter=<TestClass>` — DB di supporto `orchestrator_test`, tutte le classi Feature usano `use DatabaseTransactions;`.
- Stile test: classi PHPUnit, metodi `/** @test */ public function nome_in_snake_case_italiano(): void`, come in `tests/Feature/Api/QuoteApiTest.php`.
- Nessuna migrazione fisica di dati esistenti oltre a quanto esplicitamente previsto (backfill `vat` è opt-in via `--apply`, mai automatico al deploy).
- Ruoli abilitati per le nuove API (`Customer`, `pdf`, `pdf-link`): `Admin`/`Manager`/`Developer` — stesso pattern `abort_unless` di `app/Http/Controllers/Api/ProductController.php`, nessuna Policy dedicata (debito accettato, coerente con la decisione già presa in oc:8286 per Product/RecurringProduct).
- `expires_in_days` per il link pubblico: `integer|min:1|max:90`, default 30 se omesso.
- Filename PDF sempre sanitizzato (solo alfanumerico/spazio/underscore/trattino, troncato) prima di finire in `Content-Disposition`, su tutte le rotte che generano il PDF (web, API, pubblica).
- Rotta pubblica firmata: middleware `['signed', 'throttle:30,1']`, mai `auth:sanctum`.
- Nessuna colonna `company_name` da creare: nella response API è sempre un alias di sola lettura su `full_name`.
- Repo di destinazione per ogni file: sempre `/Users/rubensgarofalo/Sites/Webmapp/orchestrator` (repo principale, nessun submodule coinvolto).

---

### Task 1: `QuotePdfService` — estrazione della generazione PDF + sanitizzazione filename

**Files:**
- Create: `app/Services/QuotePdfService.php`
- Modify: `app/Http/Controllers/QuoteController.php:1-84` (rimuove `generatePdf()`, `show()` usa il servizio)
- Test: `tests/Feature/QuotePdfServiceTest.php`

**Interfaces:**
- Produces: `App\Services\QuotePdfService::stream(Quote $quote, string $lang): \Symfony\Component\HttpFoundation\Response` — genera e streama il PDF. `App\Services\QuotePdfService::fileName(Quote $quote): string` — nome file sanitizzato con estensione `.pdf`. Entrambi i metodi sono usati dai Task 2 e 3.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Quote;
use App\Services\QuotePdfService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class QuotePdfServiceTest extends TestCase
{
    use DatabaseTransactions;

    /** @test */
    public function file_name_sanitizza_caratteri_non_alfanumerici_dal_nome_cliente(): void
    {
        $customer = Customer::factory()->create(['full_name' => 'Acme "Corp" / Srl <script>&']);
        $quote = Quote::factory()->create(['customer_id' => $customer->id]);

        $fileName = (new QuotePdfService())->fileName($quote);

        $this->assertStringEndsWith('.pdf', $fileName);
        $this->assertStringNotContainsString('/', $fileName);
        $this->assertStringNotContainsString('"', $fileName);
        $this->assertStringNotContainsString('<', $fileName);
        $this->assertStringNotContainsString('&', $fileName);
    }

    /** @test */
    public function file_name_tronca_nomi_molto_lunghi(): void
    {
        $customer = Customer::factory()->create(['full_name' => str_repeat('A', 200)]);
        $quote = Quote::factory()->create(['customer_id' => $customer->id]);

        $fileName = (new QuotePdfService())->fileName($quote);

        $this->assertLessThanOrEqual(110, strlen($fileName));
    }

    /** @test */
    public function stream_restituisce_una_response_pdf(): void
    {
        $customer = Customer::factory()->create(['full_name' => 'Cliente Di Prova']);
        $quote = Quote::factory()->create(['customer_id' => $customer->id, 'additional_services' => []]);

        $response = (new QuotePdfService())->stream($quote, 'it');

        $this->assertStringContainsString('application/pdf', $response->headers->get('Content-Type'));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker exec php81_orchestrator php artisan test --filter=QuotePdfServiceTest`
Expected: FAIL con `Class "App\Services\QuotePdfService" not found`

- [ ] **Step 3: Write minimal implementation**

```php
<?php

namespace App\Services;

use App\Models\Quote;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class QuotePdfService
{
    /**
     * Generate and stream the quote PDF using DomPDF.
     */
    public function stream(Quote $quote, string $lang): Response
    {
        $quote->clearEmptyAdditionalServicesTranslations();
        App::setLocale($lang);

        $config = config('quote-pdf');

        $pdf = Pdf::loadView('quote-pdf', compact('quote', 'config'))
            ->setPaper($config['page']['size'], $config['page']['orientation'])
            ->setOption('enable-local-file-access', true)
            ->setOption('isRemoteEnabled', true)
            ->setOption('isHtml5ParserEnabled', true);

        return $pdf->stream($this->fileName($quote));
    }

    /**
     * Build a sanitized filename for the quote PDF.
     * Customer name is free text (no validation, no cast): strip anything
     * that isn't alphanumeric/space/underscore/dash before it reaches
     * Content-Disposition, and cap the length.
     */
    public function fileName(Quote $quote): string
    {
        $customerName = $quote->customer->full_name ?? $quote->customer->name ?? 'Cliente';

        $safeName = preg_replace('/[^A-Za-z0-9 _-]/', '', $customerName);
        $safeName = trim(preg_replace('/\s+/', ' ', $safeName));
        $safeName = substr($safeName, 0, 80);

        if ($safeName === '') {
            $safeName = 'Cliente';
        }

        return __('Preventivo_WEBMAPP_' . $safeName) . '.pdf';
    }
}
```

Modify `app/Http/Controllers/QuoteController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Models\Quote;
use App\Http\Requests\StoreQuoteRequest;
use App\Http\Requests\UpdateQuoteRequest;
use App\Services\QuotePdfService;
use Illuminate\Http\Request;

class QuoteController extends Controller
{
    public function index()
    {
        //
    }

    public function create()
    {
        //
    }

    public function store(StoreQuoteRequest $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, $id, QuotePdfService $pdfService)
    {
        $quote = Quote::findOrFail($id);
        $lang = $request->get('lang', 'it');

        return $pdfService->stream($quote, $lang);
    }

    public function edit(Quote $quote)
    {
        //
    }

    public function update(UpdateQuoteRequest $request, Quote $quote)
    {
        //
    }

    public function destroy(Quote $quote)
    {
        //
    }
}
```

(Rimossi l'import di `Barryvdh\DomPDF\Facade\Pdf` e `Illuminate\Support\Facades\App`, non più usati direttamente in questo controller; rimosso il metodo privato `generatePdf()`.)

- [ ] **Step 4: Run test to verify it passes**

Run: `docker exec php81_orchestrator php artisan test --filter=QuotePdfServiceTest`
Expected: PASS (3/3)

- [ ] **Step 5: Verifica manuale di non-regressione sulla rotta web**

Run: `docker exec php81_orchestrator php artisan route:list --name=quote` (verifica che la rotta `GET /quote/{id}` esista ancora) e apri `http://localhost:8099/quote/162` in browser (o l'ID di un preventivo reale) per confermare che il PDF si generi ancora come prima del refactor.

- [ ] **Step 6: Commit**

```bash
git add app/Services/QuotePdfService.php app/Http/Controllers/QuoteController.php tests/Feature/QuotePdfServiceTest.php
git commit -m "refactor(oc:8291): extract QuotePdfService with sanitized filename"
```

---

### Task 2: `GET /api/quotes/{quote}/pdf` — download autenticato via bearer

**Files:**
- Modify: `app/Http/Controllers/Api/QuoteController.php:1-20` (aggiunge metodo `pdf()`)
- Modify: `routes/api.php:50-58` (aggiunge rotta)
- Test: `tests/Feature/Api/QuotePdfApiTest.php`

**Interfaces:**
- Consumes: `App\Services\QuotePdfService::stream(Quote $quote, string $lang): Response` (Task 1)
- Produces: rotta `GET /api/quotes/{quote}/pdf` — nessuna nuova interfaccia consumata da altri task.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Api;

use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\Quote;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class QuotePdfApiTest extends TestCase
{
    use DatabaseTransactions;

    private function actingAsAdmin(): User
    {
        $user = User::factory()->create(['roles' => [UserRole::Admin]]);
        Sanctum::actingAs($user);
        return $user;
    }

    /** @test */
    public function utente_non_autenticato_ottiene_401_su_pdf(): void
    {
        $customer = Customer::factory()->create();
        $quote = Quote::factory()->create(['customer_id' => $customer->id, 'additional_services' => []]);

        $this->getJson("/api/quotes/{$quote->id}/pdf")->assertStatus(401);
    }

    /** @test */
    public function utente_autenticato_scarica_il_pdf(): void
    {
        $this->actingAsAdmin();
        $customer = Customer::factory()->create();
        $quote = Quote::factory()->create(['customer_id' => $customer->id, 'additional_services' => []]);

        $response = $this->get("/api/quotes/{$quote->id}/pdf");

        $response->assertStatus(200);
        $this->assertStringContainsString('application/pdf', $response->headers->get('Content-Type'));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker exec php81_orchestrator php artisan test --filter=QuotePdfApiTest`
Expected: FAIL — rotta `pdf` non definita (404/`RouteNotFoundException` o simile)

- [ ] **Step 3: Write minimal implementation**

In `app/Http/Controllers/Api/QuoteController.php`, aggiungere l'import e il metodo:

```php
use App\Services\QuotePdfService;
```

```php
    /**
     * Stream the quote PDF (bearer auth).
     */
    public function pdf(Request $request, Quote $quote, QuotePdfService $pdfService)
    {
        $this->authorize('view', $quote);

        $lang = $request->get('lang', 'it');

        return $pdfService->stream($quote, $lang);
    }
```

In `routes/api.php`, dentro il gruppo `auth:sanctum`, subito dopo la riga `Route::get('/quotes/{quote}', [QuoteController::class, 'show']);`:

```php
    Route::get('/quotes/{quote}/pdf', [QuoteController::class, 'pdf']);
```

- [ ] **Step 4: Run test to verify it passes**

Run: `docker exec php81_orchestrator php artisan test --filter=QuotePdfApiTest`
Expected: PASS (2/2)

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/Api/QuoteController.php routes/api.php tests/Feature/Api/QuotePdfApiTest.php
git commit -m "feat(oc:8291): add GET /api/quotes/{quote}/pdf bearer download"
```

---

### Task 3: `POST /api/quotes/{quote}/pdf-link` + rotta pubblica firmata con throttle

**Files:**
- Create: `app/Http/Controllers/QuotePublicController.php`
- Modify: `app/Http/Controllers/Api/QuoteController.php` (aggiunge metodo `pdfLink()`)
- Modify: `routes/api.php` (nuova rotta `pdf-link` dentro `auth:sanctum`)
- Modify: `routes/web.php:1-24` (nuova rotta pubblica firmata)
- Test: `tests/Feature/Api/QuotePdfApiTest.php` (estende il file del Task 2)

**Interfaces:**
- Consumes: `App\Services\QuotePdfService::stream()` (Task 1)
- Produces: rotta nominata `quotes.pdf.public` usata da `URL::temporarySignedRoute()` in `pdfLink()`.

- [ ] **Step 1: Write the failing test**

Aggiungere a `tests/Feature/Api/QuotePdfApiTest.php`:

```php
    /** @test */
    public function pdf_link_genera_url_firmato_con_scadenza(): void
    {
        $this->actingAsAdmin();
        $customer = Customer::factory()->create();
        $quote = Quote::factory()->create(['customer_id' => $customer->id, 'additional_services' => []]);

        $response = $this->postJson("/api/quotes/{$quote->id}/pdf-link", [
            'lang' => 'it',
            'expires_in_days' => 5,
        ]);

        $response->assertStatus(201);
        $response->assertJsonStructure(['url', 'expires_at']);
        $this->assertStringContainsString('signature=', $response->json('url'));
    }

    /** @test */
    public function pdf_link_rifiuta_expires_in_days_oltre_90(): void
    {
        $this->actingAsAdmin();
        $customer = Customer::factory()->create();
        $quote = Quote::factory()->create(['customer_id' => $customer->id, 'additional_services' => []]);

        $this->postJson("/api/quotes/{$quote->id}/pdf-link", ['expires_in_days' => 91])
            ->assertStatus(422);
    }

    /** @test */
    public function link_pubblico_firmato_restituisce_il_pdf_senza_autenticazione(): void
    {
        $customer = Customer::factory()->create();
        $quote = Quote::factory()->create(['customer_id' => $customer->id, 'additional_services' => []]);

        $url = \Illuminate\Support\Facades\URL::temporarySignedRoute(
            'quotes.pdf.public',
            now()->addDays(1),
            ['quote' => $quote->id, 'lang' => 'it']
        );

        $response = $this->get($url);

        $response->assertStatus(200);
        $this->assertStringContainsString('application/pdf', $response->headers->get('Content-Type'));
    }

    /** @test */
    public function link_pubblico_con_firma_non_valida_viene_rifiutato(): void
    {
        $customer = Customer::factory()->create();
        $quote = Quote::factory()->create(['customer_id' => $customer->id, 'additional_services' => []]);

        $this->get("/public/quotes/{$quote->id}/pdf?lang=it&expires=9999999999&signature=invalid")
            ->assertStatus(403);
    }
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker exec php81_orchestrator php artisan test --filter=QuotePdfApiTest`
Expected: FAIL — `pdf-link` non definita, rotta `quotes.pdf.public` inesistente

- [ ] **Step 3: Write minimal implementation**

Creare `app/Http/Controllers/QuotePublicController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Models\Quote;
use App\Services\QuotePdfService;
use Illuminate\Http\Request;

class QuotePublicController extends Controller
{
    /**
     * Public, signature-verified PDF download (no auth:sanctum).
     * The `signed` middleware rejects the request before this method runs
     * if the signature/expiry is invalid.
     */
    public function show(Request $request, Quote $quote, QuotePdfService $pdfService)
    {
        $lang = $request->get('lang', 'it');

        return $pdfService->stream($quote, $lang);
    }
}
```

In `routes/web.php`, dopo la riga `Route::get('/quote/{id}', [QuoteController::class, 'show'])->name('quote');`:

```php
use App\Http\Controllers\QuotePublicController;
```

```php
Route::get('/public/quotes/{quote}/pdf', [QuotePublicController::class, 'show'])
    ->name('quotes.pdf.public')
    ->middleware(['signed', 'throttle:30,1']);
```

In `app/Http/Controllers/Api/QuoteController.php`, aggiungere l'import e il metodo:

```php
use Illuminate\Support\Facades\URL;
```

```php
    /**
     * Generate a temporary signed public URL for the quote PDF (no auth
     * required to open it — meant to be embedded in an email to the
     * customer). expires_in_days is capped at 90 to avoid a de-facto
     * permanent link; the signature cannot be revoked before expiry short
     * of rotating APP_KEY (which invalidates every signed link project-wide).
     *
     * @response 201 array{url: string, expires_at: string}
     */
    public function pdfLink(Request $request, Quote $quote): JsonResponse
    {
        $this->authorize('view', $quote);

        $validated = $request->validate([
            'lang'             => ['sometimes', 'string', 'max:5'],
            'expires_in_days'  => ['sometimes', 'integer', 'min:1', 'max:90'],
        ]);

        $lang = $validated['lang'] ?? 'it';
        $expiresAt = now()->addDays($validated['expires_in_days'] ?? 30);

        $url = URL::temporarySignedRoute('quotes.pdf.public', $expiresAt, [
            'quote' => $quote->id,
            'lang'  => $lang,
        ]);

        return response()->json([
            'url'        => $url,
            'expires_at' => $expiresAt->toIso8601String(),
        ], 201);
    }
```

In `routes/api.php`, dentro il gruppo `auth:sanctum`, subito dopo la rotta `pdf` aggiunta nel Task 2:

```php
    Route::post('/quotes/{quote}/pdf-link', [QuoteController::class, 'pdfLink']);
```

- [ ] **Step 4: Run test to verify it passes**

Run: `docker exec php81_orchestrator php artisan test --filter=QuotePdfApiTest`
Expected: PASS (6/6 nel file, incluse le 2 del Task 2)

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/QuotePublicController.php app/Http/Controllers/Api/QuoteController.php routes/api.php routes/web.php tests/Feature/Api/QuotePdfApiTest.php
git commit -m "feat(oc:8291): add signed public PDF link with 90-day cap and throttle"
```

---

### Task 4: Migration `vat`/`address` su customers + `Customer` model (fillable + accessor `contact_emails`)

**Files:**
- Create: `database/migrations/2026_07_28_120000_add_vat_and_address_to_customers_table.php`
- Modify: `app/Models/Customer.php:32-56` (`$fillable`), aggiunge `getContactEmailsAttribute()`
- Test: `tests/Unit/CustomerContactEmailsTest.php`

**Interfaces:**
- Produces: `App\Models\Customer::$vat`, `App\Models\Customer::$address` (colonne), `App\Models\Customer->contact_emails` (accessor, `array`) — consumato dal Task 5 (`CustomerController`).

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Unit;

use App\Models\Customer;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class CustomerContactEmailsTest extends TestCase
{
    use DatabaseTransactions;

    /** @test */
    public function contact_emails_splitta_su_virgola_e_spazio_come_nova(): void
    {
        $customer = Customer::factory()->create([
            'email' => 'l.bevilacqua@qzrstudio.com,stefan.guerra@lucense.it',
        ]);

        $this->assertEquals(
            ['l.bevilacqua@qzrstudio.com', 'stefan.guerra@lucense.it'],
            $customer->contact_emails
        );
    }

    /** @test */
    public function contact_emails_e_array_vuoto_se_email_e_null(): void
    {
        $customer = Customer::factory()->create(['email' => null]);

        $this->assertEquals([], $customer->contact_emails);
    }

    /** @test */
    public function vat_e_address_sono_scrivibili_e_nullable(): void
    {
        $customer = Customer::factory()->create(['vat' => '01234567890', 'address' => 'Via Roma 1, Pisa']);

        $this->assertEquals('01234567890', $customer->fresh()->vat);
        $this->assertEquals('Via Roma 1, Pisa', $customer->fresh()->address);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker exec php81_orchestrator php artisan test --filter=CustomerContactEmailsTest`
Expected: FAIL — colonna `vat`/`address` inesistente, `contact_emails` non definito

- [ ] **Step 3: Write minimal implementation**

Creare `database/migrations/2026_07_28_120000_add_vat_and_address_to_customers_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->string('vat')->nullable();
            $table->text('address')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn(['vat', 'address']);
        });
    }
};
```

In `app/Models/Customer.php`, aggiungere `'vat'` e `'address'` a `$fillable`:

```php
    protected $fillable = [
        'name',
        'description',
        'wmpm_id',
        'notes',
        'hs_id',
        'domain_name',
        'full_name',
        'acronym',
        'has_subscription',
        'subscription_amount',
        'subscription_last_payment',
        'subscription_last_covered_year',
        'subscription_last_invoice',
        'score_cash',
        'score_pain',
        'score_business',
        'contract_expiration_date',
        'contract_value',
        'status',
        'phone',
        'mobile_phone',
        'user_id',
        'associated_user_id',
        'vat',
        'address',
    ];
```

Aggiungere l'accessor, subito dopo `setMobilePhoneAttribute()`:

```php
    /**
     * Contact emails as a structured array. `email` is free text (no cast,
     * not validated) — same split logic already used by the Nova resource
     * for display, kept identical for API/Nova consistency.
     */
    public function getContactEmailsAttribute(): array
    {
        if (empty($this->attributes['email'])) {
            return [];
        }

        return collect(preg_split('/[\s,]+/', trim($this->attributes['email'])))
            ->filter()
            ->values()
            ->all();
    }
```

- [ ] **Step 4: Run test to verify it passes**

Run: `docker exec php81_orchestrator bash -c "DB_DATABASE=orchestrator_test php artisan migrate"` (applica la nuova migration sul DB di test)
Run: `docker exec php81_orchestrator php artisan test --filter=CustomerContactEmailsTest`
Expected: PASS (3/3)

- [ ] **Step 5: Commit**

```bash
git add database/migrations/2026_07_28_120000_add_vat_and_address_to_customers_table.php app/Models/Customer.php tests/Unit/CustomerContactEmailsTest.php
git commit -m "feat(oc:8291): add vat/address columns and contact_emails accessor"
```

---

### Task 5: `CustomerController` API — `index()`/`show()` + rotte + ruoli

**Files:**
- Create: `app/Http/Controllers/Api/CustomerController.php`
- Modify: `routes/api.php` (nuove rotte `customers`)
- Test: `tests/Feature/Api/CustomerApiTest.php`

**Interfaces:**
- Consumes: `App\Models\Customer->contact_emails` (Task 4), `App\Models\Customer::owner(): BelongsTo` (esistente)
- Produces: `GET /api/customers`, `GET /api/customers/{customer}` — nessuna interfaccia consumata da altri task di questo piano.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Api;

use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CustomerApiTest extends TestCase
{
    use DatabaseTransactions;

    private function actingAsAdmin(): User
    {
        $user = User::factory()->create(['roles' => [UserRole::Admin]]);
        Sanctum::actingAs($user);
        return $user;
    }

    private function actingAsCustomerRole(): User
    {
        $user = User::factory()->create(['roles' => [UserRole::Customer]]);
        Sanctum::actingAs($user);
        return $user;
    }

    /** @test */
    public function utente_non_autenticato_ottiene_401(): void
    {
        $this->getJson('/api/customers')->assertStatus(401);
    }

    /** @test */
    public function ruolo_customer_non_puo_accedere(): void
    {
        $this->actingAsCustomerRole();

        $this->getJson('/api/customers')->assertStatus(403);
    }

    /** @test */
    public function index_restituisce_i_campi_attesi(): void
    {
        $this->actingAsAdmin();
        $owner = User::factory()->create();
        $customer = Customer::factory()->create([
            'name'      => 'acme_srl',
            'full_name' => 'Acme S.r.l.',
            'vat'       => '01234567890',
            'address'   => 'Via Roma 1, Pisa',
            'email'     => 'a@acme.it,b@acme.it',
            'phone'     => '0501234567',
            'status'    => 'active',
            'user_id'   => $owner->id,
            'notes'     => 'nota interna',
        ]);

        $response = $this->getJson('/api/customers')->assertStatus(200);

        $item = collect($response->json())->firstWhere('id', $customer->id);
        $this->assertEquals('acme_srl', $item['name']);
        $this->assertEquals('Acme S.r.l.', $item['company_name']);
        $this->assertEquals('01234567890', $item['vat']);
        $this->assertEquals('Via Roma 1, Pisa', $item['address']);
        $this->assertEquals(['a@acme.it', 'b@acme.it'], $item['contact_emails']);
        $this->assertEquals('0501234567', $item['phone']);
        $this->assertEquals('active', $item['status']);
        $this->assertEquals(['id' => $owner->id, 'name' => $owner->name], $item['owner']);
        $this->assertEquals('nota interna', $item['notes']);
    }

    /** @test */
    public function show_restituisce_il_singolo_customer(): void
    {
        $this->actingAsAdmin();
        $customer = Customer::factory()->create(['name' => 'progetto_x']);

        $this->getJson("/api/customers/{$customer->id}")
            ->assertStatus(200)
            ->assertJsonPath('id', $customer->id)
            ->assertJsonPath('name', 'progetto_x');
    }

    /** @test */
    public function filtro_per_status_funziona(): void
    {
        $this->actingAsAdmin();
        Customer::factory()->create(['status' => 'active']);
        Customer::factory()->create(['status' => 'lost']);

        $response = $this->getJson('/api/customers?status=active')->assertStatus(200);

        $this->assertTrue(collect($response->json())->every(fn($c) => $c['status'] === 'active'));
    }

    /** @test */
    public function ricerca_per_nome_sanitizza_i_caratteri_like(): void
    {
        $this->actingAsAdmin();
        Customer::factory()->create(['name' => 'acme_srl']);
        Customer::factory()->create(['name' => 'altro_cliente']);

        $response = $this->getJson('/api/customers?search=acme%25')->assertStatus(200);

        $this->assertCount(0, $response->json());
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker exec php81_orchestrator php artisan test --filter=CustomerApiTest`
Expected: FAIL — `/api/customers` non definita (404)

- [ ] **Step 3: Write minimal implementation**

Creare `app/Http/Controllers/Api/CustomerController.php`:

```php
<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    /**
     * List customers, optionally filtered by status or name search.
     *
     * @response array<array{id: int, name: string, company_name: string|null, vat: string|null, address: string|null, contact_emails: array<string>, phone: string|null, status: string|null, owner: array{id: int, name: string}|null, notes: string|null}>
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorizeRole($request);

        $query = Customer::query()->with('owner');

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        if ($request->filled('search')) {
            $search = str_replace(['%', '_'], ['\%', '\_'], $request->string('search'));
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('full_name', 'like', "%{$search}%");
            });
        }

        $customers = $query->get();

        return response()->json($customers->map(fn(Customer $c) => $this->formatCustomer($c)));
    }

    /**
     * Retrieve a customer.
     *
     * @response array{id: int, name: string, company_name: string|null, vat: string|null, address: string|null, contact_emails: array<string>, phone: string|null, status: string|null, owner: array{id: int, name: string}|null, notes: string|null}
     */
    public function show(Request $request, Customer $customer): JsonResponse
    {
        $this->authorizeRole($request);

        $customer->load('owner');

        return response()->json($this->formatCustomer($customer));
    }

    private function authorizeRole(Request $request): void
    {
        $user = $request->user();
        abort_unless(
            $user->hasRole(UserRole::Admin) || $user->hasRole(UserRole::Manager) || $user->hasRole(UserRole::Developer),
            403
        );
    }

    private function formatCustomer(Customer $customer): array
    {
        return [
            'id'             => $customer->id,
            'name'           => $customer->name,
            'company_name'   => $customer->full_name,
            'vat'            => $customer->vat,
            'address'        => $customer->address,
            'contact_emails' => $customer->contact_emails,
            'phone'          => $customer->phone,
            'status'         => $customer->status,
            'owner'          => $customer->owner ? [
                'id'   => $customer->owner->id,
                'name' => $customer->owner->name,
            ] : null,
            'notes'          => $customer->notes,
        ];
    }
}
```

In `routes/api.php`, aggiungere l'import:

```php
use App\Http\Controllers\Api\CustomerController;
```

e le rotte, dentro il gruppo `auth:sanctum`, dopo il blocco `recurring-products`:

```php
    Route::get('/customers', [CustomerController::class, 'index']);
    Route::get('/customers/{customer}', [CustomerController::class, 'show']);
```

- [ ] **Step 4: Run test to verify it passes**

Run: `docker exec php81_orchestrator php artisan test --filter=CustomerApiTest`
Expected: PASS (6/6)

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/Api/CustomerController.php routes/api.php tests/Feature/Api/CustomerApiTest.php
git commit -m "feat(oc:8291): add GET /api/customers index and show"
```

---

### Task 6: Comando Artisan `customers:backfill-vat` (dry-run di default, report su file, `--apply`)

**Files:**
- Create: `app/Console/Commands/BackfillCustomerVatFromHeading.php`
- Test: `tests/Feature/Console/BackfillCustomerVatFromHeadingTest.php`

**Interfaces:**
- Consumes: `App\Models\Customer::$vat`, `App\Models\Customer::$heading` (colonne esistenti/Task 4)
- Produces: nessuna interfaccia consumata da altri task — comando standalone.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Console;

use App\Models\Customer;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BackfillCustomerVatFromHeadingTest extends TestCase
{
    use DatabaseTransactions;

    /** @test */
    public function dry_run_non_scrive_vat_ma_salva_il_report(): void
    {
        Storage::fake('local');

        $customer = Customer::factory()->create([
            'heading' => "VIA DEI LIMONI N 23\n54100 MASSA (MS)\nPartita IVA 00660130451 C.F. 00660130451",
            'vat'     => null,
        ]);

        $this->artisan('customers:backfill-vat')->assertExitCode(0);

        $this->assertNull($customer->fresh()->vat);
        $this->assertNotEmpty(Storage::disk('local')->allFiles('customer-vat-backfill'));
    }

    /** @test */
    public function apply_scrive_la_partita_iva_estratta(): void
    {
        Storage::fake('local');

        $customer = Customer::factory()->create([
            'heading' => "Via Placido Rizzotto, 90\n41126 Modena (MO)\nP.iva / CF 03880320365",
            'vat'     => null,
        ]);

        $this->artisan('customers:backfill-vat', ['--apply' => true])->assertExitCode(0);

        $this->assertEquals('03880320365', $customer->fresh()->vat);
    }

    /** @test */
    public function non_sovrascrive_vat_gia_presente(): void
    {
        Storage::fake('local');

        $customer = Customer::factory()->create([
            'heading' => "P.IVA 01164510503",
            'vat'     => '99999999999',
        ]);

        $this->artisan('customers:backfill-vat', ['--apply' => true])->assertExitCode(0);

        $this->assertEquals('99999999999', $customer->fresh()->vat);
    }

    /** @test */
    public function non_estrae_da_codice_fiscale_alfanumerico_di_persona_fisica(): void
    {
        Storage::fake('local');

        $customer = Customer::factory()->create([
            'heading' => "Via San Costanzo 25, 80061 Massa Lubrense (NA)\nC.F. CCRSVT85H02I862Q",
            'vat'     => null,
        ]);

        $this->artisan('customers:backfill-vat', ['--apply' => true])->assertExitCode(0);

        $this->assertNull($customer->fresh()->vat);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker exec php81_orchestrator php artisan test --filter=BackfillCustomerVatFromHeadingTest`
Expected: FAIL — comando `customers:backfill-vat` non esiste

- [ ] **Step 3: Write minimal implementation**

Creare `app/Console/Commands/BackfillCustomerVatFromHeading.php`:

```php
<?php

namespace App\Console\Commands;

use App\Models\Customer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class BackfillCustomerVatFromHeading extends Command
{
    protected $signature = 'customers:backfill-vat
                            {--apply : Esegue realmente l\'update (default: dry-run, nessuna scrittura)}';

    protected $description = 'Estrae la Partita IVA dal campo heading (testo libero) dei customer con vat vuoto.
                              Dry-run di default: salva sempre un report su storage/app prima di ogni --apply,
                              nessun rollback automatico oltre a quel report.';

    public function handle(): int
    {
        $customers = Customer::whereNotNull('heading')
            ->where('heading', '!=', '')
            ->whereNull('vat')
            ->get(['id', 'name', 'heading']);

        $report = $customers->map(fn(Customer $customer) => [
            'id'            => $customer->id,
            'name'          => $customer->name,
            'vat_extracted' => $this->extractVat($customer->heading),
        ]);

        $matched = $report->filter(fn($row) => $row['vat_extracted'] !== null)->values();

        $this->table(
            ['ID', 'Name', 'VAT estratto'],
            $matched->map(fn($row) => [$row['id'], substr($row['name'], 0, 40), $row['vat_extracted']])
        );
        $this->info("Match trovati: {$matched->count()} / {$customers->count()} customer con heading e vat vuoto");

        $reportPath = 'customer-vat-backfill/' . now()->format('Y-m-d_His') . '.json';
        Storage::disk('local')->put($reportPath, json_encode($report->values()->all(), JSON_PRETTY_PRINT));
        $this->info("Report salvato in storage/app/{$reportPath}");

        if (!$this->option('apply')) {
            $this->comment('Dry-run: nessuna scrittura eseguita. Rilancia con --apply per applicare.');
            return self::SUCCESS;
        }

        foreach ($matched as $row) {
            Customer::whereKey($row['id'])->update(['vat' => $row['vat_extracted']]);
        }

        $this->info("Aggiornati {$matched->count()} customer.");

        return self::SUCCESS;
    }

    /**
     * Match "Partita Iva"/"P.IVA" first (11-digit VAT number). Fall back to
     * "C.F." only when it is exactly 11 numeric digits (a company VAT often
     * doubles as C.F.) — an alphanumeric 16-char C.F. is a private
     * individual's fiscal code, not a VAT number, and must NOT be used.
     */
    private function extractVat(string $heading): ?string
    {
        if (preg_match('/(?:Partita\s*Iva|P\.?\s*IVA|Numero\s*partita\s*IVA)[:\s\/]*([0-9]{11})/iu', $heading, $matches)) {
            return $matches[1];
        }

        if (preg_match('/C\.?F\.?[:\s]*([0-9]{11})\b/iu', $heading, $matches)) {
            return $matches[1];
        }

        return null;
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `docker exec php81_orchestrator php artisan test --filter=BackfillCustomerVatFromHeadingTest`
Expected: PASS (4/4)

- [ ] **Step 5: Commit**

```bash
git add app/Console/Commands/BackfillCustomerVatFromHeading.php tests/Feature/Console/BackfillCustomerVatFromHeadingTest.php
git commit -m "feat(oc:8291): add customers:backfill-vat dry-run command"
```

---

### Task 7: Test di regressione — PATCH parziale sui quotes (già implementato in oc:8286)

**Files:**
- Modify: `tests/Feature/Api/QuoteApiTest.php` (aggiunge un test, nessuna modifica al codice di produzione)

**Interfaces:**
- Nessuna — task di sola verifica, nessun'altra parte del piano dipende da questo.

- [ ] **Step 1: Write the test (già passa oggi — è una verifica di regressione, non TDD rosso→verde)**

Aggiungere a `tests/Feature/Api/QuoteApiTest.php`:

```php
    /** @test */
    public function update_solo_status_non_richiede_title_ne_customer_id(): void
    {
        $this->actingAsAdmin();
        $quote = Quote::factory()->create(['additional_services' => []]);

        $this->patchJson("/api/quotes/{$quote->id}", ['status' => \App\Enums\QuoteStatus::Presented->value])
            ->assertStatus(200)
            ->assertJsonPath('status', \App\Enums\QuoteStatus::Presented->value);

        $this->assertEquals(\App\Enums\QuoteStatus::Presented->value, $quote->fresh()->status);
    }
```

- [ ] **Step 2: Run test to verify it already passes**

Run: `docker exec php81_orchestrator php artisan test --filter=QuoteApiTest`
Expected: PASS (tutti i test esistenti + questo nuovo) — se questo test fallisse, la regola `sometimes` di `QuoteApiRequest` sarebbe stata rimossa/rotta da un altro cambiamento: fermarsi e investigare prima di proseguire, non è previsto codice di fix in questo task.

- [ ] **Step 3: Commit**

```bash
git add tests/Feature/Api/QuoteApiTest.php
git commit -m "test(oc:8291): lock in partial PATCH on quotes (status-only)"
```

---

### Task 8: `Api/QuoteController@index` — timestamps, sort, paginazione opt-in, filtro `status[]` multiplo

**Files:**
- Modify: `app/Http/Controllers/Api/QuoteController.php` (metodo `index()` e `formatQuote()`)
- Test: `tests/Feature/Api/QuoteApiTest.php`

**Interfaces:**
- Produces: `formatQuote(Quote $quote, array $include = []): array` — nuova firma con secondo parametro opzionale, consumata anche dal Task 9 (`show()`).

- [ ] **Step 1: Write the failing test**

Aggiungere a `tests/Feature/Api/QuoteApiTest.php`:

```php
    /** @test */
    public function index_espone_created_at_e_updated_at(): void
    {
        $this->actingAsAdmin();
        $quote = Quote::factory()->create(['additional_services' => []]);

        $response = $this->getJson('/api/quotes')->assertStatus(200);

        $item = collect($response->json())->firstWhere('id', $quote->id);
        $this->assertArrayHasKey('created_at', $item);
        $this->assertArrayHasKey('updated_at', $item);
        $this->assertNotNull($item['created_at']);
    }

    /** @test */
    public function index_ordina_per_created_at_decrescente(): void
    {
        $this->actingAsAdmin();
        // 'created_at' non è in $fillable su Quote: Eloquent lo scarterebbe
        // silenziosamente via create(), quindi si forza con forceFill()+save()
        // dopo la creazione per garantire due timestamp distinti e deterministici.
        $older = Quote::factory()->create(['additional_services' => []]);
        $older->forceFill(['created_at' => now()->subDays(2)])->save();
        $newer = Quote::factory()->create(['additional_services' => []]);
        $newer->forceFill(['created_at' => now()])->save();

        $response = $this->getJson('/api/quotes?sort=-created_at')->assertStatus(200);

        $ids = collect($response->json())->pluck('id')->filter(fn($id) => in_array($id, [$older->id, $newer->id]))->values();
        $this->assertEquals([$newer->id, $older->id], $ids->all());
    }

    /** @test */
    public function index_senza_parametri_di_paginazione_resta_un_array_semplice(): void
    {
        $this->actingAsAdmin();
        Quote::factory()->count(3)->create(['additional_services' => []]);

        $response = $this->getJson('/api/quotes')->assertStatus(200);

        $this->assertIsArray($response->json());
        $this->assertArrayNotHasKey('data', $response->json());
    }

    /** @test */
    public function index_con_per_page_restituisce_un_oggetto_paginato(): void
    {
        $this->actingAsAdmin();
        Quote::factory()->count(3)->create(['additional_services' => []]);

        $response = $this->getJson('/api/quotes?per_page=2&page=1')->assertStatus(200);

        $response->assertJsonStructure(['data', 'meta' => ['current_page', 'per_page', 'total', 'last_page']]);
        $this->assertCount(2, $response->json('data'));
    }

    /** @test */
    public function index_filtra_per_piu_status(): void
    {
        $this->actingAsAdmin();
        $new = Quote::factory()->create(['additional_services' => [], 'status' => \App\Enums\QuoteStatus::New->value]);
        $presented = Quote::factory()->create(['additional_services' => [], 'status' => \App\Enums\QuoteStatus::Presented->value]);
        $cold = Quote::factory()->create(['additional_services' => [], 'status' => \App\Enums\QuoteStatus::Cold->value]);

        $response = $this->getJson('/api/quotes?' . http_build_query(['status' => [
            \App\Enums\QuoteStatus::New->value,
            \App\Enums\QuoteStatus::Presented->value,
        ]]))->assertStatus(200);

        $ids = collect($response->json())->pluck('id')->all();
        $this->assertContains($new->id, $ids);
        $this->assertContains($presented->id, $ids);
        $this->assertNotContains($cold->id, $ids);
    }
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker exec php81_orchestrator php artisan test --filter=QuoteApiTest`
Expected: FAIL sui 5 nuovi test (mancano `created_at`/`updated_at` nella response, `sort` ignorato, `per_page` ignorato, `status[]` non gestito come array)

- [ ] **Step 3: Write minimal implementation**

Sostituire il metodo `index()` in `app/Http/Controllers/Api/QuoteController.php`:

```php
    /**
     * List quotes, optionally filtered by customer or status (single or
     * multiple), sorted by created_at, and optionally paginated.
     *
     * Pagination is opt-in: without `per_page`/`page` the response stays a
     * plain array (unchanged from before this feature) to avoid breaking
     * existing consumers.
     *
     * @response array<array{id: int, title: string, status: string, priority: int, customer_id: int, google_drive_url: string|null, discount: float|null, notes: string|null, additional_services: array|null, template: bool, total: float, net_total: float, iva: float, final_price: float, created_at: string|null, updated_at: string|null}>
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Quote::class);

        $query = Quote::query()->with(['products', 'recurringProducts']);

        if ($request->filled('customer_id')) {
            $query->where('customer_id', $request->integer('customer_id'));
        }

        if ($request->filled('status')) {
            $status = $request->input('status');
            if (is_array($status)) {
                $query->whereIn('status', $status);
            } else {
                $query->where('status', $status);
            }
        }

        if ($request->get('sort') === '-created_at') {
            $query->orderByDesc('created_at');
        } elseif ($request->get('sort') === 'created_at') {
            $query->orderBy('created_at');
        }

        if ($request->filled('per_page') || $request->filled('page')) {
            $paginated = $query->paginate($request->integer('per_page', 20));

            return response()->json([
                'data' => collect($paginated->items())->map(fn(Quote $q) => $this->formatQuote($q)),
                'meta' => [
                    'current_page' => $paginated->currentPage(),
                    'per_page'     => $paginated->perPage(),
                    'total'        => $paginated->total(),
                    'last_page'    => $paginated->lastPage(),
                ],
            ]);
        }

        $quotes = $query->get();

        return response()->json($quotes->map(fn(Quote $q) => $this->formatQuote($q)));
    }
```

Sostituire il metodo privato `formatQuote()` (verrà esteso ulteriormente nel Task 9, qui solo `created_at`/`updated_at`):

```php
    private function formatQuote(Quote $quote): array
    {
        return [
            'id'                   => $quote->id,
            'title'                => $quote->title,
            'status'               => $quote->status,
            'priority'             => $quote->priority,
            'customer_id'          => $quote->customer_id,
            'google_drive_url'     => $quote->google_drive_url,
            'discount'             => $quote->discount,
            'notes'                => $quote->notes,
            'additional_services'  => $quote->additional_services,
            'template'             => $quote->template,
            'total'                => $quote->getTotalPrice() + $quote->getTotalRecurringPrice() + $quote->getTotalAdditionalServicesPrice(),
            'net_total'            => $quote->getQuoteNetPrice(),
            'created_at'           => optional($quote->created_at)->toIso8601String(),
            'updated_at'           => optional($quote->updated_at)->toIso8601String(),
        ];
    }
```

- [ ] **Step 4: Run test to verify it passes**

Run: `docker exec php81_orchestrator php artisan test --filter=QuoteApiTest`
Expected: PASS (tutti, incluso `index_restituisce_lista_con_totali` già esistente — verificare che non si sia rotto)

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/Api/QuoteController.php tests/Feature/Api/QuoteApiTest.php
git commit -m "feat(oc:8291): add timestamps, sort, opt-in pagination and multi-status filter to quotes index"
```

---

### Task 9: `Api/QuoteController@show` — `include` relazioni + `iva`/`final_price`

**Files:**
- Modify: `app/Http/Controllers/Api/QuoteController.php` (metodo `show()` e `formatQuote()`)
- Test: `tests/Feature/Api/QuoteApiTest.php`

**Interfaces:**
- Consumes: `formatQuote(Quote $quote, array $include = [])` (Task 8, qui si aggiunge il parametro `$include` e i campi `iva`/`final_price`)
- Produces: nessuna nuova interfaccia consumata da altri task di questo piano.

- [ ] **Step 1: Write the failing test**

Aggiungere a `tests/Feature/Api/QuoteApiTest.php`:

```php
    /** @test */
    public function show_espone_iva_e_final_price(): void
    {
        $this->actingAsAdmin();
        $quote = Quote::factory()->create(['additional_services' => [], 'discount' => 0]);
        $product = Product::factory()->create(['price' => 100]);
        $quote->products()->attach($product->id, ['quantity' => 1]);

        $response = $this->getJson("/api/quotes/{$quote->id}")->assertStatus(200);

        $response->assertJson([
            'net_total'   => 100.0,
            'iva'         => 22.0,
            'final_price' => 122.0,
        ]);
    }

    /** @test */
    public function show_senza_include_non_espone_le_relazioni(): void
    {
        $this->actingAsAdmin();
        $quote = Quote::factory()->create(['additional_services' => []]);

        $response = $this->getJson("/api/quotes/{$quote->id}")->assertStatus(200);

        $response->assertJsonMissing(['customer' => []]);
        $this->assertArrayNotHasKey('customer', $response->json());
        $this->assertArrayNotHasKey('products', $response->json());
    }

    /** @test */
    public function show_con_include_espone_customer_e_products(): void
    {
        $this->actingAsAdmin();
        $customer = Customer::factory()->create(['name' => 'cliente_test']);
        $quote = Quote::factory()->create(['additional_services' => [], 'customer_id' => $customer->id]);
        $product = Product::factory()->create(['price' => 50]);
        $quote->products()->attach($product->id, ['quantity' => 2]);

        $response = $this->getJson("/api/quotes/{$quote->id}?include=customer,products")->assertStatus(200);

        $response->assertJsonPath('customer.id', $customer->id);
        $response->assertJsonPath('customer.name', 'cliente_test');
        $response->assertJsonPath('products.0.id', $product->id);
        $response->assertJsonPath('products.0.quantity', 2);
        $this->assertArrayNotHasKey('recurringProducts', $response->json());
    }
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker exec php81_orchestrator php artisan test --filter=QuoteApiTest`
Expected: FAIL sui 3 nuovi test (`iva`/`final_price` assenti, `include` ignorato)

- [ ] **Step 3: Write minimal implementation**

Sostituire il metodo `show()`:

```php
    private const ALLOWED_INCLUDES = ['customer', 'products', 'recurringProducts'];

    /**
     * Retrieve a quote, optionally expanding relations via `?include=`.
     *
     * @response array{id: int, title: string, status: string, priority: int, customer_id: int, google_drive_url: string|null, discount: float|null, notes: string|null, additional_services: array|null, template: bool, total: float, net_total: float, iva: float, final_price: float, created_at: string|null, updated_at: string|null}
     */
    public function show(Request $request, Quote $quote): JsonResponse
    {
        $this->authorize('view', $quote);

        $include = array_values(array_intersect(
            array_filter(explode(',', (string) $request->get('include', ''))),
            self::ALLOWED_INCLUDES
        ));

        $relationsToLoad = array_unique(array_merge(['products', 'recurringProducts'], $include));
        $quote->load($relationsToLoad);

        return response()->json($this->formatQuote($quote, $include));
    }
```

Sostituire `formatQuote()` (ora con `iva`/`final_price` sempre presenti e supporto `$include`):

```php
    private function formatQuote(Quote $quote, array $include = []): array
    {
        $netTotal = $quote->getQuoteNetPrice();
        $iva = $netTotal * 0.22;

        $data = [
            'id'                   => $quote->id,
            'title'                => $quote->title,
            'status'               => $quote->status,
            'priority'             => $quote->priority,
            'customer_id'          => $quote->customer_id,
            'google_drive_url'     => $quote->google_drive_url,
            'discount'             => $quote->discount,
            'notes'                => $quote->notes,
            'additional_services'  => $quote->additional_services,
            'template'             => $quote->template,
            'total'                => $quote->getTotalPrice() + $quote->getTotalRecurringPrice() + $quote->getTotalAdditionalServicesPrice(),
            'net_total'            => $netTotal,
            'iva'                  => $iva,
            'final_price'          => $netTotal + $iva,
            'created_at'           => optional($quote->created_at)->toIso8601String(),
            'updated_at'           => optional($quote->updated_at)->toIso8601String(),
        ];

        if (in_array('customer', $include, true) && $quote->relationLoaded('customer') && $quote->customer) {
            $data['customer'] = [
                'id'   => $quote->customer->id,
                'name' => $quote->customer->name,
            ];
        }

        if (in_array('products', $include, true)) {
            $data['products'] = $quote->products->map(fn($p) => [
                'id'       => $p->id,
                'name'     => $p->name,
                'price'    => $p->price,
                'quantity' => $p->pivot->quantity,
            ])->all();
        }

        if (in_array('recurringProducts', $include, true)) {
            $data['recurringProducts'] = $quote->recurringProducts->map(fn($p) => [
                'id'       => $p->id,
                'name'     => $p->name,
                'price'    => $p->price,
                'quantity' => $p->pivot->quantity,
            ])->all();
        }

        return $data;
    }
```

- [ ] **Step 4: Run test to verify it passes**

Run: `docker exec php81_orchestrator php artisan test --filter=QuoteApiTest`
Expected: PASS (tutti i test del file, inclusi quelli dei Task 7/8)

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/Api/QuoteController.php tests/Feature/Api/QuoteApiTest.php
git commit -m "feat(oc:8291): add include= relations and iva/final_price to quote responses"
```

---

### Task 10: Coerenza tipi nei docblock OpenAPI (Scramble)

**Files:**
- Modify: `app/Http/Controllers/Api/QuoteController.php` (docblock `@response` di `store()` e `update()` — `index()`/`show()` sono già stati corretti nei Task 8/9)

**Interfaces:**
- Nessuna — task di sola documentazione, nessun comportamento runtime cambia.

- [ ] **Step 1: Verifica dello stato attuale (non serve test automatico — verifica manuale sull'export OpenAPI)**

Run: `docker exec php81_orchestrator php artisan scramble:export --path=storage/app/api-before.json`
Run: `grep -A2 '"additional_services"' storage/app/api-before.json | head -20` — osservare che il tipo dichiarato per `additional_services`/`priority`/`template` non corrisponde a `array`/`integer`/`boolean`.

- [ ] **Step 2: Correggere i docblock**

In `app/Http/Controllers/Api/QuoteController.php`, aggiornare i tag `@response` di `store()` e `update()` (stesso schema già usato in `index()`/`show()` dopo il Task 8/9):

```php
    /**
     * Create a new quote.
     *
     * @response 201 array{id: int, title: string, status: string, priority: int, customer_id: int, google_drive_url: string|null, discount: float|null, notes: string|null, additional_services: array|null, template: bool, total: float, net_total: float, iva: float, final_price: float, created_at: string|null, updated_at: string|null}
     */
    public function store(QuoteApiRequest $request): JsonResponse
```

```php
    /**
     * Update an existing quote.
     *
     * @response array{id: int, title: string, status: string, priority: int, customer_id: int, google_drive_url: string|null, discount: float|null, notes: string|null, additional_services: array|null, template: bool, total: float, net_total: float, iva: float, final_price: float, created_at: string|null, updated_at: string|null}
     */
    public function update(QuoteApiRequest $request, Quote $quote): JsonResponse
```

Applicare lo stesso schema di tipo corretto anche ai docblock di `attachProduct()`, `detachProduct()`, `attachRecurringProduct()`, `detachRecurringProduct()` (attualmente dichiarano lo stesso schema errato di `store()`/`update()`).

- [ ] **Step 3: Verifica manuale**

Run: `docker exec php81_orchestrator php artisan scramble:export --path=storage/app/api-after.json`
Run: `grep -B2 -A2 '"additional_services"' storage/app/api-after.json | head -20` — confermare che il tipo sia ora `array` (non `string`), `priority` sia `integer`, `template` sia `boolean`.
Run: `rm storage/app/api-before.json storage/app/api-after.json` (file temporanei di verifica, non da committare)

- [ ] **Step 4: Commit**

```bash
git add app/Http/Controllers/Api/QuoteController.php
git commit -m "docs(oc:8291): fix Scramble response type mismatches on quote write endpoints"
```

---

### Task 11: Documentazione autenticazione per skill (docblock `AuthController`)

**Files:**
- Modify: `app/Http/Controllers/Api/AuthController.php:1-30`

**Interfaces:**
- Nessuna — task di sola documentazione.

- [ ] **Step 1: Aggiornare il docblock di `login()`**

In `app/Http/Controllers/Api/AuthController.php`, sostituire il docblock del metodo `login()`:

```php
    /**
     * Authenticate a user and issue a Sanctum bearer token.
     *
     * The token has no expiration and no scoped abilities (full access to
     * every `auth:sanctum` route) — intended for long-lived use by external
     * integrations (e.g. Claude Code skills) that need a Bearer token
     * without an interactive login each time. Store it securely; to revoke
     * a compromised token, delete it from the `personal_access_tokens`
     * table (e.g. `$user->tokens()->delete()` via tinker) — there is no
     * dedicated revocation endpoint.
     *
     * @response array{token: string, user: array{id: int, name: string, email: string}}
     * @response 401 array{message: string}
     */
    public function login(Request $request): JsonResponse
```

- [ ] **Step 2: Verifica manuale**

Run: `docker exec php81_orchestrator php artisan scramble:export --path=storage/app/api-auth-check.json`
Run: `grep -A5 '"login"' storage/app/api-auth-check.json | head -10` — confermare che la descrizione aggiornata compaia nell'export
Run: `rm storage/app/api-auth-check.json`

- [ ] **Step 3: Commit**

```bash
git add app/Http/Controllers/Api/AuthController.php
git commit -m "docs(oc:8291): document long-lived full-access PAT for skill usage"
```

---

## Self-Review

**Copertura spec (overview.md):**

| Requisito overview | Task |
|---|---|
| 1. PDF via API + link pubblico + throttle + filename sanitizzato + expires_in_days 1-90 | Task 1, 2, 3 |
| 2. Endpoint Customers (migration, contact_emails, company_name alias, ruoli, backfill vat) | Task 4, 5, 6 |
| 3. Verifica PATCH parziale (già implementato) | Task 7 |
| 4. Timestamps, sort, paginazione opt-in, filtro status[] | Task 8 |
| 5. Dettaglio con include= + iva/final_price | Task 9 |
| 6. Coerenza tipi Scramble | Task 10 (+ Task 8/9 per index/show) |
| 7. Documentazione auth | Task 11 |

**Rischio "rotta web senza autorizzazione" (fuori scope):** non richiede task di codice in questo piano — va registrato in `notes.md` durante `Fase: notes`, non qui.

**Placeholder scan:** nessun "TBD"/"handle edge cases" generico — ogni step ha codice completo o comando eseguibile esplicito.

**Coerenza tipi tra task:** `formatQuote(Quote $quote, array $include = [])` introdotto nel Task 8 (senza `$include`, usato solo da `index()`) e esteso nel Task 9 con la firma finale usata da `show()` — stesso nome di metodo in entrambi, nessuna divergenza. `QuotePdfService::stream()`/`fileName()` (Task 1) sono la sola interfaccia consumata da Task 2 e 3, stessa firma in entrambi i punti di consumo.
