> Ticket: oc:8155

# API CRUD per Tag con attach/detach stories — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Esporre 6 endpoint REST autenticati per il modello Tag (list, show, store, update, attach story, detach story) seguendo il pattern già usato da `StoryController`.

**Architecture:** Un `TagController` con 6 metodi, un `TagApiRequest` per la validazione, route aggiunte al gruppo `auth:sanctum` esistente in `routes/api.php`. L'autorizzazione per ruolo (Developer/Admin) è centralizzata in un metodo privato del controller. L'attach/detach usa la relazione `tagged()` (morphedByMany) del modello Tag sulla pivot `taggables`.

**Tech Stack:** Laravel 10, Sanctum, PostgreSQL, PHPUnit (DatabaseTransactions), TagFactory già presente.

## Global Constraints

- Tutti i comandi PHP girano dentro il container Docker: `docker exec php81_orchestrator <comando>`
- DB di test: `orchestrator_test` (configurato in `phpunit.xml`) — mai usare `DB_DATABASE=orchestrator`
- Commit convention: `feat(oc:8155): ...`
- NO commit automatici — i commit nel piano sono istruzioni testuali per il developer
- Il campo `type` NON esiste nella colonna DB `tags` — non va mai incluso
- L'attach/detach usa SOLO `$tag->tagged()->...` (morphedByMany), MAI `$tag->taggable()` (morphTo)
- Sanitize LIKE: `str_replace(['%', '_'], ['\%', '\_'], $search)` prima di ogni query LIKE
- Autorizzazione: `abort_unless($user->hasRole(UserRole::Developer) || $user->isAdmin(), 403)`

---

### Task 1: TagApiRequest

**Files:**
- Create: `app/Http/Requests/Api/TagApiRequest.php`
- Test: `tests/Feature/Api/TagApiTest.php` (solo i test di validazione in questo task)

**Interfaces:**
- Produce: `TagApiRequest` con `rules()` usato da `TagController` nei task successivi

- [ ] **Step 1: Crea il file di test con i test di validazione**

```php
<?php

namespace Tests\Feature\Api;

use App\Enums\UserRole;
use App\Models\Tag;
use App\Models\Story;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TagApiTest extends TestCase
{
    use DatabaseTransactions;

    private function actingAsDeveloper(): User
    {
        $user = User::factory()->create(['roles' => [UserRole::Developer]]);
        Sanctum::actingAs($user);
        return $user;
    }

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
    public function store_richiede_name(): void
    {
        $this->actingAsDeveloper();

        $this->postJson('/api/tags', [])->assertStatus(422)->assertJsonValidationErrors(['name']);
    }

    /** @test */
    public function store_accetta_solo_name_e_description(): void
    {
        $this->actingAsDeveloper();

        $this->postJson('/api/tags', ['name' => 'Test Tag', 'description' => 'Desc'])
            ->assertStatus(201);
    }

    /** @test */
    public function update_non_richiede_name(): void
    {
        $this->actingAsDeveloper();
        $tag = Tag::factory()->create();

        $this->patchJson("/api/tags/{$tag->id}", ['description' => 'updated'])
            ->assertStatus(200);
    }
}
```

- [ ] **Step 2: Esegui i test per verificare che falliscano**

```bash
docker exec php81_orchestrator php artisan test --filter=TagApiTest
```

Atteso: FAIL (route non esistono → 404)

- [ ] **Step 3: Crea `TagApiRequest`**

```php
<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class TagApiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $isCreate = $this->isMethod('POST');

        return [
            'name'        => [$isCreate ? 'required' : 'sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
        ];
    }
}
```

- [ ] **Step 4: Commit**

```
feat(oc:8155): add TagApiRequest with name/description validation
```

---

### Task 2: TagController — index e show

**Files:**
- Create: `app/Http/Controllers/Api/TagController.php`
- Modify: `routes/api.php`
- Test: `tests/Feature/Api/TagApiTest.php`

**Interfaces:**
- Consumes: `TagApiRequest` (Task 1)
- Produce: `TagController` con metodi `index()` e `show()`; route `GET /api/tags` e `GET /api/tags/{tag}`

- [ ] **Step 1: Aggiungi i test per index e show**

Nel file `tests/Feature/Api/TagApiTest.php`, aggiungi dopo i test esistenti:

