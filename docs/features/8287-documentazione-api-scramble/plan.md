> Ticket: oc:8287

# Documentazione API con Scramble (OpenAPI/Swagger) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking. **No commit or branch automatico**: i comandi `git commit` nei passi sono istruzioni testuali per l'utente, non azioni da eseguire autonomamente.

**Goal:** Generare e pubblicare una documentazione OpenAPI/Swagger interattiva delle API REST di Orchestrator tramite `dedoc/scramble`, accessibile pubblicamente su `/docs/api` e linkata dal menu Nova.

**Architecture:** `dedoc/scramble` analizza staticamente `routes/api.php` e i controller in `app/Http/Controllers/Api/` per generare la spec OpenAPI a runtime; la UI (Stoplight Elements) viene servita dalla route pubblicata dal package. Per le risposte non inferibili automaticamente (metodi che ritornano array custom senza type hint strutturato) si aggiungono annotazioni PHPDoc `@response`. Le operazioni mutanti (POST/PATCH/DELETE) vengono private del requisito di sicurezza nella spec generata tramite un document transformer, così il pulsante "Try it out" di Stoplight Elements non può iniettare il Bearer token su quelle richieste.

**Tech Stack:** Laravel 12, PHP 8.4, `dedoc/scramble` (nuova dipendenza), Sanctum (auth esistente), Laravel Nova.

## Global Constraints

- Route docs pubblica: nessuna autenticazione richiesta per `GET /docs/api` e `GET /docs/api.json`.
- "Try it out" abilitato solo per richieste GET; disabilitato (nessun security requirement iniettabile) per POST/PATCH/DELETE.
- Titoli, descrizioni e summary della doc in inglese.
- Nessuna modifica strutturale a `routes/api.php` o ai controller oltre alle annotazioni PHPDoc `@response`.
- Nessun refactoring verso API Resource classes (out of scope).
- Nessuna restrizione di accesso aggiuntiva (basic auth, ambiente) oltre al `throttle:api` globale già esistente.

---

### Task 1: Installare e configurare dedoc/scramble su `/docs/api`

**Files:**
- Modify: `composer.json`, `composer.lock`
- Create: `config/scramble.php` (pubblicato dal package)
- Test: `tests/Feature/Api/ApiDocsTest.php`

**Interfaces:**
- Consumes: nessuna dipendenza da task precedenti (primo task)
- Produces: route pubbliche `GET /docs/api` (UI) e `GET /docs/api.json` (spec OpenAPI), consumate da Task 3 (security transformer) e Task 6 (verifica manuale)

- [ ] **Step 1: Installare il pacchetto**

```bash
docker exec php81_orchestrator composer require dedoc/scramble
```

Expected: `composer.json` e `composer.lock` aggiornati, nessun conflitto di versione con `laravel/framework ^12.0` / `php ^8.4`.

- [ ] **Step 2: Pubblicare la configurazione**

```bash
docker exec php81_orchestrator php artisan vendor:publish --provider="Dedoc\Scramble\ScrambleServiceProvider" --tag=scramble-config
```

Expected: creato `config/scramble.php`.

- [ ] **Step 3: Configurare path pubblico e info doc**

Apri `config/scramble.php` appena pubblicato e verifica/imposta le chiavi effettivamente presenti (i nomi esatti dipendono dalla versione installata — usa questo come riferimento, non come copia letterale):

```php
'api_path' => 'docs/api',

'info' => [
    'version' => '1.0.0',
    'description' => 'Orchestrator REST API documentation.',
],

'middleware' => [
    'web',
],
```

Rimuovi qualsiasi middleware di autenticazione (`auth`, `verified`, ecc.) eventualmente presente di default nell'array `middleware`, così la route resta pubblica come deciso in reverse-interaction.

- [ ] **Step 4: Scrivere il test di accesso pubblico**

```php
<?php

namespace Tests\Feature\Api;

use Tests\TestCase;

class ApiDocsTest extends TestCase
{
    public function test_docs_ui_is_publicly_accessible(): void
    {
        $response = $this->get('/docs/api');

        $response->assertStatus(200);
    }

    public function test_openapi_spec_is_publicly_accessible_and_valid_json(): void
    {
        $response = $this->get('/docs/api.json');

        $response->assertStatus(200);
        $response->assertHeader('content-type', 'application/json');
        $this->assertIsArray($response->json('paths'));
    }
}
```

- [ ] **Step 5: Eseguire il test e verificare che passi**

```bash
docker exec php81_orchestrator php artisan test --filter=ApiDocsTest
```

Expected: PASS su entrambi i test. Se fallisce con 404, verifica che `api_path` in `config/scramble.php` corrisponda a `docs/api` e che `php artisan route:list` mostri le route `docs/api` e `docs/api.json`.

