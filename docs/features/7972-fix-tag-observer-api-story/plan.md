> Ticket: oc:7972

# Fix applicazione automatica tag via API Story — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Garantire che i tag automatici (trimestre, customer, repo da testo) vengano applicati alle Story create/aggiornate via API REST, esattamente come avviene nel flusso Nova.

**Architecture:** La logica di auto-tagging viene spostata da `Nova\Story::afterCreate/afterUpdate` a due punti: (1) `StoryObserver::created` per la creazione, (2) chiamata esplicita nel controller dopo `tags()->sync()` per store e update. `Nova\Story::afterCreate/afterUpdate` vengono rimossi. Tutto in un unico commit atomico.

**Tech Stack:** Laravel 10, Eloquent Observer, `App\Services\TagService`, PHPUnit (Feature + Unit tests)

---

## File Map

| Azione | File | Responsabilità |
|--------|------|----------------|
| Modify | `app/Observers/StoryObserver.php` | Aggiunge chiamata a `TagService::attachAutoTags` in `created` |
| Modify | `app/Nova/Story.php` | Rimuove `afterCreate`, `afterUpdate`, `attachAutoTags` |
| Modify | `app/Http/Controllers/Api/StoryController.php` | Chiama `attachAutoTags` dopo `sync()` in `store()` e `update()` |
| Modify | `tests/Feature/Api/StoryApiTest.php` | Aggiunge test per auto-tagging via API |

---

## Task 1: Test fallenti per auto-tag in `store` e `update`

**Files:**
- Modify: `tests/Feature/Api/StoryApiTest.php`

- [ ] **Step 1: Aggiungi import mancanti in cima al file di test**

Apri `tests/Feature/Api/StoryApiTest.php` e aggiungi dopo gli `use` esistenti:

```php
use App\Models\Customer;
use App\Models\Tag;
use App\Services\TagService;
use Mockery;
```

- [ ] **Step 2: Aggiungi i tre test fallenti**

Aggiungi alla fine della classe, prima della `}` di chiusura:

```php
/** @test */
public function crea_story_via_api_applica_tag_trimestre(): void
{
    Sanctum::actingAs($this->developer);

    $response = $this->postJson('/api/stories', [
        'name' => 'Story con tag trimestre',
        'type' => StoryType::Feature->value,
    ]);

    $response->assertStatus(201);

    $storyId = $response->json('id');
    $story = Story::find($storyId);

    $quarterName = \App\Services\TagService::currentQuarterName();
    $this->assertTrue(
        $story->tags->contains('name', $quarterName),
        "Il tag trimestre '{$quarterName}' non è stato applicato alla story creata via API"
    );
}

/** @test */
public function aggiorna_story_via_api_applica_tag_trimestre(): void
{
    Sanctum::actingAs($this->developer);

    $story = Story::factory()->create(['creator_id' => $this->developer->id]);

    $response = $this->patchJson("/api/stories/{$story->id}", [
        'name' => 'Story aggiornata via API',
    ]);

    $response->assertStatus(200);

    $story->refresh();
    $quarterName = \App\Services\TagService::currentQuarterName();
    $this->assertTrue(
        $story->tags->contains('name', $quarterName),
        "Il tag trimestre '{$quarterName}' non è stato applicato alla story aggiornata via API"
    );
}

/** @test */
public function crea_story_via_api_non_rimuove_tag_manuali(): void
{
    Sanctum::actingAs($this->developer);

    $manualTag = Tag::factory()->create(['name' => 'tag-manuale']);

    $response = $this->postJson('/api/stories', [
        'name' => 'Story con tag manuale',
        'type' => StoryType::Feature->value,
        'tags' => [$manualTag->id],
    ]);

    $response->assertStatus(201);

    $storyId = $response->json('id');
    $story = Story::find($storyId);

    $this->assertTrue(
        $story->tags->contains('id', $manualTag->id),
        'Il tag manuale è stato rimosso dopo applicazione auto-tag'
    );

    $quarterName = \App\Services\TagService::currentQuarterName();
    $this->assertTrue(
        $story->tags->contains('name', $quarterName),
        "Il tag trimestre '{$quarterName}' non è presente insieme al tag manuale"
    );
}
```

