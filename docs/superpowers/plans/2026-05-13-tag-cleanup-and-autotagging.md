# Tag Cleanup & Auto-tagging — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Pulire i nomi dei tag esistenti, auto-assegnare tag quarter/customer/repository ai ticket (presenti e futuri), e aggiungere un'Action Nova per il merge di tag.

**Architecture:** Un Artisan command `tags:align` gestisce il backfill idempotente su tutti i dati esistenti. Un servizio `TagService` centralizza tutta la logica di `firstOrCreate` + attach dei tag. `StoryObserver` usa `TagService` per i ticket futuri. Nova riceve una MergeTagsAction e il selettore tag ordinato per utilizzo.

**Tech Stack:** Laravel 10, Laravel Nova, PostgreSQL, Eloquent Observer

---

## File Map

| File | Azione | Responsabilità |
|------|--------|----------------|
| `app/Services/TagService.php` | Crea | Logica centralizzata firstOrCreate + attach per tutti i tipi di tag |
| `app/Console/Commands/AlignTagsCommand.php` | Crea | Command `tags:align` — backfill idempotente su tutti i ticket |
| `app/Models/Customer.php` | Modifica | Fix nome tag generato automaticamente |
| `app/Models/App.php` | Modifica | Fix nome tag generato automaticamente |
| `app/Models/Documentation.php` | Modifica | Fix nome tag generato automaticamente |
| `app/Observers/StoryObserver.php` | Modifica | Aggiunge `creating()` e hook su `saved()` per auto-tag |
| `app/Nova/Actions/MergeTagsAction.php` | Crea | Action Nova: unisce N tag sorgente in un tag destinazione |
| `app/Nova/Tag.php` | Modifica | Registra MergeTagsAction |
| `app/Traits/fieldTrait.php` | Modifica | `tagsField()` ordina per utilizzo recente |
| `tests/Unit/TagServiceTest.php` | Crea | Test su TagService |
| `tests/Unit/AlignTagsCommandTest.php` | Crea | Test sul command |

---

## Task 1: `TagService` — logica centralizzata

**Files:**
- Crea: `app/Services/TagService.php`
- Crea: `tests/Unit/TagServiceTest.php`

- [ ] **Step 1: Scrivi i test che falliranno**

```php
<?php

namespace Tests\Unit;

use App\Models\Tag;
use App\Models\Story;
use App\Services\TagService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TagServiceTest extends TestCase
{
    use RefreshDatabase;

    private TagService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new TagService();
    }

    public function test_ensure_tag_creates_if_not_exists(): void
    {
        $tag = $this->service->ensureTag('osm2cai');

        $this->assertDatabaseHas('tags', ['name' => 'osm2cai']);
        $this->assertInstanceOf(Tag::class, $tag);
    }

    public function test_ensure_tag_returns_existing_tag(): void
    {
        $existing = Tag::factory()->create(['name' => 'osm2cai']);

        $tag = $this->service->ensureTag('osm2cai');

        $this->assertEquals($existing->id, $tag->id);
        $this->assertDatabaseCount('tags', 1);
    }

    public function test_attach_tag_to_story_is_idempotent(): void
    {
        $story = Story::factory()->create();
        $tag = Tag::factory()->create(['name' => 'osm2cai']);

        $this->service->attachTagToStory($story, $tag);
        $this->service->attachTagToStory($story, $tag);

        $this->assertCount(1, $story->fresh()->tags);
    }

    public function test_quarter_tag_name_returns_correct_format(): void
    {
        $name = TagService::currentQuarterName();

        $this->assertMatchesRegularExpression('/^\d{2}Q[1-4]$/', $name);
    }

    public function test_extract_repo_names_from_text_finds_github_urls(): void
    {
        $text = 'Fix in https://github.com/webmapp/osm2cai/pull/123 and https://github.com/webmapp/wm-package/issues/5';

        $repos = TagService::extractRepoNamesFromText($text);

        $this->assertEquals(['osm2cai', 'wm-package'], $repos->toArray());
    }

    public function test_extract_repo_names_deduplicates(): void
    {
        $text = 'https://github.com/webmapp/osm2cai/pull/1 and https://github.com/webmapp/osm2cai/pull/2';

        $repos = TagService::extractRepoNamesFromText($text);

        $this->assertCount(1, $repos);
    }

    public function test_extract_repo_names_returns_empty_when_no_links(): void
    {
        $repos = TagService::extractRepoNamesFromText('Nessun link qui.');

        $this->assertCount(0, $repos);
    }
}
```

