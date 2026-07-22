> Ticket: oc:8286

# API CRUD per Quote Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Esporre un set di endpoint REST CRUD per il modello `Quote` (più liste read-only per `Product`/`RecurringProduct` e attach/detach pivot), riusando `QuotePolicy` come unica fonte di autorizzazione dopo averla corretta.

**Architecture:** Segue il pattern già validato da `TagController`/`TagApiRequest` (oc:8155): controller sottile, `FormRequest` dedicato per la validazione, autorizzazione via Policy (`$this->authorize()`) invece di `abort_unless` duplicato. Il fix a `QuotePolicy::before()` è un prerequisito: oggi ritorna sempre un booleano netto e short-circuita sempre, impedendo a `update()`/`delete()` di essere mai valutati.

**Tech Stack:** Laravel 10, PostgreSQL, Sanctum, PHPUnit (`DatabaseTransactions`, DB di test `orchestrator_test`).

## Global Constraints

- Commit convention: `feat(oc:8286): ...` / `fix(oc:8286): ...` — nessun commit automatico, sono istruzioni testuali per l'utente.
- Nessun `git commit`/`git push` eseguito autonomamente durante l'esecuzione del piano.
- Ruoli abilitati a tutte le rotte: `Admin`, `Manager`, `Developer` (enum `App\Enums\UserRole`).
- Campi translatable (`additional_services`, `notes`) scritti via API solo su `config('app.locale')` (= `it`), mai sulle altre lingue.
- Nessun soft delete su `Quote`: il `DELETE` è fisico, va loggato.
- Test eseguiti con `docker exec php81_orchestrator php artisan test --filter=...` (DB `orchestrator_test`, mai `orchestrator`).

---

### Task 1: Fix `QuotePolicy::before()` e implementazione `delete()`

**Files:**
- Modify: `app/Policies/QuotePolicy.php:14-17` (metodo `before`)
- Modify: `app/Policies/QuotePolicy.php:60-62` (metodo `delete`)
- Test: `tests/Feature/Api/QuotePolicyTest.php` (nuovo)

**Interfaces:**
- Consumes: `App\Models\Quote`, `App\Models\User`, `App\Enums\UserRole`, `App\Enums\QuoteStatus` (già importati nel file)
- Produces: `Gate::allows('update', $quote)` e `Gate::allows('delete', $quote)` valutano realmente lo stato del quote per i ruoli Admin/Manager/Developer — usati dal `QuoteController` nei Task 3 e 5.

- [ ] **Step 1: Scrivi il test che verifica il bug attuale (prima del fix) — salta questo step se il fix è già applicato, serve solo a documentare il comportamento pre-fix**

Non serve un test "rosso" per il bug: il bug è nella policy stessa, non testabile in isolamento senza il fix. Passa direttamente allo Step 2 scrivendo i test sul comportamento *desiderato*.

- [ ] **Step 2: Scrivi i test sul comportamento desiderato di `before()`/`update()`/`delete()`**

```php
<?php

namespace Tests\Feature\Api;

use App\Enums\QuoteStatus;
use App\Enums\UserRole;
use App\Models\Quote;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class QuotePolicyTest extends TestCase
{
    use DatabaseTransactions;

    /** @test */
    public function admin_non_puo_aggiornare_un_quote_chiuso_vinto(): void
    {
        $admin = User::factory()->create(['roles' => [UserRole::Admin]]);
        $quote = Quote::factory()->create(['status' => QuoteStatus::Closed_Won->value]);

        $this->assertFalse($admin->can('update', $quote));
    }

    /** @test */
    public function admin_non_puo_aggiornare_un_quote_chiuso_perso(): void
    {
        $admin = User::factory()->create(['roles' => [UserRole::Admin]]);
        $quote = Quote::factory()->create(['status' => QuoteStatus::Closed_Lost->value]);

        $this->assertFalse($admin->can('update', $quote));
    }

    /** @test */
    public function admin_puo_aggiornare_un_quote_aperto(): void
    {
        $admin = User::factory()->create(['roles' => [UserRole::Admin]]);
        $quote = Quote::factory()->create(['status' => QuoteStatus::New->value]);

        $this->assertTrue($admin->can('update', $quote));
    }

    /** @test */
    public function admin_non_puo_eliminare_un_quote_chiuso_vinto(): void
    {
        $admin = User::factory()->create(['roles' => [UserRole::Admin]]);
        $quote = Quote::factory()->create(['status' => QuoteStatus::Closed_Won->value]);

        $this->assertFalse($admin->can('delete', $quote));
    }

    /** @test */
    public function admin_puo_eliminare_un_quote_aperto(): void
    {
        $admin = User::factory()->create(['roles' => [UserRole::Admin]]);
        $quote = Quote::factory()->create(['status' => QuoteStatus::New->value]);

        $this->assertTrue($admin->can('delete', $quote));
    }

    /** @test */
    public function customer_non_puo_aggiornare_nessun_quote(): void
    {
        $customer = User::factory()->create(['roles' => [UserRole::Customer]]);
        $quote    = Quote::factory()->create(['status' => QuoteStatus::New->value]);

        $this->assertFalse($customer->can('update', $quote));
    }
}
```

