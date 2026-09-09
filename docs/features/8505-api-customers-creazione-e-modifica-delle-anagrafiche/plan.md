> Ticket: oc:8505

# API Customers: creazione e modifica delle anagrafiche — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.
>
> ⚠️ **Webmapp override:** in questo progetto i commit sono vietati durante l'esecuzione automatica. Il passo "Commit" di ogni task è un'istruzione testuale per lo sviluppatore/il dev, non un'azione da eseguire autonomamente — non eseguire `git commit`/`git push`/`git add` per nessun motivo durante l'esecuzione del piano.

**Goal:** Esporre `POST /api/customers` e `PATCH /api/customers/{customer}`, complemento delle `GET` già rilasciate in oc:8291, così che le anagrafiche clienti possano essere create/aggiornate dallo stesso flusso automatico che già gestisce preventivi e task.

**Architecture:** Due nuovi metodi su `CustomerController` esistente (`store()`/`update()`), autorizzati con lo stesso `authorizeRole()` privato già usato da `index()`/`show()` (Admin/Manager only). Una nuova `CustomerApiRequest` fa da whitelist di validazione stretta (mai `$request->all()`). Il controller non usa mai `$customer->fill()`: assegna i campi uno a uno per attributo esplicito, così `Customer::$fillable` (che espone campi di integrazione come `hs_id`/`wmpm_id`/`associated_user_id` mai richiesti da questo ticket) resta irrilevante — nessun rischio di mass-assignment involontario. In aggiunta, un fix mirato e indipendente sulla documentazione OpenAPI di Quote (`additional_services` era tipizzato come array di stringhe invece che oggetto).

**Tech Stack:** Laravel 10, Laravel Sanctum (auth API), PHPUnit + `DatabaseTransactions`, `dedoc/scramble` v0.13.35 per la doc OpenAPI.

**Spec:** `docs/features/8505-api-customers-creazione-e-modifica-delle-anagrafiche/overview.md`

## Global Constraints

- Nessuna migration: tutte le colonne necessarie (`name`, `full_name`, `vat`, `address`, `email`, `phone`, `status`, `notes`) esistono già sulla tabella `customers`.
- Autorizzazione Admin/Manager only, invariata rispetto al `GET` esistente — nessuna modifica a `CustomerPolicy.php` (il controller non la usa).
- `CustomerApiRequest::rules()` è una whitelist stretta: solo `name`, `company_name`, `vat`, `address`, `contact_emails`, `contact_emails_add`, `phone`, `status`, `notes`. Nessun altro campo di `Customer::$fillable` è scrivibile via questi endpoint.
- `contact_emails` (replace-all) e `contact_emails_add` (append) sono mutuamente esclusivi nello stesso payload.
- `vat`: 11 cifre numeriche (`regex:/^[0-9]{11}$/`), nessun vincolo di unicità — solo warning applicativo su duplicati.
- `name` è opzionale in `POST`: se omesso, generato da `company_name` via `Str::slug($base, '_')` con suffisso numerico su collisione, verificato con query esplicita (`Customer::where('name', $slug)->exists()`), nessun lock/transazione dedicata.
- Fuori scope (non toccare in questo piano): `DELETE /customers`, `mobile_phone`, qualsiasi hook su `Quote`, fix di `AlignTagsCommand`, vincoli DB `unique`, endpoint annidati per le email.

---

### Task 1: `CustomerApiRequest` — whitelist di validazione

**Files:**
- Create: `app/Http/Requests/Api/CustomerApiRequest.php`
- Test: `tests/Feature/Api/CustomerApiTest.php` (creato in questo task, esteso nei successivi)

**Interfaces:**
- Consumes: `App\Enums\CustomerStatus` (esistente, valori `unknown`/`opportunity`/`active`/`lost`, non modificato)
- Produces: `CustomerApiRequest::validated()` — array con chiavi opzionali tra `name`, `company_name`, `vat`, `address`, `contact_emails` (array di stringhe email), `contact_emails_add` (stringa o array di stringhe email), `phone`, `status`, `notes`. Consumato da `CustomerController::store()`/`update()` nel Task 2/3.

Questo task non ha ancora un endpoint raggiungibile via HTTP (arriva nel Task 2): i test di validazione useranno direttamente il metodo `rules()`/`withValidator()` tramite il `Validator` facade, per non anticipare logica del controller.

- [ ] **Step 1: Scrivi il test delle regole base (name/company_name/vat/status opzionali, tutti stringhe)**

Crea `tests/Feature/Api/CustomerApiTest.php`:

