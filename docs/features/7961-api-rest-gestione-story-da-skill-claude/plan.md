> Ticket: oc:7961

# API REST per gestione Story da skill Claude — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Esporre endpoint REST autenticati su Orchestrator per permettere alle skill Claude di leggere, creare e aggiornare Story via Bearer token Sanctum.

**Architecture:** Tre controller sotto `app/Http/Controllers/Api/`: `AuthController` per login/token, `StoryController` per CRUD Story. Un Form Request `StoryApiRequest` centralizza la validazione. Le route sono nel gruppo `api` esistente con middleware `auth:sanctum`. Gli observer Eloquent del modello (`saving`/`saved`) gestiscono i side effect (auto-tag, notifiche) senza logica duplicata nel controller.

**Tech Stack:** Laravel 10, Laravel Sanctum, PHP 8.1, PostgreSQL, PHPUnit

---

## File Structure

| File | Azione | Responsabilità |
|------|--------|----------------|
| `routes/api.php` | Modifica | Aggiunge route auth + story |
| `app/Http/Controllers/Api/AuthController.php` | Crea | Login → token Sanctum |
| `app/Http/Controllers/Api/StoryController.php` | Crea | GET / POST / PATCH Story |
| `app/Http/Requests/Api/StoryApiRequest.php` | Crea | Validazione campi con enum |
| `tests/Feature/Api/AuthApiTest.php` | Crea | Test endpoint login |
| `tests/Feature/Api/StoryApiTest.php` | Crea | Test CRUD Story via API |

---

### Task 1: AuthController — login e token Sanctum

**Files:**
- Create: `app/Http/Controllers/Api/AuthController.php`
- Create: `tests/Feature/Api/AuthApiTest.php`
- Modify: `routes/api.php`

- [ ] **Step 1: Crea la directory e scrivi il test fallente**

```bash
mkdir -p tests/Feature/Api
```

```php
<?php
// tests/Feature/Api/AuthApiTest.php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthApiTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function login_con_credenziali_valide_restituisce_token(): void
    {
        $user = User::factory()->create([
            'email' => 'dev@webmapp.it',
            'password' => bcrypt('password'),
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'dev@webmapp.it',
            'password' => 'password',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['token', 'user' => ['id', 'name', 'email']]);
    }

    /** @test */
    public function login_con_credenziali_errate_restituisce_401(): void
    {
        User::factory()->create(['email' => 'dev@webmapp.it']);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'dev@webmapp.it',
            'password' => 'wrong',
        ]);

        $response->assertStatus(401)
            ->assertJson(['message' => 'Invalid credentials']);
    }

    /** @test */
    public function login_con_campi_mancanti_restituisce_422(): void
    {
        $response = $this->postJson('/api/auth/login', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email', 'password']);
    }
}
```

- [ ] **Step 2: Esegui il test per verificare che fallisca**

```bash
docker exec php81_orchestrator php artisan test --filter=AuthApiTest
```

Expected: FAIL — `Route [api/auth/login] not found`

- [ ] **Step 3: Crea AuthController**

```php
<?php
// app/Http/Controllers/Api/AuthController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (!Auth::attempt($request->only('email', 'password'))) {
            return response()->json(['message' => 'Invalid credentials'], 401);
        }

        $user = Auth::user();
        $token = $user->createToken('orchestrator-skill')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
        ]);
    }
}
```

- [ ] **Step 4: Aggiungi le route in `routes/api.php`**

```php
<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AppController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\StoryController;

Route::prefix('app')->name('app.')->group(function () {
    Route::get("/{id}/config.json", [AppController::class, 'config'])->name('config');
});

Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
});

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/stories/{story}', [StoryController::class, 'show']);
    Route::post('/stories', [StoryController::class, 'store']);
    Route::patch('/stories/{story}', [StoryController::class, 'update']);
});
```

- [ ] **Step 5: Esegui i test per verificare che passino**

```bash
docker exec php81_orchestrator php artisan test --filter=AuthApiTest
```

Expected: PASS — 3 test verdi

- [ ] **Step 6: Commit**

```
feat(oc:7961): add AuthController with Sanctum token login endpoint
```

---

### Task 2: StoryApiRequest — validazione campi

**Files:**
- Create: `app/Http/Requests/Api/StoryApiRequest.php`

- [ ] **Step 1: Crea la directory e il Form Request**