- [ ] **Step 3: Esegui i test, verifica che falliscano**

```bash
docker exec php81_orchestrator php artisan test --filter=QuotePolicyTest
```

Expected: FAIL su `admin_non_puo_aggiornare_un_quote_chiuso_vinto`, `admin_non_puo_aggiornare_un_quote_chiuso_perso`, `admin_non_puo_eliminare_un_quote_chiuso_vinto` (perché `before()` oggi ritorna sempre `true` per Admin, quindi `update()`/`delete()` non vengono mai valutati). Le altre 2 passano già.

- [ ] **Step 4: Applica il fix a `QuotePolicy::before()` e implementa `delete()`**

In `app/Policies/QuotePolicy.php`, sostituisci:

```php
    public function before(User $user)
    {
        return $user->hasRole(UserRole::Admin) || $user->hasRole(UserRole::Manager) || $user->hasRole(UserRole::Developer);
    }
```

con:

```php
    public function before(User $user)
    {
        if (!($user->hasRole(UserRole::Admin) || $user->hasRole(UserRole::Manager) || $user->hasRole(UserRole::Developer))) {
            return false;
        }
    }
```

E sostituisci:

```php
    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Quote $quote)
    {
    }
```

con:

```php
    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Quote $quote)
    {
        return $quote->status != QuoteStatus::Closed_Won->value &&
            $quote->status != QuoteStatus::Closed_Lost->value;
    }
```

- [ ] **Step 5: Esegui i test, verifica che passino**

```bash
docker exec php81_orchestrator php artisan test --filter=QuotePolicyTest
```

Expected: PASS su tutti e 6 i test.

- [ ] **Step 6: Verifica di non-regressione su Nova**

```bash
docker exec php81_orchestrator php artisan test --filter=Quote
```

Expected: nessun test esistente rotto (nessun test Nova diretto su `QuotePolicy` risultava presente prima di questo piano, ma la ricerca `--filter=Quote` intercetta eventuali test collaterali su totali/prezzi).

- [ ] **Step 7: Commit**

```bash
git add app/Policies/QuotePolicy.php tests/Feature/Api/QuotePolicyTest.php
git commit -m "fix(oc:8286): correggi QuotePolicy::before per valutare update/delete su stato chiuso"
```

---

### Task 2: `QuoteApiRequest` — validazione create/update

**Files:**
- Create: `app/Http/Requests/Api/QuoteApiRequest.php`
- Test: incluso in `tests/Feature/Api/QuoteApiTest.php` (Task 3)

**Interfaces:**
- Consumes: nessuna dipendenza da altri task
- Produces: `QuoteApiRequest::rules()` — usato da `QuoteController::store`/`update` nel Task 3. Campi validati: `title`, `name`, `status`, `priority`, `additional_services`, `customer_id`, `google_drive_url`, `discount`, `notes`, `template`.

- [ ] **Step 1: Crea il Form Request**

```php
<?php

namespace App\Http\Requests\Api;

use App\Enums\QuoteStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class QuoteApiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $isCreate = $this->isMethod('POST');

        return [
            'title'                => ['sometimes', 'nullable', 'string', 'max:255'],
            'name'                 => [$isCreate ? 'required' : 'sometimes', 'string', 'max:255'],
            'status'               => ['sometimes', Rule::in(array_column(QuoteStatus::cases(), 'value'))],
            'priority'             => ['sometimes', 'nullable', 'integer'],
            'additional_services'  => ['sometimes', 'nullable', 'array'],
            'customer_id'          => [$isCreate ? 'required' : 'sometimes', 'integer', 'exists:customers,id'],
            'google_drive_url'     => ['sometimes', 'nullable', 'string', 'max:2048'],
            'discount'             => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'notes'                => ['sometimes', 'nullable', 'string'],
            'template'             => ['sometimes', 'boolean'],
        ];
    }
}
```

