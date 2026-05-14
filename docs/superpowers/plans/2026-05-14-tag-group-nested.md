# TagGroup Nested — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Estendere `TagGroup` per permettere di usare altri TagGroup come condizioni nei gruppi di filtro, in OR con i tag normali.

**Architecture:** Ogni gruppo (slot `condition_1..4`) può contenere sia tag IDs che TagGroup IDs. Si aggiungono colonne JSON `tag_group_condition_1..4` alla tabella `tag_groups` per memorizzare i TagGroup per slot. La tabella `tag_group_conditions` si estende con una colonna nullable `ref_tag_group_id`. Il metodo `computeMatchingStoryIds()` viene aggiornato: per ogni gruppo AND, una story matcha se ha almeno uno dei tag del gruppo OR appartiene alle story di almeno uno dei TagGroup del gruppo. Il sync rimane lazy (al render Nova). La UI Nova mostra due multiselect per ogni gruppo: uno per i Tag e uno per i TagGroup.

**Tech Stack:** Laravel 10, Laravel Nova, PostgreSQL, Eloquent

---

## File Map

| File | Azione | Responsabilità |
|------|--------|----------------|
| `database/migrations/2026_05_14_110000_add_tag_group_conditions_to_tag_groups.php` | Crea | Aggiunge `tag_group_condition_1..4` a `tag_groups` |
| `database/migrations/2026_05_14_110001_add_ref_tag_group_to_tag_group_conditions.php` | Crea | Aggiunge `ref_tag_group_id` nullable a `tag_group_conditions` |
| `app/Models/TagGroupCondition.php` | Modifica | Aggiunge relazione `refTagGroup()`, aggiorna `$fillable` |
| `app/Models/TagGroup.php` | Modifica | Aggiorna `$fillable`, `$casts`, `syncConditionsFromSlots()`, `storyMatches()`, `computeMatchingStoryIds()` |
| `app/Nova/TagGroup.php` | Modifica | Aggiunge MultiSelect per TagGroup per ogni slot, `prepareModelForDetailView` già gestisce il sync |
| `tests/Unit/TagGroupTest.php` | Modifica | Aggiunge test per condizioni con TagGroup nested |

---

## Task 1: Migrazioni

**Files:**
- Crea: `database/migrations/2026_05_14_110000_add_tag_group_conditions_to_tag_groups.php`
- Crea: `database/migrations/2026_05_14_110001_add_ref_tag_group_to_tag_group_conditions.php`

- [ ] **Step 1: Crea la migrazione per `tag_groups`**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tag_groups', function (Blueprint $table) {
            $table->json('tag_group_condition_1')->nullable()->after('condition_4');
            $table->json('tag_group_condition_2')->nullable()->after('tag_group_condition_1');
            $table->json('tag_group_condition_3')->nullable()->after('tag_group_condition_2');
            $table->json('tag_group_condition_4')->nullable()->after('tag_group_condition_3');
        });
    }

    public function down(): void
    {
        Schema::table('tag_groups', function (Blueprint $table) {
            $table->dropColumn([
                'tag_group_condition_1',
                'tag_group_condition_2',
                'tag_group_condition_3',
                'tag_group_condition_4',
            ]);
        });
    }
};
```

- [ ] **Step 2: Crea la migrazione per `tag_group_conditions`**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tag_group_conditions', function (Blueprint $table) {
            $table->foreignId('ref_tag_group_id')
                ->nullable()
                ->after('tag_id')
                ->constrained('tag_groups')
                ->nullOnDelete();

            // Rende tag_id nullable — una condizione ora può essere tag OPPURE tag_group
            $table->foreignId('tag_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('tag_group_conditions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('ref_tag_group_id');
            $table->foreignId('tag_id')->nullable(false)->change();
        });
    }
};
```

- [ ] **Step 3: Esegui le migrazioni**

```bash
docker exec -it php81_orchestrator php artisan migrate
```