```bash
mkdir -p app/Http/Requests/Api
```

```php
<?php
// app/Http/Requests/Api/StoryApiRequest.php

namespace App\Http\Requests\Api;

use App\Enums\StoryStatus;
use App\Enums\StoryType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoryApiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $isCreate = $this->isMethod('POST');

        return [
            'name'             => [$isCreate ? 'required' : 'sometimes', 'string', 'max:255'],
            'description'      => ['sometimes', 'nullable', 'string'],
            'customer_request' => ['sometimes', 'nullable', 'string'],
            'answer_to_ticket' => ['sometimes', 'nullable', 'string'],
            'type'             => ['sometimes', 'nullable', Rule::enum(StoryType::class)],
            'status'           => ['sometimes', 'nullable', Rule::enum(StoryStatus::class)],
            'user_id'          => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
            'tester_id'        => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
            'creator_id'       => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
            'parent_id'        => ['sometimes', 'nullable', 'integer', 'exists:stories,id'],
            'estimated_hours'  => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'tags'             => ['sometimes', 'nullable', 'array'],
            'tags.*'           => ['integer', 'exists:tags,id'],
        ];
    }
}
```

- [ ] **Step 2: Commit**

```
feat(oc:7961): add StoryApiRequest with enum validation for status and type
```

---

### Task 3: StoryController — GET show

**Files:**
- Create: `app/Http/Controllers/Api/StoryController.php`
- Create: `tests/Feature/Api/StoryApiTest.php`

- [ ] **Step 1: Scrivi il test fallente per GET /api/stories/{id}**

```php
<?php
// tests/Feature/Api/StoryApiTest.php

namespace Tests\Feature\Api;

use App\Enums\StoryStatus;
use App\Enums\StoryType;
use App\Models\Story;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StoryApiTest extends TestCase
{
    use RefreshDatabase;

    private User $developer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->developer = User::factory()->create(['is_developer' => true]);
    }

    /** @test */
    public function get_story_autenticato_restituisce_campi_corretti(): void
    {
        Sanctum::actingAs($this->developer);

        $story = Story::factory()->create([
            'name'   => 'Test story',
            'status' => StoryStatus::New->value,
            'type'   => StoryType::Feature->value,
        ]);

        $response = $this->getJson("/api/stories/{$story->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'id', 'name', 'status', 'type', 'description',
                'customer_request', 'answer_to_ticket',
                'user_id', 'tester_id', 'creator_id', 'parent_id',
                'estimated_hours', 'effective_hours',
                'tags', 'created_at', 'updated_at',
            ])
            ->assertJsonFragment(['name' => 'Test story']);
    }

    /** @test */
    public function get_story_senza_autenticazione_restituisce_401(): void
    {
        $story = Story::factory()->create();

        $response = $this->getJson("/api/stories/{$story->id}");

        $response->assertStatus(401);
    }

    /** @test */
    public function get_story_non_esistente_restituisce_404(): void
    {
        Sanctum::actingAs($this->developer);

        $response = $this->getJson('/api/stories/99999');

        $response->assertStatus(404);
    }
}
```

- [ ] **Step 2: Esegui il test per verificare che fallisca**

```bash
docker exec php81_orchestrator php artisan test --filter=StoryApiTest::get_story
```

Expected: FAIL — `StoryController not found`

- [ ] **Step 3: Crea StoryController con metodo show**

```php
<?php
// app/Http/Controllers/Api/StoryController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoryApiRequest;
use App\Models\Story;
use Illuminate\Http\JsonResponse;

class StoryController extends Controller
{
    public function show(Story $story): JsonResponse
    {
        $story->load('tags');

        return response()->json([
            'id'               => $story->id,
            'name'             => $story->name,
            'status'           => $story->status,
            'type'             => $story->type,
            'description'      => $story->description,
            'customer_request' => $story->customer_request,
            'answer_to_ticket' => $story->answer_to_ticket,
            'user_id'          => $story->user_id,
            'tester_id'        => $story->tester_id,
            'creator_id'       => $story->creator_id,
            'parent_id'        => $story->parent_id,
            'estimated_hours'  => $story->estimated_hours,
            'effective_hours'  => $story->effective_hours,
            'tags'             => $story->tags->map(fn($t) => ['id' => $t->id, 'name' => $t->name]),
            'created_at'       => $story->created_at?->toIso8601String(),
            'updated_at'       => $story->updated_at?->toIso8601String(),
        ]);
    }

    public function store(StoryApiRequest $request): JsonResponse
    {
        // implementato in Task 4
    }

    public function update(StoryApiRequest $request, Story $story): JsonResponse
    {
        // implementato in Task 5
    }
}
```