```php
<?php

namespace Tests\Feature\Api;

use App\Enums\CustomerStatus;
use App\Http\Requests\Api\CustomerApiRequest;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class CustomerApiTest extends TestCase
{
    private function validate(array $data, string $method = 'POST'): \Illuminate\Contracts\Validation\Validator
    {
        $request = new CustomerApiRequest();
        $request->setMethod($method);
        $request->merge($data);

        $validator = Validator::make($data, $request->rules());
        $request->withValidator($validator);

        return $validator;
    }

    /** @test */
    public function regole_base_passano_con_campi_scrivibili_validi(): void
    {
        $validator = $this->validate([
            'name'         => 'cliente_test',
            'company_name' => 'Cliente Test SRL',
            'vat'          => '01164510503',
            'address'      => 'Via Roma 1',
            'phone'        => '+39 328 5360803',
            'status'       => CustomerStatus::Active->value,
            'notes'        => 'nota',
        ]);

        $this->assertFalse($validator->fails(), $validator->errors()->first());
    }

    /** @test */
    public function name_company_name_address_notes_sono_sempre_opzionali(): void
    {
        $validator = $this->validate([]);

        $this->assertFalse($validator->fails(), $validator->errors()->first());
    }
}
```

- [ ] **Step 2: Esegui il test — deve fallire (classe non esiste)**

```
docker exec -it php81_orchestrator php artisan test --filter=CustomerApiTest
```

Atteso: FAIL con `Class "App\Http\Requests\Api\CustomerApiRequest" not found`.

- [ ] **Step 3: Crea `CustomerApiRequest` con le regole base**

Crea `app/Http/Requests/Api/CustomerApiRequest.php`:

```php
<?php

namespace App\Http\Requests\Api;

use App\Enums\CustomerStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Whitelist esplicita sui soli campi richiesti da oc:8505. Customer::$fillable
 * espone molti più campi (hs_id, wmpm_id, domain_name, associated_user_id, ...)
 * che NON devono essere scrivibili via API — il controller non farà mai
 * $customer->fill($validated), assegna solo i campi elencati qui uno a uno.
 */
class CustomerApiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'                  => ['sometimes', 'nullable', 'string', 'max:255'],
            'company_name'          => ['sometimes', 'nullable', 'string', 'max:255'],
            'vat'                   => ['sometimes', 'nullable', 'regex:/^[0-9]{11}$/'],
            'address'               => ['sometimes', 'nullable', 'string'],
            'contact_emails'        => ['sometimes', 'nullable', 'array'],
            'contact_emails.*'      => ['email'],
            'contact_emails_add'    => ['sometimes', 'nullable', function ($attribute, $value, $fail) {
                foreach (is_array($value) ? $value : [$value] as $email) {
                    if (!is_string($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                        $fail(__('Each contact_emails_add entry must be a valid email address.'));
                        return;
                    }
                }
            }],
            'phone'                 => ['sometimes', 'nullable', 'string', 'max:255'],
            'status'                => ['sometimes', Rule::in(array_column(CustomerStatus::cases(), 'value'))],
            'notes'                 => ['sometimes', 'nullable', 'string'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        //
    }
}
```

- [ ] **Step 4: Esegui il test — deve passare**

```
docker exec -it php81_orchestrator php artisan test --filter=CustomerApiTest
```

Atteso: PASS (2 test).

- [ ] **Step 5: Scrivi il test del formato P.IVA (11 cifre numeriche)**

Aggiungi a `CustomerApiTest`:

```php
    /** @test */
    public function vat_deve_essere_11_cifre_numeriche(): void
    {
        $this->assertTrue($this->validate(['vat' => '123'])->fails());
        $this->assertTrue($this->validate(['vat' => 'ABCDEFGHIJK'])->fails());
        $this->assertFalse($this->validate(['vat' => '01164510503'])->fails());
    }
```

- [ ] **Step 6: Esegui — deve passare senza modifiche** (la regex è già in `rules()`)

```
docker exec -it php81_orchestrator php artisan test --filter=CustomerApiTest
```

Atteso: PASS (3 test).

- [ ] **Step 7: Scrivi il test del formato status enum**

```php
    /** @test */
    public function status_deve_essere_un_valore_enum_valido(): void
    {
        $this->assertTrue($this->validate(['status' => 'not_a_status'])->fails());
        $this->assertFalse($this->validate(['status' => CustomerStatus::Opportunity->value])->fails());
    }
```

- [ ] **Step 8: Esegui — deve passare senza modifiche**

```
docker exec -it php81_orchestrator php artisan test --filter=CustomerApiTest
```

Atteso: PASS (4 test).

- [ ] **Step 9: Scrivi i test su `contact_emails`/`contact_emails_add` (formato e mutua esclusione)**

```php
    /** @test */
    public function contact_emails_valida_formato_di_ogni_indirizzo(): void
    {
        $this->assertTrue($this->validate(['contact_emails' => ['non-una-email']])->fails());
        $this->assertFalse($this->validate(['contact_emails' => ['a@example.com', 'b@example.com']])->fails());
    }

    /** @test */
    public function contact_emails_add_accetta_stringa_singola_o_array(): void
    {
        $this->assertFalse($this->validate(['contact_emails_add' => 'a@example.com'])->fails());
        $this->assertFalse($this->validate(['contact_emails_add' => ['a@example.com', 'b@example.com']])->fails());
        $this->assertTrue($this->validate(['contact_emails_add' => 'non-una-email'])->fails());
        $this->assertTrue($this->validate(['contact_emails_add' => ['a@example.com', 'non-una-email']])->fails());
    }

    /** @test */
    public function contact_emails_e_contact_emails_add_sono_mutuamente_esclusivi(): void
    {
        $validator = $this->validate([
            'contact_emails'     => ['a@example.com'],
            'contact_emails_add' => 'b@example.com',
        ]);

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('contact_emails_add', $validator->errors()->toArray());
    }
```