Expected: entrambe le migrazioni eseguite senza errori.

- [ ] **Step 4: Commit**

```bash
git add database/migrations/2026_05_14_110000_add_tag_group_conditions_to_tag_groups.php
git add database/migrations/2026_05_14_110001_add_ref_tag_group_to_tag_group_conditions.php
git commit -m "feat(tag-group): add nested tag_group conditions columns"
```

---

## Task 2: Modello `TagGroupCondition`

**Files:**
- Modifica: `app/Models/TagGroupCondition.php`

- [ ] **Step 1: Scrivi il test RED**

In `tests/Unit/TagGroupTest.php`, aggiungi:

```php
public function test_condition_can_reference_tag_group(): void
{
    $tagGroup = TagGroup::factory()->create();
    $nestedGroup = TagGroup::factory()->create();

    $condition = TagGroupCondition::create([
        'tag_group_id'    => $tagGroup->id,
        'ref_tag_group_id' => $nestedGroup->id,
        'group_index'     => 0,
    ]);

    $this->assertEquals($nestedGroup->id, $condition->refTagGroup->id);
}
```

- [ ] **Step 2: Verifica che fallisca**

```bash
docker exec -it php81_orchestrator php artisan test --filter=test_condition_can_reference_tag_group
```

Expected: FAIL — `refTagGroup` non definito o errore DB su `tag_id` NOT NULL.

- [ ] **Step 3: Aggiorna `TagGroupCondition`**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TagGroupCondition extends Model
{
    use HasFactory;

    protected $fillable = ['tag_group_id', 'tag_id', 'ref_tag_group_id', 'group_index'];

    public function tagGroup()
    {
        return $this->belongsTo(TagGroup::class);
    }

    public function tag()
    {
        return $this->belongsTo(Tag::class);
    }

    public function refTagGroup()
    {
        return $this->belongsTo(TagGroup::class, 'ref_tag_group_id');
    }
}
```

- [ ] **Step 4: Verifica che il test passi**

```bash
docker exec -it php81_orchestrator php artisan test --filter=test_condition_can_reference_tag_group
```

Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Models/TagGroupCondition.php tests/Unit/TagGroupTest.php
git commit -m "feat(tag-group): TagGroupCondition supports ref_tag_group_id"
```

---

## Task 3: Modello `TagGroup` — logica nested

**Files:**
- Modifica: `app/Models/TagGroup.php`

Il comportamento atteso per ogni gruppo AND:
- Una story matcha il gruppo se: ha almeno uno dei **tag** del gruppo OR appartiene alle story di almeno uno dei **TagGroup** del gruppo.

- [ ] **Step 1: Scrivi i test RED**

In `tests/Unit/TagGroupTest.php`, aggiungi:

```php
public function test_nested_tag_group_condition_matches_stories(): void
{
    // Gruppo nested: contiene story con tag "osm2cai"
    $tag = Tag::factory()->create(['name' => 'osm2cai']);
    $nestedGroup = TagGroup::factory()->create();
    TagGroupCondition::create(['tag_group_id' => $nestedGroup->id, 'tag_id' => $tag->id, 'group_index' => 0]);

    $storyInNested = Story::factory()->create();
    $storyInNested->tags()->syncWithoutDetaching([$tag->id]);
    $nestedGroup->syncStories();

    // Gruppo principale: condizione = nestedGroup
    $mainGroup = TagGroup::factory()->create();
    TagGroupCondition::create([
        'tag_group_id'    => $mainGroup->id,
        'ref_tag_group_id' => $nestedGroup->id,
        'group_index'     => 0,
    ]);

    $mainGroup->syncStories();

    $this->assertContains($storyInNested->id, $mainGroup->stories()->pluck('stories.id'));
}

public function test_nested_tag_group_and_tag_in_same_group_are_or(): void
{
    // Gruppo: tag "alpha" OR nestedGroup (che contiene story con tag "beta")
    $tagAlpha = Tag::factory()->create(['name' => 'alpha']);
    $tagBeta  = Tag::factory()->create(['name' => 'beta']);

    $nestedGroup = TagGroup::factory()->create();
    TagGroupCondition::create(['tag_group_id' => $nestedGroup->id, 'tag_id' => $tagBeta->id, 'group_index' => 0]);

    $storyAlpha = Story::factory()->create();
    $storyAlpha->tags()->syncWithoutDetaching([$tagAlpha->id]);

    $storyBeta = Story::factory()->create();
    $storyBeta->tags()->syncWithoutDetaching([$tagBeta->id]);
    $nestedGroup->syncStories();

    $mainGroup = TagGroup::factory()->create();
    // tag alpha in OR con nestedGroup (che porta storyBeta)
    TagGroupCondition::create(['tag_group_id' => $mainGroup->id, 'tag_id' => $tagAlpha->id, 'group_index' => 0]);
    TagGroupCondition::create(['tag_group_id' => $mainGroup->id, 'ref_tag_group_id' => $nestedGroup->id, 'group_index' => 0]);

    $mainGroup->syncStories();

    $results = $mainGroup->stories()->pluck('stories.id');
    $this->assertContains($storyAlpha->id, $results);
    $this->assertContains($storyBeta->id, $results);
}
```

- [ ] **Step 2: Verifica che falliscano**

```bash
docker exec -it php81_orchestrator php artisan test --filter="test_nested_tag_group"
```

Expected: FAIL.

- [ ] **Step 3: Aggiorna `TagGroup`**

Aggiorna `$fillable`, `$casts`, `syncConditionsFromSlots()` e `computeMatchingStoryIds()` / `storyMatches()`:

```php
protected $fillable = [
    'name',
    'description',
    'condition_1',
    'condition_2',
    'condition_3',
    'condition_4',
    'tag_group_condition_1',
    'tag_group_condition_2',
    'tag_group_condition_3',
    'tag_group_condition_4',
];

protected $casts = [
    'condition_1' => 'array',
    'condition_2' => 'array',
    'condition_3' => 'array',
    'condition_4' => 'array',
    'tag_group_condition_1' => 'array',
    'tag_group_condition_2' => 'array',
    'tag_group_condition_3' => 'array',
    'tag_group_condition_4' => 'array',
];
```

Aggiorna `syncConditionsFromSlots()`:

```php
public function syncConditionsFromSlots(): void
{
    $this->conditions()->whereIn('group_index', [0, 1, 2, 3])->delete();

    foreach ([0, 1, 2, 3] as $index) {
        $tagSlot      = 'condition_' . ($index + 1);
        $groupSlot    = 'tag_group_condition_' . ($index + 1);

        foreach ($this->{$tagSlot} ?? [] as $tagId) {
            TagGroupCondition::create([
                'tag_group_id' => $this->id,
                'tag_id'       => (int) $tagId,
                'group_index'  => $index,
            ]);
        }

        foreach ($this->{$groupSlot} ?? [] as $refGroupId) {
            TagGroupCondition::create([
                'tag_group_id'    => $this->id,
                'ref_tag_group_id' => (int) $refGroupId,
                'group_index'     => $index,
            ]);
        }
    }
}
```

Aggiorna `storyMatches()`:

```php
public function storyMatches(Story $story): bool
{
    $groups = $this->conditions()->get()->groupBy('group_index');

    if ($groups->isEmpty()) {
        return false;
    }

    $storyTagIds = $story->tags()->pluck('tags.id');

    foreach ($groups as $conditions) {
        $tagIds         = $conditions->whereNotNull('tag_id')->pluck('tag_id');
        $refGroupIds    = $conditions->whereNotNull('ref_tag_group_id')->pluck('ref_tag_group_id');

        $matchesTag     = $storyTagIds->intersect($tagIds)->isNotEmpty();
        $matchesGroup   = TagGroup::whereIn('id', $refGroupIds)
            ->get()
            ->contains(fn ($g) => $g->stories()->where('stories.id', $story->id)->exists());

        if (! $matchesTag && ! $matchesGroup) {
            return false;
        }
    }

    return true;
}
```