- [ ] **Step 4: Esegui i test GET per verificare che passino**

```bash
docker exec php81_orchestrator php artisan test --filter="StoryApiTest::get_story"
```

Expected: PASS

- [ ] **Step 5: Commit**

```
feat(oc:7961): add StoryController show endpoint GET /api/stories/{id}
```

---

### Task 4: StoryController — POST store

**Files:**
- Modify: `app/Http/Controllers/Api/StoryController.php`
- Modify: `tests/Feature/Api/StoryApiTest.php`

- [ ] **Step 1: Aggiungi i test fallenti per POST /api/stories**

Aggiungi questi metodi alla classe `StoryApiTest`:

```php
/** @test */
public function crea_story_con_campi_validi_restituisce_201(): void
{
    Sanctum::actingAs($this->developer);

    $response = $this->postJson('/api/stories', [
        'name'        => 'Nuova feature via API',
        'type'        => StoryType::Feature->value,
        'description' => 'Note tecniche della feature',
        'status'      => StoryStatus::New->value,
    ]);

    $response->assertStatus(201)
        ->assertJsonFragment(['name' => 'Nuova feature via API']);

    $this->assertDatabaseHas('stories', ['name' => 'Nuova feature via API']);
}

/** @test */
public function crea_story_senza_name_restituisce_422(): void
{
    Sanctum::actingAs($this->developer);

    $response = $this->postJson('/api/stories', [
        'type' => StoryType::Feature->value,
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['name']);
}

/** @test */
public function crea_story_con_status_non_valido_restituisce_422(): void
{
    Sanctum::actingAs($this->developer);

    $response = $this->postJson('/api/stories', [
        'name'   => 'Test',
        'status' => 'invalid_status',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['status']);
}
```

- [ ] **Step 2: Esegui i test per verificare che falliscano**

```bash
docker exec php81_orchestrator php artisan test --filter="StoryApiTest::crea_story"
```

Expected: FAIL — store returns null

- [ ] **Step 3: Implementa il metodo store in StoryController**

Sostituisci il metodo `store` nel controller:

```php
public function store(StoryApiRequest $request): JsonResponse
{
    $validated = $request->validated();
    $tags = $validated['tags'] ?? null;
    unset($validated['tags']);

    $story = new Story();
    $story->fill(array_filter($validated, fn($v) => $v !== null));

    // campi non in $fillable
    foreach (['customer_request', 'answer_to_ticket', 'estimated_hours'] as $field) {
        if (isset($validated[$field])) {
            $story->{$field} = $validated[$field];
        }
    }

    $story->save();

    if ($tags !== null) {
        $story->tags()->sync($tags);
    }

    $story->load('tags');

    return response()->json($this->formatStory($story), 201);
}
```

Aggiungi il metodo privato `formatStory` per evitare duplicazione:

```php
private function formatStory(Story $story): array
{
    return [
        'id'               => $story->id,
        'name'             => $story->name,
        'status'           => $story->status,
        'type'             => $story->type,
        'description'      => $story->description,
        'customer_request' => $story->customer_request,
        'answer_to_ticket' => $story->answer_to_ticket,
        'user_id'          => $story->user_id,
        'tester_id'        => $story->tester_id,
        'creator_id'       => $story->creator_id,
        'parent_id'        => $story->parent_id,
        'estimated_hours'  => $story->estimated_hours,
        'effective_hours'  => $story->effective_hours,
        'tags'             => $story->tags->map(fn($t) => ['id' => $t->id, 'name' => $t->name]),
        'created_at'       => $story->created_at?->toIso8601String(),
        'updated_at'       => $story->updated_at?->toIso8601String(),
    ];
}
```

Aggiorna anche il metodo `show` per usare `formatStory`:

```php
public function show(Story $story): JsonResponse
{
    $story->load('tags');
    return response()->json($this->formatStory($story));
}
```

- [ ] **Step 4: Esegui i test store per verificare che passino**

```bash
docker exec php81_orchestrator php artisan test --filter="StoryApiTest::crea_story"
```