- [ ] **Step 2: Nessun test isolato per questo file** — la validazione viene esercitata dai test HTTP del `QuoteController` nel Task 3 (`store_richiede_name_e_customer_id`, `store_valida_customer_id_esistente`, ecc.). Procedi al Task 3.

- [ ] **Step 3: Commit (incluso nel commit del Task 3, il Form Request da solo non è testabile in isolamento senza un controller che lo usi)**

Nessun commit separato — vedi Task 3 Step finale.

---

### Task 3: `QuoteController` — CRUD base (index/show/store/update/destroy)

**Files:**
- Create: `app/Http/Controllers/Api/QuoteController.php`
- Modify: `routes/api.php`
- Test: `tests/Feature/Api/QuoteApiTest.php` (nuovo)

**Interfaces:**
- Consumes: `App\Http\Requests\Api\QuoteApiRequest` (Task 2), `QuotePolicy::update`/`delete` corretti (Task 1)
- Produces: `QuoteController::index`, `show`, `store`, `update`, `destroy` — usati dalle route in `routes/api.php`. Formato risposta JSON di un quote (metodo privato `formatQuote`): `id`, `title`, `name`, `status`, `priority`, `customer_id`, `google_drive_url`, `discount`, `notes`, `additional_services`, `template`, `total` (float), `net_total` (float) — usato anche dal Task 4 (attach/detach) per la risposta.

- [ ] **Step 1: Scrivi i test HTTP per index/show/store/update/destroy**