- [ ] **Step 10: Esegui — i primi due passano, il terzo fallisce** (mutua esclusione non ancora implementata)

```
docker exec -it php81_orchestrator php artisan test --filter=CustomerApiTest
```

Atteso: FAIL su `contact_emails_e_contact_emails_add_sono_mutuamente_esclusivi`.

- [ ] **Step 11: Implementa la mutua esclusione in `withValidator()`**

In `app/Http/Requests/Api/CustomerApiRequest.php`, sostituisci il corpo vuoto di `withValidator()`:

```php
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($this->has('contact_emails') && $this->has('contact_emails_add')) {
                $validator->errors()->add(
                    'contact_emails_add',
                    __('contact_emails and contact_emails_add are mutually exclusive in the same request.')
                );
            }
        });
    }
```

- [ ] **Step 12: Esegui — tutti i test passano**

```
docker exec -it php81_orchestrator php artisan test --filter=CustomerApiTest
```

Atteso: PASS (7 test).

- [ ] **Step 13: Scrivi il test di validazione del `phone`, riusando la stessa logica di plausibilità già in `App\Nova\Customer::isValidPhoneFragment()` (oc:8412)**

```php
    /** @test */
    public function phone_valida_uno_o_piu_numeri_separati_da_virgola(): void
    {
        $this->assertFalse($this->validate(['phone' => '+39 328 5360803'])->fails());
        $this->assertFalse($this->validate(['phone' => '+39 328 5360803, +39 02 1234567'])->fails());
        $this->assertTrue($this->validate(['phone' => 'non un numero'])->fails());
        $this->assertTrue($this->validate(['phone' => '12345'])->fails());
    }
```

- [ ] **Step 14: Esegui — deve fallire** (nessuna validazione di formato sul phone ancora)

```
docker exec -it php81_orchestrator php artisan test --filter=CustomerApiTest
```

Atteso: FAIL su `phone_valida_uno_o_piu_numeri_separati_da_virgola`.