```php
/** @test */
public function index_restituisce_lista_tag(): void
{
    $this->actingAsDeveloper();
    Tag::factory()->count(3)->create();

    $response = $this->getJson('/api/tags')->assertStatus(200);

    $this->assertCount(3, $response->json());
    $response->assertJsonStructure([['id', 'name', 'description']]);
}

/** @test */
public function index_filtra_per_nome(): void
{
    $this->actingAsDeveloper();
    Tag::factory()->create(['name' => 'Alpha tag']);
    Tag::factory()->create(['name' => 'Beta tag']);

    $response = $this->getJson('/api/tags?search=alpha')->assertStatus(200);

    $this->assertCount(1, $response->json());
    $this->assertEquals('Alpha tag', $response->json()[0]['name']);
}

/** @test */
public function index_search_non_è_vulnerabile_a_like_injection(): void
{
    $this->actingAsDeveloper();
    Tag::factory()->count(5)->create();

    $response = $this->getJson('/api/tags?search=%')->assertStatus(200);

    // % non sanitizzato restituirebbe tutti i tag; sanitizzato restituisce 0 match
    $this->assertCount(0, $response->json());
}

/** @test */
public function show_restituisce_tag_con_stories(): void
{
    $this->actingAsDeveloper();
    $tag = Tag::factory()->create();
    $story = Story::factory()->create();
    $tag->tagged()->attach($story->id);

    $response = $this->getJson("/api/tags/{$tag->id}")->assertStatus(200);

    $response->assertJsonStructure(['id', 'name', 'description', 'stories']);
    $this->assertCount(1, $response->json('stories'));
    $response->assertJsonPath('stories.0.id', $story->id);
    $response->assertJsonPath('stories.0.name', $story->name);
    $response->assertJsonPath('stories.0.status', $story->status);
    $this->assertArrayHasKey('customer_request', $response->json('stories.0'));
    $this->assertArrayHasKey('description', $response->json('stories.0'));
}

/** @test */
public function show_restituisce_404_per_tag_inesistente(): void
{
    $this->actingAsDeveloper();

    $this->getJson('/api/tags/99999')->assertStatus(404);
}

/** @test */
public function customer_non_puo_accedere_alle_api_tag(): void
{
    $this->actingAsCustomer();

    $this->getJson('/api/tags')->assertStatus(403);
}

/** @test */
public function admin_puo_accedere_alle_api_tag(): void
{
    $this->actingAsAdmin();

    $this->getJson('/api/tags')->assertStatus(200);
}

/** @test */
public function utente_non_autenticato_ottiene_401(): void
{
    $this->getJson('/api/tags')->assertStatus(401);
}
```

- [ ] **Step 2: Esegui i test per verificare che falliscano**

```bash
docker exec php81_orchestrator php artisan test --filter=TagApiTest
```

Atteso: FAIL (route non esistono → 404)

- [ ] **Step 3: Crea `TagController` con `index()` e `show()`**

```php
<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\TagApiRequest;
use App\Models\StoryLog;
use App\Models\Tag;
use App\Models\Story;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TagController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorizeRole($request);

        $query = Tag::query();

        if ($request->filled('search')) {
            $search = str_replace(['%', '_'], ['\%', '\_'], $request->search);
            $query->whereRaw('LOWER(name) LIKE ?', ['%' . strtolower($search) . '%']);
        }

        $tags = $query->get();

        return response()->json($tags->map(fn($t) => $this->formatTag($t)));
    }

    public function show(Request $request, Tag $tag): JsonResponse
    {
        $this->authorizeRole($request);

        $tag->load('tagged');

        return response()->json($this->formatTag($tag, withStories: true));
    }

    private function authorizeRole(Request $request): void
    {
        $user = $request->user();
        abort_unless(
            $user->hasRole(UserRole::Developer) || $user->isAdmin(),
            403
        );
    }

    private function formatTag(Tag $tag, bool $withStories = false): array
    {
        $data = [
            'id'          => $tag->id,
            'name'        => $tag->name,
            'description' => $tag->description,
        ];

        if ($withStories) {
            $data['stories'] = $tag->tagged->map(fn($s) => [
                'id'               => $s->id,
                'name'             => $s->name,
                'status'           => $s->status,
                'customer_request' => $s->customer_request,
                'description'      => $s->description,
            ])->values();
        }

        return $data;
    }
}
```

- [ ] **Step 4: Aggiungi le route a `routes/api.php`**

All'interno del gruppo `Route::middleware('auth:sanctum')->group(...)`, aggiungi prima della chiusura `})`:

```php
Route::get('/tags', [TagController::class, 'index']);
Route::get('/tags/{tag}', [TagController::class, 'show']);
```

Aggiungi l'import in testa al file:
```php
use App\Http\Controllers\Api\TagController;
```