- [ ] **Step 6: Commit**

```bash
git add composer.json composer.lock config/scramble.php tests/Feature/Api/ApiDocsTest.php
git commit -m "feat(oc:8287): install and expose dedoc/scramble on /docs/api"
```

---

### Task 2: Restringere "Try it out" alle sole richieste GET

**Files:**
- Modify: `app/Providers/AppServiceProvider.php` (registrazione del document transformer nel metodo `boot()`)
- Modify: `tests/Feature/Api/ApiDocsTest.php`

**Interfaces:**
- Consumes: route `GET /docs/api.json` prodotta da Task 1
- Produces: spec OpenAPI in cui le operazioni non-GET hanno `security: []` — nessuna interfaccia consumata da altri task

- [ ] **Step 1: Ispezionare il punto di estensione reale del pacchetto installato**

```bash
grep -rn "afterOpenApiGenerated\|extendOpenApi\|registerUiRoute" vendor/dedoc/scramble/src/ | head -20
```

Usa l'output per confermare il nome esatto del metodo statico di estensione esposto da `Dedoc\Scramble\Scramble` nella versione installata (il nome può differire da quello indicato sotto se la libreria ha cambiato API tra versioni).

- [ ] **Step 2: Scrivere il test che verifica la restrizione**

Aggiungi in `tests/Feature/Api/ApiDocsTest.php`:

```php
    public function test_mutating_operations_have_no_security_requirement_in_spec(): void
    {
        $spec = $this->get('/docs/api.json')->json();

        $storyPostOperation = $spec['paths']['/api/stories']['post'] ?? null;
        $this->assertNotNull($storyPostOperation, 'Expected POST /api/stories in generated spec.');
        $this->assertSame([], $storyPostOperation['security'] ?? null);
    }

    public function test_readonly_operations_keep_security_requirement_in_spec(): void
    {
        $spec = $this->get('/docs/api.json')->json();

        $storyShowOperation = $spec['paths']['/api/stories/{story}']['get'] ?? null;
        $this->assertNotNull($storyShowOperation, 'Expected GET /api/stories/{story} in generated spec.');
        $this->assertNotEmpty($storyShowOperation['security'] ?? []);
    }
```

- [ ] **Step 2: Eseguire i test e verificare che falliscano**

```bash
docker exec php81_orchestrator php artisan test --filter=ApiDocsTest
```

Expected: FAIL su `test_mutating_operations_have_no_security_requirement_in_spec` (la security è ancora presente su tutte le operazioni).

- [ ] **Step 3: Implementare il transformer**

In `app/Providers/AppServiceProvider.php`, nel metodo `boot()`, aggiungi (adatta i nomi di classe/metodo al risultato dello Step 1 di questo task se differiscono):

```php
use Dedoc\Scramble\Scramble;

Scramble::afterOpenApiGenerated(function (\Dedoc\Scramble\Support\Generator\OpenApi $openApi) {
    foreach ($openApi->paths as $path) {
        foreach ($path->operations as $operation) {
            if (strtoupper($operation->method) !== 'GET') {
                $operation->security = [];
            }
        }
    }
});
```

- [ ] **Step 4: Eseguire i test e verificare che passino**

```bash
docker exec php81_orchestrator php artisan test --filter=ApiDocsTest
```

Expected: PASS su tutti i test in `ApiDocsTest`. Se la struttura `$openApi->paths` / `$path->operations` non corrisponde a quella reale del package (nomi di proprietà diversi tra versioni), adatta il codice del transformer secondo l'output di `var_dump`/`dd()` temporaneo su `$openApi` durante il debug, poi rimuovi il debug prima di procedere.

- [ ] **Step 5: Commit**

```bash
git add app/Providers/AppServiceProvider.php tests/Feature/Api/ApiDocsTest.php
git commit -m "feat(oc:8287): restrict try-it-out execution to GET operations only"
```

---

### Task 3: Aggiungere il link alla documentazione nel menu Nova

**Files:**
- Modify: `app/Providers/NovaServiceProvider.php`

**Interfaces:**
- Consumes: route `/docs/api` prodotta da Task 1
- Produces: nessuna interfaccia consumata da altri task

- [ ] **Step 1: Aggiungere la voce di menu**

In `app/Providers/NovaServiceProvider.php`, dentro l'array ritornato da `Nova::mainMenu`, subito dopo le voci `MEET`/`SCRUM` esistenti (circa riga 68), aggiungi:

```php
                MenuItem::externalLink('API Docs', '/docs/api')->openInNewTab(),
```