- [ ] **Step 2: Verifica che i test falliscano**

```bash
docker exec -it php81_orchestrator php artisan test --filter=TagServiceTest
```

Expected: errore — `TagService` non trovato

- [ ] **Step 3: Crea `app/Services/TagService.php`**

```php
<?php

namespace App\Services;

use App\Models\Story;
use App\Models\Tag;
use Illuminate\Support\Collection;

class TagService
{
    public function ensureTag(string $name, array $attributes = []): Tag
    {
        return Tag::firstOrCreate(
            ['name' => $name],
            array_merge(['name' => $name], $attributes)
        );
    }

    public function attachTagToStory(Story $story, Tag $tag): void
    {
        if (! $story->tags()->where('tags.id', $tag->id)->exists()) {
            $story->tags()->attach($tag->id);
        }
    }

    public function attachTagsFromTextToStory(Story $story): void
    {
        $text = ($story->description ?? '') . ' ' . ($story->customer_request ?? '');
        $repoNames = static::extractRepoNamesFromText($text);

        foreach ($repoNames as $repoName) {
            $tag = $this->ensureTag($repoName);
            $this->attachTagToStory($story, $tag);
        }
    }

    public function attachQuarterTagToStory(Story $story): void
    {
        $tag = $this->ensureTag(static::currentQuarterName());
        // Quarter tag va come primo tag — attach solo se non già presente
        if (! $story->tags()->where('tags.id', $tag->id)->exists()) {
            $story->tags()->attach($tag->id);
        }
    }

    public function attachCustomerTagToStory(Story $story): void
    {
        if (! $story->creator_id) {
            return;
        }

        $creator = \App\Models\User::find($story->creator_id);
        $customer = $creator?->associatedCustomer;

        if (! $customer) {
            return;
        }

        $customerTag = $customer->tags()->first();
        if ($customerTag) {
            $this->attachTagToStory($story, $customerTag);
        }
    }

    public static function currentQuarterName(): string
    {
        $year = now()->format('y');
        $quarter = (int) ceil(now()->month / 3);
        return "{$year}Q{$quarter}";
    }

    public static function extractRepoNamesFromText(string $text): Collection
    {
        // Matcha github.com/org/repo e gitlab.com/org/repo
        preg_match_all(
            '#https?://(?:github|gitlab)\.com/[^/]+/([a-zA-Z0-9_\-\.]+)#',
            $text,
            $matches
        );

        return collect($matches[1])->unique()->values();
    }
}
```

- [ ] **Step 4: Verifica che i test passino**

```bash
docker exec -it php81_orchestrator php artisan test --filter=TagServiceTest
```

Expected: tutti PASS

- [ ] **Step 5: Commit**

```bash
git add app/Services/TagService.php tests/Unit/TagServiceTest.php
git commit -m "feat(tags): add TagService with ensureTag, attachTagToStory, extractRepoNames"
```

---

## Task 2: Fix nomi tag auto-generati in Customer, App, Documentation

**Files:**
- Modifica: `app/Models/Customer.php:150-151`
- Modifica: `app/Models/App.php:281-282`
- Modifica: `app/Models/Documentation.php:54-55`

- [ ] **Step 1: Fix `Customer.php` — riga ~151**