- [ ] **Step 5: Esegui i test**

```bash
docker exec php81_orchestrator php artisan test --filter=TagApiTest
```

Atteso: i test di index e show passano; quelli di store/update/attach/detach falliscono ancora (metodi non implementati).

- [ ] **Step 6: Commit**

```
feat(oc:8155): add TagController index and show endpoints
```

---

### Task 3: TagController — store e update

**Files:**
- Modify: `app/Http/Controllers/Api/TagController.php`
- Test: `tests/Feature/Api/TagApiTest.php`

**Interfaces:**
- Consumes: `TagApiRequest` (Task 1), `TagController` (Task 2)
- Produce: metodi `store()` e `update()` nel `TagController`; route `POST /api/tags` e `PATCH /api/tags/{tag}`

- [ ] **Step 1: Aggiungi i test per store e update**

Nel file `tests/Feature/Api/TagApiTest.php`, aggiungi:

```php
/** @test */
public function store_crea_tag_globale(): void
{
    $this->actingAsDeveloper();

    $response = $this->postJson('/api/tags', [
        'name'        => 'Nuovo tag',
        'description' => 'Una descrizione',
    ])->assertStatus(201);

    $response->assertJsonStructure(['id', 'name', 'description']);
    $this->assertEquals('Nuovo tag', $response->json('name'));
    $this->assertDatabaseHas('tags', ['name' => 'Nuovo tag', 'taggable_type' => null, 'taggable_id' => null]);
}

/** @test */
public function store_non_accetta_taggable_type_o_id(): void
{
    $this->actingAsDeveloper();

    $response = $this->postJson('/api/tags', [
        'name'          => 'Tag con parent',
        'taggable_type' => 'App\Models\Project',
        'taggable_id'   => 1,
    ])->assertStatus(201);

    $this->assertNull($response->json('taggable_type'));
    $this->assertDatabaseMissing('tags', ['name' => 'Tag con parent', 'taggable_type' => 'App\Models\Project']);
}

/** @test */
public function update_aggiorna_name_e_description(): void
{
    $this->actingAsDeveloper();
    $tag = Tag::factory()->create(['name' => 'Vecchio nome']);

    $response = $this->patchJson("/api/tags/{$tag->id}", [
        'name'        => 'Nuovo nome',
        'description' => 'Nuova desc',
    ])->assertStatus(200);

    $this->assertEquals('Nuovo nome', $response->json('name'));
    $this->assertDatabaseHas('tags', ['id' => $tag->id, 'name' => 'Nuovo nome']);
}
```

- [ ] **Step 2: Esegui i test per verificare che falliscano**

```bash
docker exec php81_orchestrator php artisan test --filter=TagApiTest
```

Atteso: FAIL (metodi e route non esistono)

- [ ] **Step 3: Aggiungi `store()` e `update()` in `TagController`**

Aggiungi questi metodi alla classe `TagController`:

```php
public function store(TagApiRequest $request): JsonResponse
{
    $this->authorizeRole($request);

    $validated = $request->validated();

    $tag = new Tag();
    $tag->name = $validated['name'];
    if (array_key_exists('description', $validated)) {
        $tag->description = $validated['description'];
    }
    $tag->save();

    return response()->json($this->formatTag($tag), 201);
}

public function update(TagApiRequest $request, Tag $tag): JsonResponse
{
    $this->authorizeRole($request);

    $validated = $request->validated();

    if (array_key_exists('name', $validated)) {
        $tag->name = $validated['name'];
    }
    if (array_key_exists('description', $validated)) {
        $tag->description = $validated['description'];
    }
    $tag->save();

    return response()->json($this->formatTag($tag));
}
```

- [ ] **Step 4: Aggiungi le route in `routes/api.php`**

Nel gruppo `auth:sanctum`, aggiungi dopo le route GET dei tag:

```php
Route::post('/tags', [TagController::class, 'store']);
Route::patch('/tags/{tag}', [TagController::class, 'update']);
```

- [ ] **Step 5: Esegui i test**

```bash
docker exec php81_orchestrator php artisan test --filter=TagApiTest
```

Atteso: i test fino a store/update passano; attach/detach ancora FAIL.

- [ ] **Step 6: Commit**

```
feat(oc:8155): add TagController store and update endpoints
```

---

### Task 4: TagController — attach e detach

**Files:**
- Modify: `app/Http/Controllers/Api/TagController.php`
- Test: `tests/Feature/Api/TagApiTest.php`