Aggiorna `computeMatchingStoryIds()`:

```php
private function computeMatchingStoryIds(): array
{
    $groups = $this->conditions()->get()->groupBy('group_index');

    if ($groups->isEmpty()) {
        return [];
    }

    $query = Story::query();

    foreach ($groups as $conditions) {
        $tagIds      = $conditions->whereNotNull('tag_id')->pluck('tag_id');
        $refGroupIds = $conditions->whereNotNull('ref_tag_group_id')->pluck('ref_tag_group_id');

        // Story IDs già matchati dai TagGroup annidati
        $nestedStoryIds = TagGroup::whereIn('id', $refGroupIds)
            ->get()
            ->flatMap(fn ($g) => $g->stories()->pluck('stories.id'))
            ->unique()
            ->values();

        $query->where(function (Builder $q) use ($tagIds, $nestedStoryIds) {
            if ($tagIds->isNotEmpty()) {
                $q->orWhereHas('tags', function (Builder $inner) use ($tagIds) {
                    $inner->whereIn('tags.id', $tagIds);
                });
            }
            if ($nestedStoryIds->isNotEmpty()) {
                $q->orWhereIn('id', $nestedStoryIds);
            }
        });
    }

    return $query->pluck('id')->toArray();
}
```

- [ ] **Step 4: Verifica che i test passino**

```bash
docker exec -it php81_orchestrator php artisan test --filter=TagGroupTest
```

Expected: tutti PASS (inclusi i vecchi 11 + i 3 nuovi).

- [ ] **Step 5: Commit**

```bash
git add app/Models/TagGroup.php tests/Unit/TagGroupTest.php
git commit -m "feat(tag-group): support nested TagGroup in condition slots"
```

---

## Task 4: UI Nova — MultiSelect per TagGroup

**Files:**
- Modifica: `app/Nova/TagGroup.php`

- [ ] **Step 1: Aggiungi i MultiSelect per TagGroup nel panel `Filtri`**

In `app/Nova/TagGroup.php`, all'inizio di `fields()` aggiungi il recupero delle opzioni dei TagGroup (escludendo il gruppo corrente per evitare cicli):

```php
$tagGroupOptions = \App\Models\TagGroup::when(
        $this->resource->id,
        fn ($q) => $q->where('id', '!=', $this->resource->id)
    )
    ->orderBy('name')
    ->pluck('name', 'id')
    ->toArray();
```

Nel panel `Filtri`, dopo ogni `MultiSelect` per tag aggiungi il corrispondente per TagGroup:

```php
Panel::make('Filtri', [
    Text::make('', function () { ... })->asHtml()->hideFromIndex()->readonly(),

    MultiSelect::make('Gruppo 1 — Tag', 'condition_1')
        ->options($tagOptions)->nullable()->hideFromIndex()
        ->help('Il ticket deve avere almeno uno di questi tag.'),

    MultiSelect::make('Gruppo 1 — Tag Group', 'tag_group_condition_1')
        ->options($tagGroupOptions)->nullable()->hideFromIndex()
        ->help('Oppure appartenere a uno di questi Tag Group.'),

    MultiSelect::make('Gruppo 2 — Tag', 'condition_2')
        ->options($tagOptions)->nullable()->hideFromIndex()
        ->help('Il ticket deve avere almeno uno di questi tag.'),

    MultiSelect::make('Gruppo 2 — Tag Group', 'tag_group_condition_2')
        ->options($tagGroupOptions)->nullable()->hideFromIndex()
        ->help('Oppure appartenere a uno di questi Tag Group.'),

    MultiSelect::make('Gruppo 3 — Tag', 'condition_3')
        ->options($tagOptions)->nullable()->hideFromIndex()
        ->help('Il ticket deve avere almeno uno di questi tag.'),

    MultiSelect::make('Gruppo 3 — Tag Group', 'tag_group_condition_3')
        ->options($tagGroupOptions)->nullable()->hideFromIndex()
        ->help('Oppure appartenere a uno di questi Tag Group.'),

    MultiSelect::make('Gruppo 4 — Tag', 'condition_4')
        ->options($tagOptions)->nullable()->hideFromIndex()
        ->help('Il ticket deve avere almeno uno di questi tag.'),

    MultiSelect::make('Gruppo 4 — Tag Group', 'tag_group_condition_4')
        ->options($tagGroupOptions)->nullable()->hideFromIndex()
        ->help('Oppure appartenere a uno di questi Tag Group.'),
]),
```