Cambia:
```php
$tag = Tag::firstOrCreate([
    'name' => class_basename($entity) . ': ' . $entity->name,
    'taggable_id' => $entity->id,
    'taggable_type' => get_class($entity)
```
In:
```php
$tag = Tag::firstOrCreate([
    'name' => $entity->name,
    'taggable_id' => $entity->id,
    'taggable_type' => get_class($entity)
```

- [ ] **Step 2: Fix `App.php` — riga ~282**

Stesso cambio: `class_basename($entity) . ': ' . $entity->name` → `$entity->name`

- [ ] **Step 3: Fix `Documentation.php` — riga ~55**

Stesso cambio: `class_basename($entity) . ': ' . $entity->name` → `$entity->name`

- [ ] **Step 4: Verifica test esistenti**

```bash
docker exec -it php81_orchestrator php artisan test
```

Expected: tutti PASS

- [ ] **Step 5: Commit**

```bash
git add app/Models/Customer.php app/Models/App.php app/Models/Documentation.php
git commit -m "fix(tags): remove class prefix from auto-generated tag names"
```

---

## Task 3: Artisan command `tags:align`

**Files:**
- Crea: `app/Console/Commands/AlignTagsCommand.php`
- Crea: `tests/Unit/AlignTagsCommandTest.php`

- [ ] **Step 1: Scrivi i test che falliranno**

```php
<?php

namespace Tests\Unit;

use App\Models\Tag;
use App\Models\Story;
use App\Models\User;
use App\Models\Customer;
use App\Enums\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AlignTagsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_cleans_project_prefix_from_tag_names(): void
    {
        Tag::factory()->create(['name' => 'Project: STELVIO']);

        $this->artisan('tags:align')->assertSuccessful();

        $this->assertDatabaseHas('tags', ['name' => 'STELVIO']);
        $this->assertDatabaseMissing('tags', ['name' => 'Project: STELVIO']);
    }

    public function test_cleans_customer_prefix_from_tag_names(): void
    {
        Tag::factory()->create(['name' => 'Customer: Webmapp']);

        $this->artisan('tags:align')->assertSuccessful();

        $this->assertDatabaseHas('tags', ['name' => 'Webmapp']);
    }

    public function test_cleans_app_prefix_from_tag_names(): void
    {
        Tag::factory()->create(['name' => 'App: OSM2CAI']);

        $this->artisan('tags:align')->assertSuccessful();

        $this->assertDatabaseHas('tags', ['name' => 'OSM2CAI']);
    }

    public function test_cleans_main_project_for_customer_substring(): void
    {
        Tag::factory()->create(['name' => 'Project: Main project for customer ITINERA ROMANICA PLUS']);

        $this->artisan('tags:align')->assertSuccessful();

        $this->assertDatabaseHas('tags', ['name' => 'ITINERA ROMANICA PLUS']);
    }

    public function test_attaches_quarter_tag_to_stories_without_it(): void
    {
        $story = Story::factory()->create();

        $this->artisan('tags:align')->assertSuccessful();

        $quarterName = \App\Services\TagService::currentQuarterName();
        $this->assertTrue(
            $story->fresh()->tags->pluck('name')->contains($quarterName)
        );
    }

    public function test_attaches_quarter_tag_is_idempotent(): void
    {
        $story = Story::factory()->create();

        $this->artisan('tags:align')->assertSuccessful();
        $this->artisan('tags:align')->assertSuccessful();

        $quarterName = \App\Services\TagService::currentQuarterName();
        $this->assertCount(
            1,
            $story->fresh()->tags->where('name', $quarterName)
        );
    }

    public function test_attaches_repo_tag_from_description(): void
    {
        $story = Story::factory()->create([
            'description' => 'Fix in https://github.com/webmapp/osm2cai/pull/5',
            'customer_request' => null,
        ]);

        $this->artisan('tags:align')->assertSuccessful();

        $this->assertTrue(
            $story->fresh()->tags->pluck('name')->contains('osm2cai')
        );
    }

    public function test_aligns_customer_associated_user_by_email(): void
    {
        $user = User::factory()->create(['email' => 'client@example.com']);
        $customer = Customer::factory()->create(['email' => 'client@example.com', 'associated_user_id' => null]);

        $this->artisan('tags:align')->assertSuccessful();

        $this->assertEquals($user->id, $customer->fresh()->associated_user_id);
    }
}
```

