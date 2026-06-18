> Ticket: oc:8051

# [oc] tag automatici — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Ripristinare l'auto-tagging su update via Nova UI e rendere robusto il try/catch in `StoryObserver::created()`.

**Architecture:** Aggiungere `afterCreate`/`afterUpdate` in `app/Nova/Story.php` (rimossi in oc:7972), isolare ogni chiamata TagService in un proprio try/catch in `StoryObserver::created()`, alzare il log level a `error`. Test Feature che copre create e update.

**Tech Stack:** Laravel 10, Laravel Nova, PHPUnit, PostgreSQL (`orchestrator_test` DB).

## Global Constraints

- Commit convention: `fix(oc:8051): ...`
- Nessun `git commit`, `git add`, `git push` autonomo — solo istruzioni testuali
- Test con `use DatabaseTransactions`, girano su `orchestrator_test`
- Comando test: `docker exec php81_orchestrator php artisan test --filter=StoryAutoTaggingTest`

---

### Task 1: Test Feature per auto-tagging (TDD — scrivi prima i test)

**Files:**
- Create: `tests/Feature/StoryAutoTaggingTest.php`

**Interfaces:**
- Consumes: `App\Models\Story`, `App\Models\User`, `App\Models\Tag`, `App\Services\TagService`, `App\Enums\UserRole`
- Produces: suite di test che fallisce fino al Task 2

- [ ] **Step 1: Crea il file di test**

```php
<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Story;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class StoryAutoTaggingTest extends TestCase
{
    use DatabaseTransactions;

    private User $developer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->developer = User::factory()->create([
            'roles' => [UserRole::Developer->value],
        ]);
    }

    /** @test */
    public function quarter_tag_is_attached_on_story_created(): void
    {
        $this->actingAs($this->developer);

        $story = Story::factory()->create([
            'name' => 'Test story created',
        ]);

        $expectedQuarter = \App\Services\TagService::quarterNameForDate($story->created_at);

        $this->assertTrue(
            $story->tags()->where('name', $expectedQuarter)->exists(),
            "Quarter tag '{$expectedQuarter}' not attached on create"
        );
    }

    /** @test */
    public function quarter_tag_is_attached_on_story_updated(): void
    {
        $this->actingAs($this->developer);

        // Crea senza tag (bypassando Nova)
        $story = Story::factory()->create(['name' => 'Test story update']);
        $story->tags()->detach(); // rimuove eventuali tag già attaccati da created

        // Simula un update Nova chiamando afterUpdate direttamente
        $tagService = app(\App\Services\TagService::class);
        $tagService->attachQuarterTagToStory($story);

        $expectedQuarter = \App\Services\TagService::quarterNameForDate($story->created_at);

        $this->assertTrue(
            $story->tags()->where('name', $expectedQuarter)->exists(),
            "Quarter tag '{$expectedQuarter}' not attached on update"
        );
    }

    /** @test */
    public function text_tags_are_attached_on_story_created(): void
    {
        $this->actingAs($this->developer);

        $story = Story::factory()->create([
            'name' => 'Test story with github url',
            'description' => '<p>Fix needed in https://github.com/webmappsrl/wm-app repo</p>',
        ]);

        $this->assertTrue(
            $story->tags()->where('name', 'wm-app')->exists(),
            "Tag 'wm-app' not attached from description URL on create"
        );
    }

    /** @test */
    public function text_tags_are_attached_on_story_updated(): void
    {
        $this->actingAs($this->developer);

        $story = Story::factory()->create(['name' => 'Test story update text tags']);
        $story->tags()->detach();

        // Simula aggiornamento della description e chiamata afterUpdate
        $story->description = '<p>See https://github.com/webmappsrl/orchestrator for details</p>';
        $story->saveQuietly();

        $tagService = app(\App\Services\TagService::class);
        $tagService->attachTagsFromTextToStory($story);

        $this->assertTrue(
            $story->tags()->where('name', 'orchestrator')->exists(),
            "Tag 'orchestrator' not attached from description URL on update"
        );
    }

    /** @test */
    public function nova_after_update_attaches_quarter_tag(): void
    {
        $this->actingAs($this->developer);

        $story = Story::factory()->create(['name' => 'Nova update test']);
        $story->tags()->detach();

        // Simula il ciclo Nova afterUpdate
        \App\Nova\Story::afterUpdate(
            app(\Laravel\Nova\Http\Requests\NovaRequest::class),
            $story
        );

        $expectedQuarter = \App\Services\TagService::quarterNameForDate($story->created_at);

        $this->assertTrue(
            $story->tags()->where('name', $expectedQuarter)->exists(),
            "Quarter tag '{$expectedQuarter}' not attached via Nova afterUpdate"
        );
    }
}
```