Nessun `canSee()` — la route è pubblica per scelta esplicita, quindi la voce di menu è visibile a tutti gli utenti Nova autenticati (che sono comunque l'unica audience che naviga il menu Nova).

- [ ] **Step 2: Verificare manualmente la voce di menu**

```bash
docker exec php81_orchestrator php artisan config:clear
```

Poi apri Nova nel browser e verifica che la voce "API Docs" compaia nel menu principale e apra `/docs/api` in una nuova tab.

Expected: voce visibile, link funzionante, nessun errore Nova in console.

- [ ] **Step 3: Commit**

```bash
git add app/Providers/NovaServiceProvider.php
git commit -m "feat(oc:8287): add API Docs link to Nova main menu"
```

---

### Task 4: Annotazioni PHPDoc @response per le risposte non inferibili — StoryController e AuthController

**Files:**
- Modify: `app/Http/Controllers/Api/StoryController.php`
- Modify: `app/Http/Controllers/Api/AuthController.php`

**Interfaces:**
- Consumes: route `GET /docs/api.json` prodotta da Task 1
- Produces: nessuna interfaccia consumata da altri task

- [ ] **Step 1: Annotare `StoryController::show`**

In `app/Http/Controllers/Api/StoryController.php`, sopra il metodo `show`:

```php
    /**
     * Retrieve a story by ID.
     *
     * @response array{id: int, name: string, status: string, type: string, description: string|null, customer_request: string|null, user_id: int|null, tester_id: int|null, creator_id: int|null, parent_id: int|null, estimated_hours: float|null, hours: float|null, tags: array<array{id: int, name: string}>, created_at: string|null, updated_at: string|null}
     */
    public function show(Story $story): JsonResponse
```

- [ ] **Step 2: Annotare `StoryController::store` e `StoryController::update`**

Sopra `store`:

```php
    /**
     * Create a new story.
     *
     * @response 201 array{id: int, name: string, status: string, type: string, description: string|null, customer_request: string|null, user_id: int|null, tester_id: int|null, creator_id: int|null, parent_id: int|null, estimated_hours: float|null, hours: float|null, tags: array<array{id: int, name: string}>, created_at: string|null, updated_at: string|null}
     */
    public function store(StoryApiRequest $request): JsonResponse
```

Sopra `update`, stesso blocco `@response` di `show` (senza status code, default 200) con summary `Update an existing story.`.

- [ ] **Step 3: Annotare `AuthController::login`**

In `app/Http/Controllers/Api/AuthController.php`, sopra `login`:

```php
    /**
     * Authenticate a user and issue a Sanctum bearer token.
     *
     * @response array{token: string, user: array{id: int, name: string, email: string}}
     * @response 401 array{message: string}
     */
    public function login(Request $request): JsonResponse
```

- [ ] **Step 4: Verificare che la spec includa gli esempi**

```bash
docker exec php81_orchestrator php artisan test --filter=ApiDocsTest
```

Expected: i test esistenti continuano a passare (nessuna regressione). Poi, manualmente:

```bash
curl -s http://localhost:8099/docs/api.json | jq '.paths["/api/stories/{story}"].get.responses["200"].content'
```

Expected: lo schema di risposta non è più vuoto/generico ma riflette i campi annotati.

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/Api/StoryController.php app/Http/Controllers/Api/AuthController.php
git commit -m "feat(oc:8287): add PHPDoc response annotations to Story and Auth controllers"
```

---

### Task 5: Annotazioni PHPDoc @response — Tag, Quote, Product, RecurringProduct controller

**Files:**
- Modify: `app/Http/Controllers/Api/TagController.php`
- Modify: `app/Http/Controllers/Api/QuoteController.php`
- Modify: `app/Http/Controllers/Api/ProductController.php`
- Modify: `app/Http/Controllers/Api/RecurringProductController.php`

**Interfaces:**
- Consumes: route `GET /docs/api.json` prodotta da Task 1
- Produces: nessuna interfaccia consumata da altri task

- [ ] **Step 1: Annotare `TagController::index`, `show`, `store`, `update`**

Sopra `index`:

```php
    /**
     * List tags, optionally filtered by name.
     *
     * @response array<array{id: int, name: string, description: string|null}>
     */
    public function index(Request $request): JsonResponse
```

Sopra `show`:

```php
    /**
     * Retrieve a tag with its attached stories.
     *
     * @response array{id: int, name: string, description: string|null, stories: array<array{id: int, name: string, status: string, customer_request: string|null, description: string|null}>}
     */
    public function show(Request $request, Tag $tag): JsonResponse
```

Sopra `store` e `update`, riusa lo schema di `index` senza il campo `stories` (`array{id: int, name: string, description: string|null}`), con summary rispettivamente `Create a new tag.` (`@response 201 ...`) e `Update an existing tag.`.

- [ ] **Step 2: Annotare `QuoteController::index`, `show`, `store`, `update`, e i metodi attach/detach**

Sopra `index`:

```php
    /**
     * List quotes, optionally filtered by customer or status.
     *
     * @response array<array{id: int, title: string, status: string, priority: string, customer_id: int, google_drive_url: string|null, discount: float|null, notes: string|null, additional_services: string|null, template: string|null, total: float, net_total: float}>
     */
    public function index(Request $request): JsonResponse
```

Sopra `show`, `store` (`@response 201`), `update`, `attachProduct`, `detachProduct`, `attachRecurringProduct`, `detachRecurringProduct`: stesso schema oggetto singolo (`array{id: int, title: string, status: string, priority: string, customer_id: int, google_drive_url: string|null, discount: float|null, notes: string|null, additional_services: string|null, template: string|null, total: float, net_total: float}`), con summary descrittivo per ciascun metodo (es. `Retrieve a quote.`, `Create a new quote.`, `Attach a product to a quote with a quantity.`).

Sopra `destroy`:

```php
    /**
     * Delete a quote.
     *
     * @response array{message: string}
     */
    public function destroy(Request $request, Quote $quote): JsonResponse
```

- [ ] **Step 3: Annotare `ProductController::index` e `RecurringProductController::index`**

In entrambi i file, sopra `index`:

```php
    /**
     * List all products.
     *
     * @response array<array{id: int, name: string, description: string|null, sku: string, price: float}>
     */
    public function index(Request $request): JsonResponse
```

(per `RecurringProductController` cambia solo il summary in `List all recurring products.`)

- [ ] **Step 4: Verificare la generazione della spec senza errori**

```bash
docker exec php81_orchestrator php artisan test --filter=ApiDocsTest
```

Expected: PASS su tutti i test — nessuna eccezione di parsing PHPDoc durante la generazione della spec (Scramble fallisce silenziosamente in JSON malformato se una annotazione `@response` non è valida; un test che fallisce con errore 500 sulla route `/docs/api.json` indica una sintassi PHPDoc non valida da correggere).

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/Api/TagController.php app/Http/Controllers/Api/QuoteController.php app/Http/Controllers/Api/ProductController.php app/Http/Controllers/Api/RecurringProductController.php
git commit -m "feat(oc:8287): add PHPDoc response annotations to Tag, Quote, Product controllers"
```

---

### Task 6: Verifica manuale finale della documentazione

**Files:**
- Nessuna modifica — solo verifica

**Interfaces:**
- Consumes: tutto quanto prodotto nei Task 1-5
- Produces: nessuna — task di chiusura

- [ ] **Step 1: Verificare i gruppi di endpoint nella UI**

Apri `http://localhost:8099/docs/api` nel browser e verifica che siano presenti gruppi/tag distinti per: `AppController` (Public — `/app/{id}/config.json`), `AuthController` (`/auth/login`), `StoryController`, `TagController`, `QuoteController`, `ProductController`, `RecurringProductController`. Se la route inline `/me` risulta senza gruppo o in un gruppo generico "Default", è un comportamento atteso di Scramble per le route con closure invece di controller — annotalo in `notes.md` come nota informativa, non un difetto da correggere in questo ciclo.

- [ ] **Step 2: Verificare il pulsante Authorize**

Clicca "Authorize" nella UI, inserisci un Bearer token valido (ottenuto da `POST /auth/login` con un utente reale del DB di test/locale), e verifica che una richiesta GET autenticata (es. `GET /api/stories/{id}` con un ID esistente) esegua con successo e mostri il risultato reale.

- [ ] **Step 3: Verificare che le operazioni mutanti non siano eseguibili con un click**

Prova a espandere `POST /api/stories` o `PATCH /api/quotes/{quote}` nella UI e verifica che il pannello "Try it out" non proponga automaticamente l'header Authorization popolato (coerente con `security: []` impostato in Task 2) — l'utente dovrebbe dover aggiungere manualmente l'header per eseguire la richiesta, a differenza delle operazioni GET.

- [ ] **Step 4: Verificare la lingua**

Scorri titoli, summary e descrizioni nella UI e conferma che siano tutti in inglese.

- [ ] **Step 5: Eseguire l'intera suite di test API per escludere regressioni**

```bash
docker exec php81_orchestrator php artisan test --filter=Api
```

Expected: PASS su tutti i test esistenti in `tests/Feature/Api/` (inclusi quelli pre-esistenti per Story, Tag, Quote, Product, Me) oltre ai nuovi test di `ApiDocsTest`.

- [ ] **Step 6: Nessun commit in questo task**

Questo task è solo verifica manuale — non produce modifiche al codice.