- [ ] **Step 2: Verifica che i test falliscano**

```bash
docker exec -it php81_orchestrator php artisan test --filter=AlignTagsCommandTest
```

Expected: errore — command non trovato

- [ ] **Step 3: Crea `app/Console/Commands/AlignTagsCommand.php`**

```php
<?php

namespace App\Console\Commands;

use App\Models\Customer;
use App\Models\Story;
use App\Models\Tag;
use App\Models\User;
use App\Services\TagService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class AlignTagsCommand extends Command
{
    protected $signature = 'tags:align';
    protected $description = 'Pulisce i nomi dei tag e allinea i tag su tutti i ticket esistenti';

    public function __construct(private TagService $tagService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->cleanTagNames();
        $this->alignCustomerUsers();
        $this->alignQuarterTags();
        $this->alignRepoTags();
        $this->alignCustomerTags();

        $this->info('tags:align completato.');
        return self::SUCCESS;
    }

    private function cleanTagNames(): void
    {
        $this->info('Pulizia nomi tag...');

        Tag::all()->each(function (Tag $tag) {
            $cleaned = $tag->name;
            // Rimuovi prefissi
            $cleaned = preg_replace('/^(Project|Customer|App):\s*/i', '', $cleaned);
            // Rimuovi "Main project for customer "
            $cleaned = preg_replace('/Main project for customer\s*/i', '', $cleaned);
            $cleaned = trim($cleaned);

            if ($cleaned !== $tag->name) {
                $tag->name = $cleaned;
                $tag->saveQuietly();
            }
        });
    }

    private function alignCustomerUsers(): void
    {
        $this->info('Allineamento User → Customer via email...');

        User::whereHas('roles', function ($q) {
            $q->where('name', \App\Enums\UserRole::Customer->value);
        })->each(function (User $user) {
            $customer = Customer::where('email', $user->email)->first();
            if ($customer && $customer->associated_user_id !== $user->id) {
                $customer->associated_user_id = $user->id;
                $customer->saveQuietly();
            }
        });
    }

    private function alignQuarterTags(): void
    {
        $this->info('Aggiunta quarter tag ai ticket...');

        Story::chunk(200, function ($stories) {
            foreach ($stories as $story) {
                $this->tagService->attachQuarterTagToStory($story);
            }
        });
    }

    private function alignRepoTags(): void
    {
        $this->info('Aggiunta tag repository dai link nei ticket...');

        Story::chunk(200, function ($stories) {
            foreach ($stories as $story) {
                $this->tagService->attachTagsFromTextToStory($story);
            }
        });
    }

    private function alignCustomerTags(): void
    {
        $this->info('Aggiunta tag customer ai ticket creati da utenti customer...');

        Story::whereNotNull('creator_id')->chunk(200, function ($stories) {
            foreach ($stories as $story) {
                $this->tagService->attachCustomerTagToStory($story);
            }
        });
    }
}
```

- [ ] **Step 4: Registra il command in `app/Console/Kernel.php`**

Nel metodo `$commands`, aggiungi:
```php
\App\Console\Commands\AlignTagsCommand::class,
```

- [ ] **Step 5: Verifica che i test passino**

```bash
docker exec -it php81_orchestrator php artisan test --filter=AlignTagsCommandTest
```

Expected: tutti PASS

- [ ] **Step 6: Commit**