- [ ] **Step 2: Aggiorna `conditionFieldsForIndex()` per mostrare anche i TagGroup**

```php
private function conditionFieldsForIndex(array $tagOptions): array
{
    $tagGroupMap = \App\Models\TagGroup::orderBy('name')->pluck('name', 'id')->toArray();
    $fields = [];

    foreach (['condition_1', 'condition_2', 'condition_3', 'condition_4'] as $i => $slot) {
        $groupSlot = 'tag_group_condition_' . ($i + 1);
        $label     = 'Gruppo ' . ($i + 1);

        $fields[] = Text::make($label, function () use ($slot, $groupSlot, $tagOptions, $tagGroupMap) {
            $tagParts   = collect($this->{$slot} ?? [])
                ->map(fn ($id) => $tagOptions[$id] ?? "#{$id}");
            $groupParts = collect($this->{$groupSlot} ?? [])
                ->map(fn ($id) => '[G] ' . ($tagGroupMap[$id] ?? "#{$id}"));

            $all = $tagParts->merge($groupParts);
            return $all->isEmpty() ? '—' : $all->implode(', ');
        })->asHtml()->onlyOnIndex();
    }

    return $fields;
}
```

- [ ] **Step 3: Verifica manuale**

1. Apri un TagGroup in Nova
2. Verifica che ogni gruppo mostri due multiselect: uno per Tag e uno per Tag Group
3. Seleziona un Tag Group come condizione, salva
4. Apri il detail — verifica che le metriche e la lista story si aggiornino includendo le story del TagGroup annidato

- [ ] **Step 4: Commit**

```bash
git add app/Nova/TagGroup.php
git commit -m "feat(nova/tag-group): add TagGroup multiselect per condition slot"
```

---

## Self-Review

### Spec coverage
- [x] TagGroup come condizione in un gruppo OR — `ref_tag_group_id` in `tag_group_conditions`, slot `tag_group_condition_1..4`
- [x] Tag e TagGroup in OR nello stesso gruppo — `computeMatchingStoryIds()` usa `orWhere`
- [x] AND tra gruppi distinti — logica `where()` per ogni group_index invariata
- [x] Cicli evitati lato UI — `$this->resource->id` escluso dalla lista TagGroup selezionabili
- [x] Sync lazy invariato — `prepareModelForDetailView` / `prepareModelForIndexView` già esistono
- [x] Metriche SAL, Tickets by Status, Tickets by Type — ereditano da `tagged()` → `stories()`, nessuna modifica

### Placeholder scan
Nessun placeholder. Ogni step ha codice completo.

### Type consistency
- `tag_group_condition_1..4` → definiti in `$fillable`, `$casts`, `syncConditionsFromSlots()`, UI Nova e `conditionFieldsForIndex()`
- `ref_tag_group_id` → in migrazione, `$fillable` di `TagGroupCondition`, `refTagGroup()`, `storyMatches()`, `computeMatchingStoryIds()`
- `computeMatchingStoryIds()` usa `Builder $q` — coerente con l'import esistente `use Illuminate\Database\Eloquent\Builder`