- [ ] **Step 3: Aggiungi factory per Tag se non esiste**

Verifica che esista `database/factories/TagFactory.php`:

```bash
ls database/factories/TagFactory.php
```

Se non esiste, creala:

```php
<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class TagFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => $this->faker->unique()->word(),
        ];
    }
}
```

- [ ] **Step 4: Esegui i test e verifica che falliscano**

Dentro il container Docker:

```bash
docker exec -it php81_orchestrator php artisan test --filter=crea_story_via_api_applica_tag_trimestre
docker exec -it php81_orchestrator php artisan test --filter=aggiorna_story_via_api_applica_tag_trimestre
docker exec -it php81_orchestrator php artisan test --filter=crea_story_via_api_non_rimuove_tag_manuali
```

Atteso: tutti e tre **FAIL** (i tag automatici non vengono applicati).

---

## Task 2: Aggiunta `attachAutoTags` in `StoryObserver::created`

**Files:**
- Modify: `app/Observers/StoryObserver.php`

- [ ] **Step 1: Aggiungi import di `TagService`**

In `app/Observers/StoryObserver.php`, dopo la riga `use Illuminate\Support\Facades\Log;` aggiungi:

```php
use App\Services\TagService;
```

- [ ] **Step 2: Modifica il metodo `created`**

Sostituisci il metodo `created` esistente (che termina dopo il blocco `if ($user)`) aggiungendo la chiamata al TagService **dopo** il blocco di logging:

```php
public function created(Story $story): void
{
    // Mark this story as newly created
    self::$createdStories[$story->id] = true;

    $user = Auth::user();
    if (is_null($user)) {
        $user = User::where('email', 'orchestrator_artisan@webmapp.it')->first();
    }

    if ($user) {
        $message = sprintf(
            '%s (%s) created story #%d "%s" on %s',
            $user->name,
            $user->email,
            $story->id,
            $story->name,
            now()->format('d-m-Y H:i:s')
        );

        $context = [
            'story_id'     => $story->id,
            'story_name'   => $story->name,
            'story_status' => $story->status,
            'story_type'   => $story->type,
            'user_id'      => $user->id,
            'user_name'    => $user->name,
            'user_email'   => $user->email,
            'timestamp'    => now()->format('Y-m-d H:i:s'),
        ];

        Log::channel('user-activity')->info($message, $context);
    }

    try {
        $tagService = app(TagService::class);
        $tagService->attachQuarterTagToStory($story);
        $tagService->attachCustomerTagToStory($story);
        $tagService->attachTagsFromTextToStory($story);
    } catch (\Throwable $e) {
        Log::warning("Auto-tagging failed for story #{$story->id}: " . $e->getMessage());
    }
}
```

- [ ] **Step 3: Esegui il test `store` e verifica che passi**

```bash
docker exec -it php81_orchestrator php artisan test --filter=crea_story_via_api_applica_tag_trimestre
```

Atteso: **PASS**

- [ ] **Step 4: Verifica che il test tag manuali passi**

```bash
docker exec -it php81_orchestrator php artisan test --filter=crea_story_via_api_non_rimuove_tag_manuali
```

Atteso: **PASS** — l'Observer applica i tag automatici al `created`, poi `sync()` nel controller aggiunge i manuali con `syncWithoutDetaching` implicito... 

> ⚠️ **Attenzione:** se questo test **fallisce** perché il `sync()` nel controller sovrascrive i tag dell'Observer, procedi al Task 3 prima di ri-eseguirlo. Il `sync()` attuale usa `tags()->sync($tags)` che **rimuove** i tag non in lista — inclusi quelli appena aggiunti dall'Observer.

---

## Task 3: Fix ordine operazioni in `StoryController::store` e `update`

**Files:**
- Modify: `app/Http/Controllers/Api/StoryController.php`

- [ ] **Step 1: Aggiungi import di `TagService`**

Dopo `use Illuminate\Http\JsonResponse;` aggiungi:

```php
use App\Services\TagService;
```