```bash
git add app/Console/Commands/AlignTagsCommand.php app/Console/Kernel.php tests/Unit/AlignTagsCommandTest.php
git commit -m "feat(tags): add tags:align command for idempotent backfill"
```

---

## Task 4: `StoryObserver` — auto-tag su creating e saved

**Files:**
- Modifica: `app/Observers/StoryObserver.php`

- [ ] **Step 1: Aggiungi `creating()` a `StoryObserver`**

Aggiungi subito prima del metodo `created()` esistente:

```php
public function creating(Story $story): void
{
    // Salviamo i tag da attaccare dopo il persist (serve l'id della story)
    $story->_pendingAutoTags = true;
}
```

- [ ] **Step 2: Aggiungi hook `created()` per i tag**

Nel metodo `created()` esistente, aggiungi come PRIMA istruzione del corpo:

```php
if ($story->_pendingAutoTags ?? false) {
    $tagService = app(\App\Services\TagService::class);
    $tagService->attachQuarterTagToStory($story);
    $tagService->attachCustomerTagToStory($story);
    $tagService->attachTagsFromTextToStory($story);
    $story->_pendingAutoTags = false;
}
```

- [ ] **Step 3: Aggiungi hook `updated()` per i tag repository**

Nel metodo `updated()` esistente, aggiungi in fondo:

```php
if ($story->wasChanged('description') || $story->wasChanged('customer_request')) {
    app(\App\Services\TagService::class)->attachTagsFromTextToStory($story);
}
```

- [ ] **Step 4: Verifica test**

```bash
docker exec -it php81_orchestrator php artisan test
```

Expected: tutti PASS

- [ ] **Step 5: Commit**

```bash
git add app/Observers/StoryObserver.php
git commit -m "feat(stories): auto-attach quarter, customer, repo tags on create/update"
```

---

## Task 5: Nova Action — `MergeTagsAction`

**Files:**
- Crea: `app/Nova/Actions/MergeTagsAction.php`
- Modifica: `app/Nova/Tag.php`

- [ ] **Step 1: Crea `app/Nova/Actions/MergeTagsAction.php`**

```php
<?php

namespace App\Nova\Actions;

use App\Models\Tag;
use Illuminate\Support\Collection;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Fields\ActionFields;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Http\Requests\NovaRequest;

class MergeTagsAction extends Action
{
    public $name = 'Merge Tags';
    public $confirmText = 'I tag sorgente selezionati verranno cancellati dopo il merge.';
    public $confirmButtonText = 'Esegui Merge';

    public function handle(ActionFields $fields, Collection $models): void
    {
        $destinationId = (int) $fields->destination_tag_id;
        $destination = Tag::find($destinationId);

        if (! $destination) {
            $this->danger('Tag destinazione non trovato.');
            return;
        }

        foreach ($models as $source) {
            if ($source->id === $destinationId) {
                continue;
            }

            // Sposta tutti i ticket del tag sorgente al tag destinazione (idempotente)
            $source->tagged()->each(function ($story) use ($destination) {
                if (! $story->tags()->where('tags.id', $destination->id)->exists()) {
                    $story->tags()->attach($destination->id);
                }
            });

            $source->delete();
        }

        $this->message('Merge completato. ' . $models->count() . ' tag sorgente eliminati.');
    }

    public function fields(NovaRequest $request): array
    {
        return [
            Select::make('Tag destinazione', 'destination_tag_id')
                ->options(
                    Tag::whereNull('taggable_type')
                        ->orWhere('taggable_type', '!=', \App\Models\Documentation::class)
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->toArray()
                )
                ->rules('required')
                ->searchable(),
        ];
    }
}
```

- [ ] **Step 2: Registra l'Action in `app/Nova/Tag.php`**

Nel metodo `actions()`:

```php
public function actions(Request $request)
{
    return [
        new \App\Nova\Actions\MergeTagsAction,
    ];
}
```