- [ ] **Step 2: Esegui i test per verificare che falliscano**

```bash
docker exec php81_orchestrator php artisan test --filter=StoryAutoTaggingTest
```

Atteso: i test `nova_after_update_attaches_quarter_tag` e `text_tags_are_attached_on_story_updated` (via afterUpdate) falliscono perché `afterUpdate` non esiste ancora in `Nova/Story.php`. Gli altri potrebbero passare già se l'observer `created()` funziona.

---

### Task 2: Aggiungere `afterCreate` e `afterUpdate` in `app/Nova/Story.php`

**Files:**
- Modify: `app/Nova/Story.php` (ultima riga prima di `}` di classe, riga ~534)

**Interfaces:**
- Consumes: `App\Services\TagService`, `Laravel\Nova\Http\Requests\NovaRequest` (già importato), `Illuminate\Database\Eloquent\Model` (da importare)
- Produces: `Story::afterCreate()`, `Story::afterUpdate()`, `Story::attachAutoTags()` usati dal Task 1

- [ ] **Step 1: Aggiungi l'import di `Model` in cima al file**

In `app/Nova/Story.php`, dopo la riga `use Laravel\Nova\Http\Requests\NovaRequest;` (riga 24), aggiungi:

```php
use Illuminate\Database\Eloquent\Model;
```

- [ ] **Step 2: Aggiungi i metodi prima della chiusura della classe**

In `app/Nova/Story.php`, prima dell'ultima `}` (riga 536), aggiungi:

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

        try {
            $tagService->attachQuarterTagToStory($model);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error("Auto-tagging (quarter) failed for story #{$model->id}: " . $e->getMessage());
        }

        try {
            $tagService->attachCustomerTagToStory($model);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error("Auto-tagging (customer) failed for story #{$model->id}: " . $e->getMessage());
        }

        try {
            $tagService->attachTagsFromTextToStory($model);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error("Auto-tagging (text) failed for story #{$model->id}: " . $e->getMessage());
        }
    }
```

- [ ] **Step 3: Esegui i test**

```bash
docker exec php81_orchestrator php artisan test --filter=StoryAutoTaggingTest
```

Atteso: tutti i test passano.

---

### Task 3: Isolare try/catch in `StoryObserver::created()`

**Files:**
- Modify: `app/Observers/StoryObserver.php` (righe 59–66)

**Interfaces:**
- Consumes: nessuna dipendenza da altri task
- Produces: nessuna interfaccia pubblica nuova

- [ ] **Step 1: Sostituisci il blocco try/catch monolitico**

In `app/Observers/StoryObserver.php`, sostituisci il blocco attuale (righe 59–66):

```php
        try {
            $tagService = app(TagService::class);
            $tagService->attachQuarterTagToStory($story);
            $tagService->attachCustomerTagToStory($story);
            $tagService->attachTagsFromTextToStory($story);
        } catch (\Throwable $e) {
            Log::warning("Auto-tagging failed for story #{$story->id}: " . $e->getMessage());
        }
```

con:

```php
        $tagService = app(TagService::class);

        try {
            $tagService->attachQuarterTagToStory($story);
        } catch (\Throwable $e) {
            Log::error("Auto-tagging (quarter) failed for story #{$story->id}: " . $e->getMessage());
        }

        try {
            $tagService->attachCustomerTagToStory($story);
        } catch (\Throwable $e) {
            Log::error("Auto-tagging (customer) failed for story #{$story->id}: " . $e->getMessage());
        }

        try {
            $tagService->attachTagsFromTextToStory($story);
        } catch (\Throwable $e) {
            Log::error("Auto-tagging (text) failed for story #{$story->id}: " . $e->getMessage());
        }
```

- [ ] **Step 2: Esegui la suite completa dei tag test**

```bash
docker exec php81_orchestrator php artisan test --filter=StoryAutoTaggingTest
```

Atteso: tutti i test passano.

- [ ] **Step 3: Esegui i test esistenti per verificare nessuna regressione**

```bash
docker exec php81_orchestrator php artisan test --filter=StoryEmailTriggersTest
docker exec php81_orchestrator php artisan test --filter=TagServiceTest
```

Atteso: tutti i test passano.

- [ ] **Step 4: Istruzioni commit (da eseguire manualmente)**

```bash
git add app/Nova/Story.php app/Observers/StoryObserver.php tests/Feature/StoryAutoTaggingTest.php
git commit -m "fix(oc:8051): restore afterUpdate in Nova Story and isolate auto-tagging try/catch"
```