```php
<?php

namespace Tests\Feature\Api;

use App\Enums\QuoteStatus;
use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Quote;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class QuoteApiTest extends TestCase
{
    use DatabaseTransactions;

    private function actingAsAdmin(): User
    {
        $user = User::factory()->create(['roles' => [UserRole::Admin]]);
        Sanctum::actingAs($user);
        return $user;
    }

    private function actingAsCustomer(): User
    {
        $user = User::factory()->create(['roles' => [UserRole::Customer]]);
        Sanctum::actingAs($user);
        return $user;
    }

    /** @test */
    public function utente_non_autenticato_ottiene_401(): void
    {
        $this->getJson('/api/quotes')->assertStatus(401);
    }

    /** @test */
    public function customer_non_puo_accedere_alle_api_quote(): void
    {
        $this->actingAsCustomer();

        $this->getJson('/api/quotes')->assertStatus(403);
    }

    /** @test */
    public function index_restituisce_lista_con_totali(): void
    {
        $this->actingAsAdmin();
        $quote = Quote::factory()->create();
        $product = Product::factory()->create(['price' => 100]);
        $quote->products()->attach($product->id, ['quantity' => 2]);

        $response = $this->getJson('/api/quotes')->assertStatus(200);

        $response->assertJsonStructure([['id', 'name', 'status', 'total', 'net_total']]);
        $item = collect($response->json())->firstWhere('id', $quote->id);
        $this->assertEquals(200.0, $item['total']);
    }

    /** @test */
    public function index_filtra_per_customer_id(): void
    {
        $this->actingAsAdmin();
        $customerA = Customer::factory()->create();
        $customerB = Customer::factory()->create();
        Quote::factory()->create(['customer_id' => $customerA->id]);
        Quote::factory()->create(['customer_id' => $customerB->id]);

        $response = $this->getJson("/api/quotes?customer_id={$customerA->id}")->assertStatus(200);

        $customerIds = collect($response->json())->pluck('customer_id')->unique();
        $this->assertEquals([$customerA->id], $customerIds->values()->toArray());
    }

    /** @test */
    public function index_filtra_per_status(): void
    {
        $this->actingAsAdmin();
        Quote::factory()->create(['status' => QuoteStatus::New->value]);
        Quote::factory()->create(['status' => QuoteStatus::Closed_Won->value]);

        $response = $this->getJson('/api/quotes?status=' . QuoteStatus::Closed_Won->value)->assertStatus(200);

        $statuses = collect($response->json())->pluck('status')->unique();
        $this->assertEquals([QuoteStatus::Closed_Won->value], $statuses->values()->toArray());
    }

    /** @test */
    public function show_restituisce_404_per_quote_inesistente(): void
    {
        $this->actingAsAdmin();

        $this->getJson('/api/quotes/999999')->assertStatus(404);
    }

    /** @test */
    public function store_richiede_name_e_customer_id(): void
    {
        $this->actingAsAdmin();

        $this->postJson('/api/quotes', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'customer_id']);
    }

    /** @test */
    public function store_valida_customer_id_esistente(): void
    {
        $this->actingAsAdmin();

        $this->postJson('/api/quotes', ['name' => 'Test', 'customer_id' => 999999])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['customer_id']);
    }

    /** @test */
    public function store_crea_quote(): void
    {
        $this->actingAsAdmin();
        $customer = Customer::factory()->create();

        $response = $this->postJson('/api/quotes', [
            'name'        => 'Nuovo preventivo',
            'customer_id' => $customer->id,
        ])->assertStatus(201);

        $this->assertDatabaseHas('quotes', ['name' => 'Nuovo preventivo', 'customer_id' => $customer->id]);
        $response->assertJsonStructure(['id', 'name', 'total', 'net_total']);
    }

    /** @test */
    public function update_blocca_quote_chiuso_vinto(): void
    {
        $this->actingAsAdmin();
        $quote = Quote::factory()->create(['status' => QuoteStatus::Closed_Won->value]);

        $this->patchJson("/api/quotes/{$quote->id}", ['name' => 'Tentativo update'])
            ->assertStatus(403);
    }

    /** @test */
    public function update_aggiorna_quote_aperto(): void
    {
        $this->actingAsAdmin();
        $quote = Quote::factory()->create(['status' => QuoteStatus::New->value]);

        $this->patchJson("/api/quotes/{$quote->id}", ['name' => 'Nome aggiornato'])
            ->assertStatus(200);

        $this->assertDatabaseHas('quotes', ['id' => $quote->id, 'name' => 'Nome aggiornato']);
    }

    /** @test */
    public function destroy_blocca_quote_chiuso_perso(): void
    {
        $this->actingAsAdmin();
        $quote = Quote::factory()->create(['status' => QuoteStatus::Closed_Lost->value]);

        $this->deleteJson("/api/quotes/{$quote->id}")->assertStatus(403);
        $this->assertDatabaseHas('quotes', ['id' => $quote->id]);
    }

    /** @test */
    public function destroy_elimina_quote_aperto(): void
    {
        $this->actingAsAdmin();
        $quote = Quote::factory()->create(['status' => QuoteStatus::New->value]);

        $this->deleteJson("/api/quotes/{$quote->id}")->assertStatus(200);
        $this->assertDatabaseMissing('quotes', ['id' => $quote->id]);
    }
}
```

- [ ] **Step 2: Esegui i test, verifica che falliscano**

```bash
docker exec php81_orchestrator php artisan test --filter=QuoteApiTest
```

Expected: FAIL — route `/api/quotes` non esiste (404 invece degli status attesi).

- [ ] **Step 3: Crea `QuoteController`**

`additional_services` e `notes` sono campi `$translatable` (via `Spatie\Translatable\HasTranslations`), quindi vanno scritti con `setTranslation($field, $locale, $value)` per toccare solo la lingua di default — non tramite `fill()`, che scriverebbe sull'attributo raw. Per questo motivo vengono estratti dal payload validato prima di chiamare `fill()`.