- [ ] **Step 2: Modifica `store()` — cambia `sync` in `syncWithoutDetaching` e aggiungi auto-tag dopo**

Sostituisci il blocco finale di `store()`:

```php
// PRIMA (da sostituire):
if ($tags !== null) {
    $story->tags()->sync($tags);
}

$story->load('tags');

return response()->json($this->formatStory($story), 201);
```

Con:

```php
if ($tags !== null) {
    $story->tags()->syncWithoutDetaching($tags);
}

$this->attachAutoTags($story);

$story->load('tags');

return response()->json($this->formatStory($story), 201);
```

- [ ] **Step 3: Modifica `update()` — stessa modifica**

Sostituisci il blocco finale di `update()`:

```php
// PRIMA (da sostituire):
if ($tags !== null) {
    $story->tags()->sync($tags);
}

$story->load('tags');

return response()->json($this->formatStory($story));
```

Con:

```php
if ($tags !== null) {
    $story->tags()->syncWithoutDetaching($tags);
}

$this->attachAutoTags($story);

$story->load('tags');

return response()->json($this->formatStory($story));
```

- [ ] **Step 4: Aggiungi il metodo privato `attachAutoTags` al controller**

Aggiungi prima di `formatStory()`:

```php
private function attachAutoTags(Story $story): void
{
    try {
        $tagService = app(TagService::class);
        $tagService->attachQuarterTagToStory($story);
        $tagService->attachCustomerTagToStory($story);
        $tagService->attachTagsFromTextToStory($story);
    } catch (\Throwable $e) {
        \Illuminate\Support\Facades\Log::warning(
            "Auto-tagging failed for story #{$story->id}: " . $e->getMessage()
        );
    }
}
```

- [ ] **Step 5: Esegui tutti e tre i test**

```bash
docker exec -it php81_orchestrator php artisan test --filter=crea_story_via_api_applica_tag_trimestre
docker exec -it php81_orchestrator php artisan test --filter=aggiorna_story_via_api_applica_tag_trimestre
docker exec -it php81_orchestrator php artisan test --filter=crea_story_via_api_non_rimuove_tag_manuali
```

Atteso: tutti e tre **PASS**

---

## Task 4: Rimozione `afterCreate`/`afterUpdate` da `Nova\Story`

**Files:**
- Modify: `app/Nova/Story.php`

- [ ] **Step 1: Rimuovi i tre metodi da `Nova\Story`**

Individua e rimuovi interamente questi tre metodi da `app/Nova/Story.php`:

```php
public static function afterCreate(NovaRequest $request, Model $model): void
{
    static::attachAutoTags($model);
}

public static function afterUpdate(NovaRequest $request, Model $model): void
{
    static::attachAutoTags($model);
}

private static function attachAutoTags(Model $model): void
{
    $tagService = app(\App\Services\TagService::class);
    $tagService->attachQuarterTagToStory($model);
    $tagService->attachCustomerTagToStory($model);
    $tagService->attachTagsFromTextToStory($model);
}
```

- [ ] **Step 2: Verifica che non ci siano riferimenti rimasti**

```bash
grep -n "afterCreate\|afterUpdate\|attachAutoTags" app/Nova/Story.php
```

Atteso: nessun output.

- [ ] **Step 3: Esegui la suite completa per verificare nessuna regressione**

```bash
docker exec -it php81_orchestrator php artisan test --filter=StoryApiTest
```

Atteso: tutti **PASS**

---

## Task 5: Verifica suite completa e pulizia

- [ ] **Step 1: Esegui tutta la suite**

```bash
docker exec -it php81_orchestrator php artisan test
```

Atteso: nessun test rosso introdotto da questa fix.

- [ ] **Step 2: Verifica `git diff --stat`**

```bash
git diff --stat
```

Atteso: 4 file modificati:
- `app/Observers/StoryObserver.php`
- `app/Nova/Story.php`
- `app/Http/Controllers/Api/StoryController.php`
- `tests/Feature/Api/StoryApiTest.php`

Più eventualmente `database/factories/TagFactory.php` se creata al Task 1.