- [ ] **Step 15: Aggiungi la validazione del `phone` — mirror strutturale di `App\Nova\Customer::isValidPhoneFragment()`, duplicato deliberatamente (diff minimo, `App\Nova\Customer` non è nei moduli toccati di questo ticket) e senza la logica "unless unchanged" di Nova (una PATCH invia solo i campi effettivamente cambiati, non l'intero form)**

In `app/Http/Requests/Api/CustomerApiRequest.php`, sostituisci la riga `'phone' => [...]` in `rules()`:

```php
            'phone'                 => ['sometimes', 'nullable', 'string', 'max:255', function ($attribute, $value, $fail) {
                if ($error = $this->phoneValidationError($value)) {
                    $fail($error);
                }
            }],
```

Aggiungi due metodi privati alla classe, dopo `rules()`:

```php
    private function phoneValidationError(?string $value): ?string
    {
        if (blank($value)) {
            return null;
        }

        $fragments = collect(explode(',', $value))
            ->map(fn ($fragment) => trim($fragment))
            ->filter(fn ($fragment) => $fragment !== '');

        foreach ($fragments as $fragment) {
            if (!$this->isValidPhoneFragment($fragment)) {
                return __('One or more numbers are not in a valid phone format.');
            }
        }

        return null;
    }

    private function isValidPhoneFragment(string $fragment): bool
    {
        if (!preg_match('/^[\d+\s\-.()]+$/', $fragment)) {
            return false;
        }

        $digits = preg_replace('/[^\d+]/', '', $fragment) ?? '';
        if ($digits === '') {
            return false;
        }

        if ($digits[0] === '+') {
            return (bool) preg_match('/^\+\d{8,15}$/', $digits);
        }

        return (bool) preg_match('/^\d{6,11}$/', $digits);
    }
```

- [ ] **Step 16: Esegui — tutti i test passano**

```
docker exec -it php81_orchestrator php artisan test --filter=CustomerApiTest
```

Atteso: PASS (8 test).

- [ ] **Step 17: Commit**

```bash
git add app/Http/Requests/Api/CustomerApiRequest.php tests/Feature/Api/CustomerApiTest.php
git commit -m "feat(oc:8505): add CustomerApiRequest validation whitelist"
```

---

### Task 2: `CustomerController::store()` + rotta `POST /customers`

**Files:**
- Modify: `app/Http/Controllers/Api/CustomerController.php`
- Modify: `routes/api.php`
- Test: `tests/Feature/Api/CustomerApiTest.php`

**Interfaces:**
- Consumes: `CustomerApiRequest::validated()` (Task 1); `Customer::$fillable`, `Customer::normalizePhoneString()`, `Customer::getContactEmailsAttribute()` (esistenti, non modificati); `CustomerController::authorizeRole()` e `CustomerController::formatCustomer()` (esistenti, privati, non modificati).
- Produces: `CustomerController::store(CustomerApiRequest $request): JsonResponse` — risposta 201 con lo stesso shape di `formatCustomer()` più una chiave opzionale `warnings` (array di `{type: string, customers: array<{id:int, name:string}>}>`). Metodi privati nuovi riusati dal Task 3: `resolveName(array $validated): string`, `uniqueSlug(string $base): string`, `applyWritableFields(Customer $customer, array $validated): void`, `applyContactEmails(Customer $customer, array $validated): void`, `duplicateVatWarnings(Customer $customer): array`.

- [ ] **Step 1: Scrivi il test di autorizzazione e la rotta minima**

Aggiungi in cima a `tests/Feature/Api/CustomerApiTest.php`, dentro la classe, i due helper e i primi test:

```php
    private function actingAsAdmin(): \App\Models\User
    {
        $user = \App\Models\User::factory()->create(['roles' => [\App\Enums\UserRole::Admin]]);
        \Laravel\Sanctum\Sanctum::actingAs($user);
        return $user;
    }

    private function actingAsDeveloper(): \App\Models\User
    {
        $user = \App\Models\User::factory()->create(['roles' => [\App\Enums\UserRole::Developer]]);
        \Laravel\Sanctum\Sanctum::actingAs($user);
        return $user;
    }

    /** @test */
    public function utente_non_autenticato_su_store_ottiene_401(): void
    {
        $this->postJson('/api/customers', ['name' => 'test'])->assertStatus(401);
    }

    /** @test */
    public function developer_non_puo_creare_customer(): void
    {
        $this->actingAsDeveloper();

        $this->postJson('/api/customers', ['name' => 'test'])->assertStatus(403);
    }
```

Aggiungi anche `use Illuminate\Foundation\Testing\DatabaseTransactions;` e `use DatabaseTransactions;` nella classe (vedi `QuoteApiTest.php` come riferimento di stile):

```php
use Illuminate\Foundation\Testing\DatabaseTransactions;
```

e, subito dopo `class CustomerApiTest extends TestCase`:

```php
    use DatabaseTransactions;
```

- [ ] **Step 2: Esegui — deve fallire** (rotta non esiste, 404 invece di 401/403)

```
docker exec -it php81_orchestrator php artisan test --filter=CustomerApiTest
```

Atteso: FAIL — nessuna rotta `POST /api/customers`.

- [ ] **Step 3: Aggiungi la rotta e uno `store()` minimo**

In `routes/api.php`, subito dopo la riga `Route::get('/customers/{customer}', [CustomerController::class, 'show']);`:

```php
    Route::post('/customers', [CustomerController::class, 'store']);
    Route::patch('/customers/{customer}', [CustomerController::class, 'update']);
```

In `app/Http/Controllers/Api/CustomerController.php`, aggiungi l'import in cima:

```php
use App\Http\Requests\Api\CustomerApiRequest;
use Illuminate\Support\Str;
```

Aggiungi il metodo `store()` subito dopo `show()`:

```php
    /**
     * Create a customer.
     *
     * @response 201 array{id: int, name: string, company_name: string|null, vat: string|null, address: string|null, contact_emails: array<string>, phone: string|null, status: string|null, owner: array{id: int, name: string}|null, notes: string|null, warnings?: array<array{type: string, customers: array<array{id: int, name: string}>}>}
     */
    public function store(CustomerApiRequest $request): JsonResponse
    {
        $this->authorizeRole($request);

        $customer = new Customer();
        $customer->save();

        return response()->json($this->formatCustomer($customer), 201);
    }
```

- [ ] **Step 4: Esegui — deve passare**

```
docker exec -it php81_orchestrator php artisan test --filter=CustomerApiTest
```

Atteso: PASS.

- [ ] **Step 5: Scrivi i test di creazione con `name` esplicito e con `name` auto-generato**

```php
    /** @test */
    public function store_crea_customer_con_name_esplicito(): void
    {
        $this->actingAsAdmin();

        $response = $this->postJson('/api/customers', ['name' => 'mio_cliente'])->assertStatus(201);

        $response->assertJsonPath('name', 'mio_cliente');
        $this->assertDatabaseHas('customers', ['name' => 'mio_cliente']);
    }

    /** @test */
    public function store_genera_name_da_company_name_quando_omesso(): void
    {
        $this->actingAsAdmin();

        $response = $this->postJson('/api/customers', ['company_name' => 'Parco Minerario Alta Valsugana'])
            ->assertStatus(201);

        $response->assertJsonPath('name', 'parco_minerario_alta_valsugana');
        $response->assertJsonPath('company_name', 'Parco Minerario Alta Valsugana');
    }

    /** @test */
    public function store_deduplica_name_generato_su_collisione(): void
    {
        $this->actingAsAdmin();
        \App\Models\Customer::factory()->create(['name' => 'acme']);

        $response = $this->postJson('/api/customers', ['company_name' => 'Acme'])->assertStatus(201);

        $response->assertJsonPath('name', 'acme_2');
    }

    /** @test */
    public function store_senza_name_ne_company_name_genera_uno_slug_di_fallback(): void
    {
        $this->actingAsAdmin();

        $response = $this->postJson('/api/customers', [])->assertStatus(201);

        $this->assertNotEmpty($response->json('name'));
    }
```

- [ ] **Step 6: Esegui — deve fallire** (`name` sempre vuoto)

```
docker exec -it php81_orchestrator php artisan test --filter=CustomerApiTest
```

Atteso: FAIL sui 4 test appena aggiunti.

- [ ] **Step 7: Implementa la generazione/assegnazione di `name` e i campi scrivibili base**

Sostituisci il corpo di `store()` in `app/Http/Controllers/Api/CustomerController.php`:

```php
    public function store(CustomerApiRequest $request): JsonResponse
    {
        $this->authorizeRole($request);

        $validated = $request->validated();

        $customer = new Customer();
        $customer->name = $this->resolveName($validated);
        $this->applyWritableFields($customer, $validated);
        $customer->save();

        return response()->json($this->formatCustomer($customer->fresh('owner')), 201);
    }
```

Aggiungi i metodi privati alla fine della classe, prima della graffa di chiusura:

```php
    private function resolveName(array $validated): string
    {
        if (!empty($validated['name'])) {
            return $validated['name'];
        }

        $base = Str::slug($validated['company_name'] ?? '', '_');

        return $this->uniqueSlug($base !== '' ? $base : 'customer');
    }

    private function uniqueSlug(string $base): string
    {
        $slug = $base;
        $suffix = 1;

        while (Customer::where('name', $slug)->exists()) {
            $suffix++;
            $slug = "{$base}_{$suffix}";
        }

        return $slug;
    }

    private function applyWritableFields(Customer $customer, array $validated): void
    {
        if (array_key_exists('company_name', $validated)) {
            $customer->full_name = $validated['company_name'];
        }
        if (array_key_exists('vat', $validated)) {
            $customer->vat = $validated['vat'];
        }
        if (array_key_exists('address', $validated)) {
            $customer->address = $validated['address'];
        }
        if (array_key_exists('phone', $validated)) {
            $customer->phone = $validated['phone'];
        }
        if (array_key_exists('status', $validated)) {
            $customer->status = $validated['status'];
        }
        if (array_key_exists('notes', $validated)) {
            $customer->notes = $validated['notes'];
        }
    }
```

Nota: ogni assegnazione usa l'attributo esplicito (`$customer->vat = ...`), mai `$customer->fill($validated)` — coerente con il vincolo globale sulla whitelist, indipendentemente da `Customer::$fillable`.

- [ ] **Step 8: Esegui — tutti i test passano**

```
docker exec -it php81_orchestrator php artisan test --filter=CustomerApiTest
```

Atteso: PASS.

- [ ] **Step 9: Scrivi i test sui campi scrivibili base (vat/address/phone/status/notes) e sull'ignoring di campi fuori whitelist**

```php
    /** @test */
    public function store_scrive_vat_address_phone_status_notes(): void
    {
        $this->actingAsAdmin();

        $response = $this->postJson('/api/customers', [
            'name'    => 'cliente_completo',
            'vat'     => '01164510503',
            'address' => 'Via Roma 1',
            'phone'   => '+39 328 5360803',
            'status'  => \App\Enums\CustomerStatus::Active->value,
            'notes'   => 'Nota di test',
        ])->assertStatus(201);

        $response->assertJson([
            'vat'     => '01164510503',
            'address' => 'Via Roma 1',
            'phone'   => '+39 328 5360803',
            'status'  => \App\Enums\CustomerStatus::Active->value,
            'notes'   => 'Nota di test',
        ]);
    }

    /** @test */
    public function store_ignora_campi_fuori_whitelist(): void
    {
        $this->actingAsAdmin();

        $this->postJson('/api/customers', [
            'name'  => 'test_whitelist',
            'hs_id' => 'HS-999',
        ])->assertStatus(201);

        $customer = \App\Models\Customer::where('name', 'test_whitelist')->first();
        $this->assertNull($customer->hs_id);
    }
```

- [ ] **Step 10: Esegui — devono passare senza ulteriori modifiche**

```
docker exec -it php81_orchestrator php artisan test --filter=CustomerApiTest
```

Atteso: PASS (la whitelist è già garantita dal fatto che `applyWritableFields` non referenzia mai `hs_id`).

- [ ] **Step 11: Scrivi il test di validazione formato P.IVA/phone/status end-to-end (422)**

```php
    /** @test */
    public function store_restituisce_422_per_vat_non_valida(): void
    {
        $this->actingAsAdmin();

        $this->postJson('/api/customers', ['name' => 'test', 'vat' => '123'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['vat']);
    }

    /** @test */
    public function store_restituisce_422_per_phone_non_valido(): void
    {
        $this->actingAsAdmin();

        $this->postJson('/api/customers', ['name' => 'test', 'phone' => 'non un numero'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['phone']);
    }
```

- [ ] **Step 12: Esegui — devono passare** (la validazione è già in `CustomerApiRequest` dal Task 1)

```
docker exec -it php81_orchestrator php artisan test --filter=CustomerApiTest
```

Atteso: PASS.

- [ ] **Step 13: Commit**

```bash
git add app/Http/Controllers/Api/CustomerController.php routes/api.php tests/Feature/Api/CustomerApiTest.php
git commit -m "feat(oc:8505): add POST /api/customers with name auto-generation"
```

---

### Task 3: `contact_emails`/`contact_emails_add`, warning P.IVA duplicata, `PATCH /customers/{customer}`

**Files:**
- Modify: `app/Http/Controllers/Api/CustomerController.php`
- Test: `tests/Feature/Api/CustomerApiTest.php`

**Interfaces:**
- Consumes: `applyWritableFields()`, `resolveName()`, `uniqueSlug()` (Task 2); `Customer::getContactEmailsAttribute()` (esistente, split su virgola/spazio della colonna `email`).
- Produces: `CustomerController::update(CustomerApiRequest $request, Customer $customer): JsonResponse`; metodi privati nuovi `applyContactEmails(Customer $customer, array $validated): void` (chiamato da `applyWritableFields`, riusato sia da `store()` che da `update()`) e `duplicateVatWarnings(Customer $customer): array` (chiamato solo da `store()`, come da overview — il ticket lo richiede solo "in POST").

- [ ] **Step 1: Scrivi i test su `contact_emails` in creazione**

```php
    /** @test */
    public function store_scrive_contact_emails_come_array(): void
    {
        $this->actingAsAdmin();

        $response = $this->postJson('/api/customers', [
            'name'           => 'test_emails',
            'contact_emails' => ['a@example.com', 'b@example.com'],
        ])->assertStatus(201);

        $response->assertJsonPath('contact_emails', ['a@example.com', 'b@example.com']);
    }
```

- [ ] **Step 2: Esegui — deve fallire** (`contact_emails` non ancora gestito, resta `[]`)

```
docker exec -it php81_orchestrator php artisan test --filter=CustomerApiTest
```

Atteso: FAIL.

- [ ] **Step 3: Implementa `applyContactEmails()` e chiamala da `applyWritableFields()`**

In `app/Http/Controllers/Api/CustomerController.php`, aggiungi alla fine di `applyWritableFields()` (dopo il blocco `if (array_key_exists('notes', ...))`):

```php
        $this->applyContactEmails($customer, $validated);
```

Aggiungi il nuovo metodo privato subito dopo `applyWritableFields()`:

```php
    /**
     * `contact_emails` (replace-all) e `contact_emails_add` (append) sono
     * mutuamente esclusivi — già garantito da CustomerApiRequest::withValidator().
     * La colonna `email` resta testo libero comma-separated, coerente col
     * formato già in produzione (Customer::getContactEmailsAttribute()).
     */
    private function applyContactEmails(Customer $customer, array $validated): void
    {
        if (array_key_exists('contact_emails', $validated)) {
            $customer->email = implode(',', $validated['contact_emails'] ?? []);
            return;
        }

        if (array_key_exists('contact_emails_add', $validated)) {
            $toAdd = is_array($validated['contact_emails_add'])
                ? $validated['contact_emails_add']
                : [$validated['contact_emails_add']];

            $merged = array_values(array_unique(array_merge($customer->contact_emails, $toAdd)));
            $customer->email = implode(',', $merged);
        }
    }
```

- [ ] **Step 4: Esegui — deve passare**

```
docker exec -it php81_orchestrator php artisan test --filter=CustomerApiTest
```

Atteso: PASS.

- [ ] **Step 5: Scrivi i test sul warning P.IVA duplicata**

```php
    /** @test */
    public function store_segnala_vat_duplicata_senza_bloccare(): void
    {
        $this->actingAsAdmin();
        $existing = \App\Models\Customer::factory()->create(['name' => 'timesis', 'vat' => '01164510503']);

        $response = $this->postJson('/api/customers', [
            'name' => 'montepisano',
            'vat'  => '01164510503',
        ])->assertStatus(201);

        $response->assertJsonPath('warnings.0.type', 'duplicate_vat');
        $response->assertJsonPath('warnings.0.customers.0.id', $existing->id);
    }

    /** @test */
    public function store_non_include_warnings_senza_vat_duplicata(): void
    {
        $this->actingAsAdmin();

        $response = $this->postJson('/api/customers', ['name' => 'test_no_dup', 'vat' => '01164510503'])
            ->assertStatus(201);

        $this->assertArrayNotHasKey('warnings', $response->json());
    }
```

- [ ] **Step 6: Esegui — deve fallire** (nessuna chiave `warnings` mai restituita)

```
docker exec -it php81_orchestrator php artisan test --filter=CustomerApiTest
```

Atteso: FAIL sul primo test, PASS sul secondo.

- [ ] **Step 7: Implementa `duplicateVatWarnings()` e collegala a `store()`**

Sostituisci il corpo di `store()`:

```php
    public function store(CustomerApiRequest $request): JsonResponse
    {
        $this->authorizeRole($request);

        $validated = $request->validated();

        $customer = new Customer();
        $customer->name = $this->resolveName($validated);
        $this->applyWritableFields($customer, $validated);
        $customer->save();

        $data = $this->formatCustomer($customer->fresh('owner'));

        if ($warnings = $this->duplicateVatWarnings($customer)) {
            $data['warnings'] = $warnings;
        }

        return response()->json($data, 201);
    }
```

Aggiungi il metodo privato dopo `applyContactEmails()`:

```php
    private function duplicateVatWarnings(Customer $customer): array
    {
        if (blank($customer->vat)) {
            return [];
        }

        $duplicates = Customer::where('vat', $customer->vat)
            ->where('id', '!=', $customer->id)
            ->get(['id', 'name']);

        if ($duplicates->isEmpty()) {
            return [];
        }

        return [[
            'type'      => 'duplicate_vat',
            'customers' => $duplicates->map(fn(Customer $c) => ['id' => $c->id, 'name' => $c->name])->all(),
        ]];
    }
```

- [ ] **Step 8: Esegui — tutti i test passano**

```
docker exec -it php81_orchestrator php artisan test --filter=CustomerApiTest
```

Atteso: PASS.

- [ ] **Step 9: Scrivi i test di `PATCH /customers/{customer}`**

```php
    /** @test */
    public function update_modifica_parzialmente_un_customer(): void
    {
        $this->actingAsAdmin();
        $customer = \App\Models\Customer::factory()->create([
            'name'   => 'test_update',
            'status' => \App\Enums\CustomerStatus::Unknown->value,
        ]);

        $response = $this->patchJson("/api/customers/{$customer->id}", [
            'status' => \App\Enums\CustomerStatus::Active->value,
        ])->assertStatus(200);

        $response->assertJsonPath('status', \App\Enums\CustomerStatus::Active->value);
        $this->assertEquals('test_update', $customer->fresh()->name);
    }

    /** @test */
    public function update_sostituisce_contact_emails_con_replace_all(): void
    {
        $this->actingAsAdmin();
        $customer = \App\Models\Customer::factory()->create(['name' => 'test_replace']);
        $customer->email = 'old@example.com';
        $customer->save();

        $this->patchJson("/api/customers/{$customer->id}", ['contact_emails' => ['new@example.com']])
            ->assertStatus(200)
            ->assertJsonPath('contact_emails', ['new@example.com']);
    }

    /** @test */
    public function update_aggiunge_email_con_contact_emails_add_senza_rimuovere_le_esistenti(): void
    {
        $this->actingAsAdmin();
        $customer = \App\Models\Customer::factory()->create(['name' => 'test_add']);
        $customer->email = 'old@example.com';
        $customer->save();

        $response = $this->patchJson("/api/customers/{$customer->id}", ['contact_emails_add' => 'new@example.com'])
            ->assertStatus(200);

        $this->assertEqualsCanonicalizing(['old@example.com', 'new@example.com'], $response->json('contact_emails'));
    }

    /** @test */
    public function update_restituisce_404_per_customer_inesistente(): void
    {
        $this->actingAsAdmin();

        $this->patchJson('/api/customers/999999', ['status' => \App\Enums\CustomerStatus::Active->value])
            ->assertStatus(404);
    }

    /** @test */
    public function developer_non_puo_modificare_customer(): void
    {
        $this->actingAsDeveloper();
        $customer = \App\Models\Customer::factory()->create(['name' => 'test_dev']);

        $this->patchJson("/api/customers/{$customer->id}", ['notes' => 'x'])->assertStatus(403);
    }
```

- [ ] **Step 10: Esegui — deve fallire** (`update()` non esiste ancora)

```
docker exec -it php81_orchestrator php artisan test --filter=CustomerApiTest
```

Atteso: FAIL — `update()` non definito sul controller.

- [ ] **Step 11: Implementa `update()`**

Aggiungi il metodo subito dopo `store()` in `app/Http/Controllers/Api/CustomerController.php`:

```php
    /**
     * Update a customer.
     *
     * @response array{id: int, name: string, company_name: string|null, vat: string|null, address: string|null, contact_emails: array<string>, phone: string|null, status: string|null, owner: array{id: int, name: string}|null, notes: string|null}
     */
    public function update(CustomerApiRequest $request, Customer $customer): JsonResponse
    {
        $this->authorizeRole($request);

        $validated = $request->validated();

        if (!empty($validated['name'])) {
            $customer->name = $validated['name'];
        }
        $this->applyWritableFields($customer, $validated);
        $customer->save();

        return response()->json($this->formatCustomer($customer->fresh('owner')));
    }
```

- [ ] **Step 12: Esegui — tutti i test passano**

```
docker exec -it php81_orchestrator php artisan test --filter=CustomerApiTest
```

Atteso: PASS (~20 test in totale nel file).

- [ ] **Step 13: Esegui l'intera suite per verificare che non ci siano regressioni**

```
docker exec -it php81_orchestrator php artisan test
```

Atteso: PASS su tutta la suite (nessuna regressione su `CustomerPhoneValidationTest`, `QuoteApiTest`, ecc.).

- [ ] **Step 14: Commit**

```bash
git add app/Http/Controllers/Api/CustomerController.php tests/Feature/Api/CustomerApiTest.php
git commit -m "feat(oc:8505): add PATCH /api/customers/{customer}, contact_emails handling and vat duplicate warning"
```

---

### Task 4: Fix documentazione OpenAPI — `additional_services` come oggetto

**Files:**
- Modify: `app/Http/Controllers/Api/QuoteController.php`
- Modify: `tests/Feature/Api/QuoteApiDocsTest.php`

**Interfaces:**
- Consumes: nessuna dipendenza dai Task 1-3 — task indipendente.
- Produces: nessuna nuova interfaccia di codice — solo metadata OpenAPI su `POST`/`PATCH /quotes`.

Verificato empiricamente in Fase: overview con `php artisan scramble:export` che `additional_services` risulta oggi `{"type": ["array","null"], "items": {"type": "string"}}`, mentre il formato reale è un oggetto `{descrizione: importo}`. L'attributo `#[BodyParameter]` con `type: 'object'` produce un `allOf` che compone correttamente il `$ref` esistente con l'override mirato su questa sola proprietà.

- [ ] **Step 1: Scrivi il test di regressione sulla doc**

Aggiungi in fondo a `tests/Feature/Api/QuoteApiDocsTest.php`, dentro la classe esistente (prima dell'ultima graffa di chiusura):

```php
    public function test_quotes_store_documents_additional_services_as_object(): void
    {
        $spec = $this->get('/docs/api.json')->json();
        $requestBodySchema = $spec['paths']['/quotes']['post']['requestBody']['content']['application/json']['schema'] ?? null;

        $this->assertNotNull($requestBodySchema, 'Expected a request body schema for POST /quotes.');

        $variants = $requestBodySchema['allOf'] ?? [$requestBodySchema];
        $additionalServicesSchema = collect($variants)
            ->pluck('properties.additional_services')
            ->filter()
            ->last();

        $this->assertNotNull($additionalServicesSchema, 'Expected additional_services to be documented on POST /quotes.');
        $this->assertEquals('object', $additionalServicesSchema['type'] ?? null, 'Expected additional_services to be documented as an object, not an array of strings.');
    }
```

- [ ] **Step 2: Esegui — deve fallire** (`additional_services` è ancora `array`)

```
docker exec -it php81_orchestrator php artisan test --filter=QuoteApiDocsTest
```

Atteso: FAIL — `additional_services` documentato come `array`, non `object`.

- [ ] **Step 3: Aggiungi l'attributo `#[BodyParameter]` su `store()`/`update()`**

In `app/Http/Controllers/Api/QuoteController.php`, aggiungi l'import in cima (accanto a `use Dedoc\Scramble\Attributes\QueryParameter;`):

```php
use Dedoc\Scramble\Attributes\BodyParameter;
```

Aggiungi l'attributo subito sopra la dichiarazione di `store()`:

```php
    #[BodyParameter('additional_services', description: 'Map of service description to price, e.g. {"Setup fee": 150.0}.', type: 'object')]
    public function store(QuoteApiRequest $request): JsonResponse
```

E lo stesso, subito sopra `update()`:

```php
    #[BodyParameter('additional_services', description: 'Map of service description to price, e.g. {"Setup fee": 150.0}.', type: 'object')]
    public function update(QuoteApiRequest $request, Quote $quote): JsonResponse
```

- [ ] **Step 4: Pulisci la cache Scramble (necessaria in locale, il test la rigenera comunque per request)**

```
docker exec -it php81_orchestrator php artisan scramble:clear
```

- [ ] **Step 5: Esegui — deve passare**

```
docker exec -it php81_orchestrator php artisan test --filter=QuoteApiDocsTest
```

Atteso: PASS su tutti i test del file (inclusi quelli preesistenti — nessuna regressione).

- [ ] **Step 6: Esegui l'intera suite**

```
docker exec -it php81_orchestrator php artisan test
```

Atteso: PASS su tutta la suite.

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/Api/QuoteController.php tests/Feature/Api/QuoteApiDocsTest.php
git commit -m "fix(oc:8505): document additional_services as object in OpenAPI schema"
```

---

## Self-Review

**Spec coverage** (contro `overview.md` → Requisiti):
- ✅ `POST`/`PATCH /customers` con tutti i campi scrivibili — Task 2/3
- ✅ `contact_emails`/`contact_emails_add` mutuamente esclusivi — Task 1 (validazione) + Task 3 (comportamento)
- ✅ Validazione phone/email/vat/status — Task 1
- ✅ Warning P.IVA duplicata solo su POST, mai bloccante — Task 3
- ✅ `name` auto-generato con dedup — Task 2
- ✅ Whitelist stretta anti mass-assignment — Task 1 (regole) + Task 2 (assegnazione esplicita, mai `fill()`)
- ✅ Autorizzazione Admin/Manager only — Task 2 (riuso `authorizeRole()` esistente)
- ✅ Fix documentazione `additional_services` — Task 4

**Placeholder scan:** nessun `TODO`/`TBD` nei blocchi di codice; ogni step ha codice completo, non descrittivo.

**Type consistency:** `CustomerApiRequest::validated()` produce sempre le stesse chiavi (`name`, `company_name`, `vat`, `address`, `contact_emails`, `contact_emails_add`, `phone`, `status`, `notes`) usate identicamente in `resolveName()`, `applyWritableFields()`, `applyContactEmails()` in tutti i task che li richiamano.