```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\QuoteApiRequest;
use App\Models\Quote;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class QuoteController extends Controller
{
    private const TRANSLATABLE_FIELDS = ['additional_services', 'notes'];

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Quote::class);

        $query = Quote::query()->with(['products', 'recurringProducts']);

        if ($request->filled('customer_id')) {
            $query->where('customer_id', $request->integer('customer_id'));
        }
        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        $quotes = $query->get();

        return response()->json($quotes->map(fn(Quote $q) => $this->formatQuote($q)));
    }

    public function show(Request $request, Quote $quote): JsonResponse
    {
        $this->authorize('view', $quote);

        $quote->load(['products', 'recurringProducts']);

        return response()->json($this->formatQuote($quote));
    }

    public function store(QuoteApiRequest $request): JsonResponse
    {
        $this->authorize('create', Quote::class);

        $validated = $request->validated();
        $translatable = $this->extractTranslatable($validated);

        $quote = new Quote();
        $quote->fill($validated);
        $this->applyTranslatable($quote, $translatable);
        $quote->save();

        return response()->json($this->formatQuote($quote), 201);
    }

    public function update(QuoteApiRequest $request, Quote $quote): JsonResponse
    {
        $this->authorize('update', $quote);

        $validated = $request->validated();
        $translatable = $this->extractTranslatable($validated);

        $quote->fill($validated);
        $this->applyTranslatable($quote, $translatable);
        $quote->save();

        return response()->json($this->formatQuote($quote));
    }

    public function destroy(Request $request, Quote $quote): JsonResponse
    {
        $this->authorize('delete', $quote);

        Log::info('Quote deleted via API', [
            'user_id'  => $request->user()->id,
            'quote_id' => $quote->id,
        ]);

        $quote->delete();

        return response()->json(['message' => 'Quote deleted.']);
    }

    private function extractTranslatable(array &$validated): array
    {
        $translatable = [];
        foreach (self::TRANSLATABLE_FIELDS as $field) {
            if (array_key_exists($field, $validated)) {
                $translatable[$field] = $validated[$field];
                unset($validated[$field]);
            }
        }
        return $translatable;
    }

    private function applyTranslatable(Quote $quote, array $translatable): void
    {
        foreach ($translatable as $field => $value) {
            $quote->setTranslation($field, config('app.locale'), $value);
        }
    }

    private function formatQuote(Quote $quote): array
    {
        return [
            'id'                   => $quote->id,
            'title'                => $quote->title,
            'name'                 => $quote->name,
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
        ];
    }
}
```

- [ ] **Step 4: Aggiungi le rotte in `routes/api.php`**

Aggiungi l'import in cima al file (accanto agli altri `use App\Http\Controllers\Api\...`):

```php
use App\Http\Controllers\Api\QuoteController;
```

Aggiungi dentro il gruppo `Route::middleware('auth:sanctum')->group(function () { ... })`, dopo le rotte `tags`:

```php
    Route::get('/quotes', [QuoteController::class, 'index']);
    Route::get('/quotes/{quote}', [QuoteController::class, 'show']);
    Route::post('/quotes', [QuoteController::class, 'store']);
    Route::patch('/quotes/{quote}', [QuoteController::class, 'update']);
    Route::delete('/quotes/{quote}', [QuoteController::class, 'destroy']);
```

- [ ] **Step 5: Esegui i test, verifica che passino**

```bash
docker exec php81_orchestrator php artisan test --filter=QuoteApiTest
```