- [ ] **Step 3: Test manuale in Nova**

1. Apri `/nova/resources/tags`
2. Seleziona 2+ tag sorgente
3. Esegui "Merge Tags" scegliendo un tag destinazione
4. Verifica che i ticket siano ora agganciati al tag destinazione
5. Verifica che i tag sorgente siano stati eliminati

- [ ] **Step 4: Commit**

```bash
git add app/Nova/Actions/MergeTagsAction.php app/Nova/Tag.php
git commit -m "feat(nova/tags): add MergeTagsAction to consolidate tags"
```

---

## Task 6: `tagsField()` — tag ordinati per utilizzo recente

**Files:**
- Modifica: `app/Traits/fieldTrait.php:363`

- [ ] **Step 1: Aggiorna `tagsField()`**

```php
public function tagsField($fieldLabel = 'Tags', $fieldName = 'tags')
{
    return
        Tag::make($fieldLabel, $fieldName, novaTag::class)
        ->withPreview()
        ->relatableQueryUsing(function (\Laravel\Nova\Http\Requests\NovaRequest $request, $query) {
            $query->where(function ($q) {
                $q->whereNull('taggable_type')
                  ->orWhere('taggable_type', '!=', \App\Models\Documentation::class);
            })
            ->leftJoin(
                \Illuminate\Support\Facades\DB::raw('(SELECT tag_id, COUNT(*) as usage_count FROM taggables GROUP BY tag_id) as tag_usage'),
                'tags.id', '=', 'tag_usage.tag_id'
            )
            ->orderByDesc('tag_usage.usage_count')
            ->select('tags.*');
        })
        ->help(__('Tags are used both to categorize a ticket and to display documentation in the "Info" section of the customer ticket view.'))
        ->canSee($this->canSee($fieldName));
}
```

- [ ] **Step 2: Verifica visivamente in Nova**

Apri la creazione di una Story, controlla che nel selettore Tag i tag più usati compaiano per primi e che i tag Documentation siano assenti.

- [ ] **Step 3: Commit**

```bash
git add app/Traits/fieldTrait.php
git commit -m "feat(nova/stories): sort tag selector by usage frequency, exclude Documentation"
```

---

## Self-Review

### Spec coverage
- [x] Quarter tag su tutti i ticket esistenti — Task 3 (`alignQuarterTags`)
- [x] Quarter tag su ticket futuri — Task 4 (`creating`)
- [x] Pulizia prefissi `Project:`, `Customer:`, `App:` — Task 3 (`cleanTagNames`)
- [x] Pulizia `Main project for customer` — Task 3 (`cleanTagNames`)
- [x] Fix codice per non rigenerare prefissi — Task 2
- [x] Tag repository da link git/PR su ticket esistenti — Task 3 (`alignRepoTags`)
- [x] Tag repository da link git/PR su ticket futuri — Task 4 (`updated`)
- [x] Relazione User → Customer via email — Task 3 (`alignCustomerUsers`)
- [x] Tag customer su ticket esistenti — Task 3 (`alignCustomerTags`)
- [x] Tag customer su ticket futuri — Task 4 (`creating`)
- [x] `firstOrCreate` ovunque — TagService usa sempre `firstOrCreate`
- [x] MergeTagsAction Nova — Task 5
- [x] Tag usati di recente in cima al selettore — Task 6

### Placeholder scan
Nessun placeholder bloccante. Tutti i task hanno codice completo.

### Type consistency
- `TagService::attachQuarterTagToStory()`, `attachCustomerTagToStory()`, `attachTagsFromTextToStory()` — usati identicamente in command e observer.
- `TagService::extractRepoNamesFromText()` — statico, usato in test e in `attachTagsFromTextToStory()`.
- `MergeTagsAction` usa `$source->tagged()` che è la relazione `morphedByMany(Story::class, 'taggable')` definita in `Tag.php` — consistente.