**Interfaces:**
- Consumes: `TagController` (Task 2 e 3), `StoryLog` model
- Produce: metodi `attachStory()` e `detachStory()`; route `POST /api/tags/{tag}/stories/{story}` e `DELETE /api/tags/{tag}/stories/{story}`

- [ ] **Step 1: Aggiungi i test per attach e detach**

```php
/** @test */
public function attach_collega_story_a_tag(): void
{
    $user = $this->actingAsDeveloper();
    $tag   = Tag::factory()->create();
    $story = Story::factory()->create();

    $this->postJson("/api/tags/{$tag->id}/stories/{$story->id}")->assertStatus(200);

    $this->assertDatabaseHas('taggables', [
        'tag_id'        => $tag->id,
        'taggable_id'   => $story->id,
        'taggable_type' => 'App\Models\Story',
    ]);
    $this->assertDatabaseHas('story_logs', [
        'story_id' => $story->id,
        'user_id'  => $user->id,
    ]);
}

/** @test */
public function attach_è_idempotente(): void
{
    $this->actingAsDeveloper();
    $tag   = Tag::factory()->create();
    $story = Story::factory()->create();
    $tag->tagged()->attach($story->id);

    $this->postJson("/api/tags/{$tag->id}/stories/{$story->id}")->assertStatus(200);

    $this->assertEquals(1, $tag->tagged()->where('taggable_id', $story->id)->count());
}

/** @test */
public function detach_scollega_story_da_tag(): void
{
    $user = $this->actingAsDeveloper();
    $tag   = Tag::factory()->create();
    $story = Story::factory()->create();
    $tag->tagged()->attach($story->id);

    $this->deleteJson("/api/tags/{$tag->id}/stories/{$story->id}")->assertStatus(200);

    $this->assertDatabaseMissing('taggables', [
        'tag_id'      => $tag->id,
        'taggable_id' => $story->id,
    ]);
    $this->assertDatabaseHas('story_logs', [
        'story_id' => $story->id,
        'user_id'  => $user->id,
    ]);
}

/** @test */
public function detach_è_idempotente(): void
{
    $this->actingAsDeveloper();
    $tag   = Tag::factory()->create();
    $story = Story::factory()->create();

    $this->deleteJson("/api/tags/{$tag->id}/stories/{$story->id}")->assertStatus(200);
}
```

- [ ] **Step 2: Esegui i test per verificare che falliscano**

```bash
docker exec php81_orchestrator php artisan test --filter=TagApiTest
```

Atteso: FAIL (metodi e route non esistono)

- [ ] **Step 3: Aggiungi `attachStory()` e `detachStory()` in `TagController`**

Aggiungi alla classe `TagController`:

```php
public function attachStory(Request $request, Tag $tag, Story $story): JsonResponse
{
    $this->authorizeRole($request);

    $tag->tagged()->syncWithoutDetaching([$story->id]);

    StoryLog::create([
        'story_id'  => $story->id,
        'user_id'   => $request->user()->id,
        'viewed_at' => now()->format('Y-m-d H:i'),
        'changes'   => ['tag_attached' => $tag->id],
    ]);

    return response()->json(['message' => 'Story attached to tag.']);
}

public function detachStory(Request $request, Tag $tag, Story $story): JsonResponse
{
    $this->authorizeRole($request);

    $tag->tagged()->detach($story->id);

    StoryLog::create([
        'story_id'  => $story->id,
        'user_id'   => $request->user()->id,
        'viewed_at' => now()->format('Y-m-d H:i'),
        'changes'   => ['tag_detached' => $tag->id],
    ]);

    return response()->json(['message' => 'Story detached from tag.']);
}
```

Aggiungi l'import di `Story` in testa al controller (se non già presente):
```php
use App\Models\Story;
```

- [ ] **Step 4: Aggiungi le route in `routes/api.php`**

Nel gruppo `auth:sanctum`, aggiungi:

```php
Route::post('/tags/{tag}/stories/{story}', [TagController::class, 'attachStory']);
Route::delete('/tags/{tag}/stories/{story}', [TagController::class, 'detachStory']);
```

- [ ] **Step 5: Esegui tutti i test**

```bash
docker exec php81_orchestrator php artisan test --filter=TagApiTest
```

Atteso: tutti i test passano (verde).

- [ ] **Step 6: Esegui la suite completa per verificare no regressioni**

```bash
docker exec php81_orchestrator php artisan test
```

Atteso: nessun test pre-esistente è diventato rosso.

- [ ] **Step 7: Commit**

```
feat(oc:8155): add TagController attach and detach story endpoints
```