Expected: PASS su tutti i test elencati allo Step 1.

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/Api/QuoteController.php app/Http/Requests/Api/QuoteApiRequest.php routes/api.php tests/Feature/Api/QuoteApiTest.php
git commit -m "feat(oc:8286): aggiungi CRUD API per Quote"
```

---

### Task 4: Attach/detach `products` e `recurringProducts` con quantity

**Files:**
- Modify: `app/Http/Controllers/Api/QuoteController.php` (aggiungi metodi)
- Modify: `routes/api.php`
- Test: modifica `tests/Feature/Api/QuoteApiTest.php`

**Interfaces:**
- Consumes: `QuoteController::formatQuote` (Task 3, riusato per la risposta)
- Produces: `QuoteController::attachProduct`, `detachProduct`, `attachRecurringProduct`, `detachRecurringProduct` — usati dalle route aggiunte in questo task.

- [ ] **Step 1: Aggiungi i test per attach/detach**

Aggiungi questi metodi in fondo a `tests/Feature/Api/QuoteApiTest.php` (prima dell'ultima `}` della classe), aggiungendo `use App\Models\RecurringProduct;` in cima al file:

```php
    /** @test */
    public function attach_product_richiede_quantity(): void
    {
        $this->actingAsAdmin();
        $quote   = Quote::factory()->create();
        $product = Product::factory()->create();

        $this->postJson("/api/quotes/{$quote->id}/products/{$product->id}", [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['quantity']);
    }

    /** @test */
    public function attach_product_collega_con_quantity(): void
    {
        $this->actingAsAdmin();
        $quote   = Quote::factory()->create();
        $product = Product::factory()->create();

        $this->postJson("/api/quotes/{$quote->id}/products/{$product->id}", ['quantity' => 3])
            ->assertStatus(200);

        $this->assertDatabaseHas('product_quote', [
            'quote_id'   => $quote->id,
            'product_id' => $product->id,
            'quantity'   => 3,
        ]);
    }

    /** @test */
    public function attach_product_e_upsert_su_seconda_chiamata(): void
    {
        $this->actingAsAdmin();
        $quote   = Quote::factory()->create();
        $product = Product::factory()->create();
        $quote->products()->attach($product->id, ['quantity' => 2]);

        $this->postJson("/api/quotes/{$quote->id}/products/{$product->id}", ['quantity' => 5])
            ->assertStatus(200);

        $this->assertEquals(1, $quote->products()->where('product_id', $product->id)->count());
        $this->assertDatabaseHas('product_quote', [
            'quote_id'   => $quote->id,
            'product_id' => $product->id,
            'quantity'   => 5,
        ]);
    }

    /** @test */
    public function detach_product_scollega(): void
    {
        $this->actingAsAdmin();
        $quote   = Quote::factory()->create();
        $product = Product::factory()->create();
        $quote->products()->attach($product->id, ['quantity' => 1]);

        $this->deleteJson("/api/quotes/{$quote->id}/products/{$product->id}")->assertStatus(200);

        $this->assertDatabaseMissing('product_quote', ['quote_id' => $quote->id, 'product_id' => $product->id]);
    }

    /** @test */
    public function detach_product_inesistente_restituisce_404(): void
    {
        $this->actingAsAdmin();
        $quote   = Quote::factory()->create();
        $product = Product::factory()->create();

        $this->deleteJson("/api/quotes/{$quote->id}/products/{$product->id}")->assertStatus(404);
    }

    /** @test */
    public function attach_recurring_product_collega_con_quantity(): void
    {
        $this->actingAsAdmin();
        $quote = Quote::factory()->create();
        $recurringProduct = RecurringProduct::factory()->create();

        $this->postJson("/api/quotes/{$quote->id}/recurring-products/{$recurringProduct->id}", ['quantity' => 2])
            ->assertStatus(200);

        $this->assertDatabaseHas('quote_recurring_product', [
            'quote_id'             => $quote->id,
            'recurring_product_id' => $recurringProduct->id,
            'quantity'             => 2,
        ]);
    }

    /** @test */
    public function detach_recurring_product_inesistente_restituisce_404(): void
    {
        $this->actingAsAdmin();
        $quote = Quote::factory()->create();
        $recurringProduct = RecurringProduct::factory()->create();

        $this->deleteJson("/api/quotes/{$quote->id}/recurring-products/{$recurringProduct->id}")->assertStatus(404);
    }
```

Verifica il nome esatto della tabella pivot recurring products prima di eseguire (vedi Step 2).

- [ ] **Step 2: Verifica i nomi delle tabelle pivot**

```bash
docker exec php81_orchestrator php artisan tinker --execute="echo (new App\Models\Quote())->recurringProducts()->getTable();"
```

Se il nome restituito differisce da `quote_recurring_product`, aggiorna il test dello Step 1 con il nome corretto (la migration `2023_03_08_152528_create_quote_recurring_product_table.php` suggerisce `quote_recurring_product`, confermalo con questo comando prima di procedere).

- [ ] **Step 3: Esegui i test, verifica che falliscano**

```bash
docker exec php81_orchestrator php artisan test --filter=QuoteApiTest
```

Expected: FAIL sui nuovi test attach/detach (rotte inesistenti).

- [ ] **Step 4: Aggiungi i metodi al `QuoteController`**

```php
    public function attachProduct(Request $request, Quote $quote, \App\Models\Product $product): JsonResponse
    {
        $this->authorize('update', $quote);

        $validated = $request->validate(['quantity' => ['required', 'integer', 'min:1']]);

        $quote->products()->syncWithoutDetaching([$product->id => ['quantity' => $validated['quantity']]]);

        return response()->json($this->formatQuote($quote->fresh(['products', 'recurringProducts'])));
    }

    public function detachProduct(Request $request, Quote $quote, \App\Models\Product $product): JsonResponse
    {
        $this->authorize('update', $quote);

        abort_unless($quote->products()->where('product_id', $product->id)->exists(), 404);

        $quote->products()->detach($product->id);

        return response()->json($this->formatQuote($quote->fresh(['products', 'recurringProducts'])));
    }

    public function attachRecurringProduct(Request $request, Quote $quote, \App\Models\RecurringProduct $recurringProduct): JsonResponse
    {
        $this->authorize('update', $quote);

        $validated = $request->validate(['quantity' => ['required', 'integer', 'min:1']]);

        $quote->recurringProducts()->syncWithoutDetaching([$recurringProduct->id => ['quantity' => $validated['quantity']]]);

        return response()->json($this->formatQuote($quote->fresh(['products', 'recurringProducts'])));
    }

    public function detachRecurringProduct(Request $request, Quote $quote, \App\Models\RecurringProduct $recurringProduct): JsonResponse
    {
        $this->authorize('update', $quote);

        abort_unless($quote->recurringProducts()->where('recurring_product_id', $recurringProduct->id)->exists(), 404);

        $quote->recurringProducts()->detach($recurringProduct->id);

        return response()->json($this->formatQuote($quote->fresh(['products', 'recurringProducts'])));
    }
```

Nota: `syncWithoutDetaching` con un array `[$id => ['quantity' => N]]` fa upsert (aggiorna la riga pivot se esiste, la crea se non esiste) senza toccare le altre righe pivot — comportamento coerente con "upsert" deciso nell'overview.

- [ ] **Step 5: Aggiungi le rotte**

In `routes/api.php`, dopo le rotte `quotes` del Task 3:

```php
    Route::post('/quotes/{quote}/products/{product}', [QuoteController::class, 'attachProduct']);
    Route::delete('/quotes/{quote}/products/{product}', [QuoteController::class, 'detachProduct']);
    Route::post('/quotes/{quote}/recurring-products/{recurringProduct}', [QuoteController::class, 'attachRecurringProduct']);
    Route::delete('/quotes/{quote}/recurring-products/{recurringProduct}', [QuoteController::class, 'detachRecurringProduct']);
```

- [ ] **Step 6: Esegui i test, verifica che passino**

```bash
docker exec php81_orchestrator php artisan test --filter=QuoteApiTest
```

Expected: PASS su tutti i test, inclusi quelli attach/detach.

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/Api/QuoteController.php routes/api.php tests/Feature/Api/QuoteApiTest.php
git commit -m "feat(oc:8286): aggiungi attach/detach products e recurring products su Quote"
```

---

### Task 5: `GET /api/products` e `GET /api/recurring-products` (sola lettura)

**Files:**
- Create: `app/Http/Controllers/Api/ProductController.php`
- Create: `app/Http/Controllers/Api/RecurringProductController.php`
- Modify: `routes/api.php`
- Test: `tests/Feature/Api/ProductApiTest.php` (nuovo)

**Interfaces:**
- Consumes: nessuna dipendenza da altri task (può essere eseguito in parallelo a Task 1-4)
- Produces: `ProductController::index`, `RecurringProductController::index` — usati dalle route aggiunte in questo task. Nessun altro task dipende da questi controller.

- [ ] **Step 1: Scrivi i test**

```php
<?php

namespace Tests\Feature\Api;

use App\Enums\UserRole;
use App\Models\Product;
use App\Models\RecurringProduct;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProductApiTest extends TestCase
{
    use DatabaseTransactions;

    private function actingAsAdmin(): User
    {
        $user = User::factory()->create(['roles' => [UserRole::Admin]]);
        Sanctum::actingAs($user);
        return $user;
    }

    private function actingAsCustomer(): User
    {
        $user = User::factory()->create(['roles' => [UserRole::Customer]]);
        Sanctum::actingAs($user);
        return $user;
    }

    /** @test */
    public function utente_non_autenticato_ottiene_401_su_products(): void
    {
        $this->getJson('/api/products')->assertStatus(401);
    }

    /** @test */
    public function customer_non_puo_accedere_a_products(): void
    {
        $this->actingAsCustomer();

        $this->getJson('/api/products')->assertStatus(403);
    }

    /** @test */
    public function index_restituisce_lista_products(): void
    {
        $this->actingAsAdmin();
        Product::factory()->count(2)->create();

        $response = $this->getJson('/api/products')->assertStatus(200);

        $response->assertJsonStructure([['id', 'name', 'price']]);
        $this->assertGreaterThanOrEqual(2, count($response->json()));
    }

    /** @test */
    public function index_restituisce_lista_recurring_products(): void
    {
        $this->actingAsAdmin();
        RecurringProduct::factory()->count(2)->create();

        $response = $this->getJson('/api/recurring-products')->assertStatus(200);

        $response->assertJsonStructure([['id', 'name', 'price']]);
        $this->assertGreaterThanOrEqual(2, count($response->json()));
    }
}
```

- [ ] **Step 2: Esegui i test, verifica che falliscano**

```bash
docker exec php81_orchestrator php artisan test --filter=ProductApiTest
```

Expected: FAIL — route inesistenti (404).

- [ ] **Step 3: Crea `ProductController`**

```php
<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorizeRole($request);

        $products = Product::all();

        return response()->json($products->map(fn(Product $p) => [
            'id'          => $p->id,
            'name'        => $p->name,
            'description' => $p->description,
            'sku'         => $p->sku,
            'price'       => $p->price,
        ]));
    }

    private function authorizeRole(Request $request): void
    {
        $user = $request->user();
        abort_unless(
            $user->hasRole(UserRole::Admin) || $user->hasRole(UserRole::Manager) || $user->hasRole(UserRole::Developer),
            403
        );
    }
}
```

- [ ] **Step 4: Crea `RecurringProductController`**

```php
<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\RecurringProduct;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RecurringProductController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorizeRole($request);

        $recurringProducts = RecurringProduct::all();

        return response()->json($recurringProducts->map(fn(RecurringProduct $p) => [
            'id'          => $p->id,
            'name'        => $p->name,
            'description' => $p->description,
            'sku'         => $p->sku,
            'price'       => $p->price,
        ]));
    }

    private function authorizeRole(Request $request): void
    {
        $user = $request->user();
        abort_unless(
            $user->hasRole(UserRole::Admin) || $user->hasRole(UserRole::Manager) || $user->hasRole(UserRole::Developer),
            403
        );
    }
}
```

- [ ] **Step 5: Aggiungi le rotte**

In `routes/api.php`, aggiungi gli import:

```php
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\RecurringProductController;
```

E dentro il gruppo `auth:sanctum`:

```php
    Route::get('/products', [ProductController::class, 'index']);
    Route::get('/recurring-products', [RecurringProductController::class, 'index']);
```

- [ ] **Step 6: Esegui i test, verifica che passino**

```bash
docker exec php81_orchestrator php artisan test --filter=ProductApiTest
```

Expected: PASS su tutti i test.

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/Api/ProductController.php app/Http/Controllers/Api/RecurringProductController.php routes/api.php tests/Feature/Api/ProductApiTest.php
git commit -m "feat(oc:8286): aggiungi liste read-only per Product e RecurringProduct"
```

---

### Task 6: Suite completa e verifica finale

**Files:**
- Nessun file nuovo — solo esecuzione e verifica

**Interfaces:**
- Consumes: tutti i controller/route/policy dei Task 1-5
- Produces: conferma che l'intera feature funziona end-to-end senza regressioni

- [ ] **Step 1: Esegui l'intera suite dei nuovi test**

```bash
docker exec php81_orchestrator php artisan test --filter=QuotePolicyTest
docker exec php81_orchestrator php artisan test --filter=QuoteApiTest
docker exec php81_orchestrator php artisan test --filter=ProductApiTest
```

Expected: PASS su tutti e tre i file.

- [ ] **Step 2: Esegui la suite Quote esistente per verificare non-regressione**

```bash
docker exec php81_orchestrator php artisan test --filter=Quote
```

Expected: PASS (include `QuoteGetTotalPriceTest`, `QuoteGetNetPriceTest`, `QuoteGetTotalRecurringPriceTest`, `QuoteGetTotalAdditionalServicesPriceTest`, `QuoteTotalsAttributesTest`, `SalesQuoteColumnAggregatorTest` — nessuno di questi tocca `QuotePolicy` o le nuove route, ma verificano che il fix alla Policy non abbia rotto il calcolo dei totali riusato in `formatQuote`).

- [ ] **Step 3: Nessun commit in questo task — è solo verifica**