Expected: PASS

- [ ] **Step 5: Commit**

```
feat(oc:7961): add StoryController store endpoint POST /api/stories
```

---

### Task 5: StoryController — PATCH update

**Files:**
- Modify: `app/Http/Controllers/Api/StoryController.php`
- Modify: `tests/Feature/Api/StoryApiTest.php`

- [ ] **Step 1: Aggiungi i test fallenti per PATCH /api/stories/{id}**

Aggiungi questi metodi alla classe `StoryApiTest`:

```php
/** @test */
public function aggiorna_story_con_campi_validi(): void
{
    Sanctum::actingAs($this->developer);

    $story = Story::factory()->create(['name' => 'Vecchio nome']);

    $response = $this->patchJson("/api/stories/{$story->id}", [
        'name'        => 'Nuovo nome',
        'description' => 'Note aggiornate',
    ]);

    $response->assertStatus(200)
        ->assertJsonFragment(['name' => 'Nuovo nome']);

    $this->assertDatabaseHas('stories', [
        'id'   => $story->id,
        'name' => 'Nuovo nome',
    ]);
}

/** @test */
public function aggiorna_story_non_tocca_campi_non_passati(): void
{
    Sanctum::actingAs($this->developer);

    $story = Story::factory()->create([
        'name'   => 'Nome originale',
        'status' => StoryStatus::New->value,
    ]);

    $this->patchJson("/api/stories/{$story->id}", [
        'description' => 'Solo descrizione aggiornata',
    ]);

    $this->assertDatabaseHas('stories', [
        'id'   => $story->id,
        'name' => 'Nome originale',
    ]);
}

/** @test */
public function aggiorna_story_con_type_non_valido_restituisce_422(): void
{
    Sanctum::actingAs($this->developer);

    $story = Story::factory()->create();

    $response = $this->patchJson("/api/stories/{$story->id}", [
        'type' => 'InvalidType',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['type']);
}
```

- [ ] **Step 2: Esegui i test per verificare che falliscano**

```bash
docker exec php81_orchestrator php artisan test --filter="StoryApiTest::aggiorna_story"
```

Expected: FAIL — update returns null

- [ ] **Step 3: Implementa il metodo update in StoryController**

Sostituisci il metodo `update` nel controller:

```php
public function update(StoryApiRequest $request, Story $story): JsonResponse
{
    $validated = $request->validated();
    $tags = $validated['tags'] ?? null;
    unset($validated['tags']);

    $fillable = array_intersect_key(
        $validated,
        array_flip(['name', 'status', 'description', 'type', 'user_id', 'tester_id', 'creator_id', 'parent_id'])
    );

    if (!empty($fillable)) {
        $story->fill($fillable);
    }

    foreach (['customer_request', 'answer_to_ticket', 'estimated_hours'] as $field) {
        if (array_key_exists($field, $validated)) {
            $story->{$field} = $validated[$field];
        }
    }

    $story->save();

    if ($tags !== null) {
        $story->tags()->sync($tags);
    }

    $story->load('tags');

    return response()->json($this->formatStory($story));
}
```

- [ ] **Step 4: Esegui tutti i test dell'API per verificare che passino**

```bash
docker exec php81_orchestrator php artisan test --filter=StoryApiTest
```

Expected: PASS — tutti i test verdi

- [ ] **Step 5: Esegui la suite completa per verificare no regressioni**

```bash
docker exec php81_orchestrator php artisan test
```

Expected: suite verde (nessuna regressione)

- [ ] **Step 6: Commit**

```
feat(oc:7961): add StoryController update endpoint PATCH /api/stories/{id}
```

---

## Self-Review

**Spec coverage:**
- ✅ `POST /api/auth/login` → Task 1
- ✅ `GET /api/stories/{id}` → Task 3
- ✅ `POST /api/stories` → Task 4
- ✅ `PATCH /api/stories/{id}` → Task 5
- ✅ Autenticazione Bearer Sanctum → Task 1 + route middleware
- ✅ Form Request con validazione enum → Task 2
- ✅ Campi allineati a edit form developer → tutti i task
- ✅ Side effects via observer Eloquent → nessun codice aggiuntivo necessario

**Note:** Il token persistito in `~/.config/webmapp/orchestrator-token` è responsabilità della skill Claude (claude-marketplace), non di questo piano.
