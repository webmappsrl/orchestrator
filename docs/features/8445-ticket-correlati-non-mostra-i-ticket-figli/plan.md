> Ticket: oc:8445

# Ticket correlati — fonte unica su `parent_id` — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Far elencare al campo "Ticket correlati" tutti i ticket figli, eleggendo `stories.parent_id` a unica fonte di verità e abbandonando il pivot `story_story`.

**Architecture:** `Story::childStories()` passa da `belongsToMany` sul pivot a `hasMany` sulla colonna `parent_id`. Il pivot resta in database ma nessun codice lo legge o scrive: spariscono il blocco di sync in `Story::booted()` e il `->using(StoryPivot::class)`. La colonna viene blindata con un indice e un vincolo anti-auto-parentela. Cadono due comportamenti che dipendevano dal pivot: il cascade di status padre→figli e la copia della parentela in `DuplicateStory`.

**Tech Stack:** Laravel 10, Laravel Nova 4, PostgreSQL 17 + PostGIS, PHPUnit. Tutti i comandi girano nel container `php81_orchestrator`.

**Spec:** `docs/features/8445-ticket-correlati-non-mostra-i-ticket-figli/overview.md`

## Global Constraints

- Branch di lavoro: `feature/oc-8445-ticket-correlati-non-mostra-i-ticket-figli` (già creato da `develop`). **Non creare branch, non eseguire commit automatici**: i blocchi `git commit` sono istruzioni testuali per l'utente.
- Convention commit: `fix(oc:8445): ...` per le correzioni, `feat(oc:8445): ...` per le aggiunte, `docs(oc:8445): ...` per la documentazione.
- Ogni comando artisan/test va eseguito con il prefisso `docker exec php81_orchestrator`.
- I test girano sul DB di supporto `orchestrator_test` (già configurato in `phpunit.xml`). **Mai** usare `DB_DATABASE=orchestrator`.
- Ogni testo visibile all'utente va tradotto in **entrambi** `lang/it.json` e `lang/en.json`. La chiave JSON è la stringa inglese completa: cambiarla in un solo file lascia l'altra lingua sulla chiave grezza.
- La tabella `story_story` **non va droppata** e `app/Models/StoryPivot.php` **non va modificato**: sono esplicitamente out of scope.
- Il `canSee` del campo "Ticket correlati" (`empty($this->parent_id) && ! Customer`) **non va toccato**.

---

## File Structure

| File | Responsabilità dopo il ticket |
|---|---|
| `database/migrations/2026_09_02_120000_add_index_and_self_parent_check_to_stories.php` | **Create** — indice su `stories.parent_id` + vincolo `parent_id <> id` |
| `app/Models/Story.php` | **Modify** — `childStories()` come `hasMany`; rimozione sync pivot; rimozione cascade status; guardia con `ValidationException` |
| `app/Nova/Story.php` | **Modify** — campo "Ticket correlati" come `HasMany` read-only |
| `app/Nova/Actions/DuplicateStory.php` | **Modify** — il duplicato nasce senza padre e senza figli |
| `lang/it.json`, `lang/en.json` | **Modify** — help text "Parent Story" senza promessa di cascade; messaggio della guardia |
| `tests/Feature/StoryRelationshipTest.php` | **Modify** — riscritto sulla colonna `parent_id`, senza test sul cascade |
| `CLAUDE.md` | **Modify** — feature, decisioni architetturali, deprecazione `story_story` |
| `docs/features/8445-.../notes.md` | **Create** — deviazioni, decisioni, procedura di rollback |

---

### Task 1: Migration — indice e vincolo anti-auto-parentela

**Files:**
- Create: `database/migrations/2026_09_02_120000_add_index_and_self_parent_check_to_stories.php`

**Interfaces:**
- Consumes: niente (primo task).
- Produces: indice `stories_parent_id_index` e vincolo `stories_parent_id_not_self` sulla tabella `stories`. I task successivi assumono che la colonna `parent_id` sia indicizzata.

- [ ] **Step 1: Verificare lo stato di partenza del database**

Run:
```bash
docker exec php81_orchestrator php artisan tinker --execute="
\$i = DB::select(\"SELECT indexname FROM pg_indexes WHERE tablename='stories'\");
foreach(\$i as \$x){ echo \$x->indexname.PHP_EOL; }
echo 'auto-parentela: '.count(DB::select('SELECT id FROM stories WHERE parent_id = id')).PHP_EOL;"
```
Expected: un solo indice `stories_pkey`, `auto-parentela: 0`.

Se `auto-parentela` è diverso da 0 **fermarsi**: il vincolo fallirebbe. Le righe vanno bonificate prima (`UPDATE stories SET parent_id = NULL WHERE parent_id = id`) e la cosa va annotata in `notes.md`.

- [ ] **Step 2: Scrivere la migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stories', function (Blueprint $table) {
            $table->index('parent_id', 'stories_parent_id_index');
        });

        DB::statement('ALTER TABLE stories ADD CONSTRAINT stories_parent_id_not_self CHECK (parent_id IS NULL OR parent_id <> id)');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE stories DROP CONSTRAINT IF EXISTS stories_parent_id_not_self');

        Schema::table('stories', function (Blueprint $table) {
            $table->dropIndex('stories_parent_id_index');
        });
    }
};
```

- [ ] **Step 3: Eseguire la migration**

Run: `docker exec php81_orchestrator php artisan migrate`
Expected: la migration risulta eseguita senza errori.

- [ ] **Step 4: Verificare indice e vincolo**

Run:
```bash
docker exec php81_orchestrator php artisan tinker --execute="
foreach(DB::select(\"SELECT indexname FROM pg_indexes WHERE tablename='stories'\") as \$x){ echo 'INDEX '.\$x->indexname.PHP_EOL; }
foreach(DB::select(\"SELECT conname FROM pg_constraint WHERE conname='stories_parent_id_not_self'\") as \$x){ echo 'CHECK '.\$x->conname.PHP_EOL; }"
```
Expected: compaiono `INDEX stories_parent_id_index` e `CHECK stories_parent_id_not_self`.

- [ ] **Step 5: Verificare che il vincolo blocchi davvero l'auto-parentela**

Run:
```bash
docker exec php81_orchestrator php artisan tinker --execute="
try { DB::statement('UPDATE stories SET parent_id = id WHERE id = (SELECT MIN(id) FROM stories)'); echo 'ERRORE: il vincolo non ha bloccato'.PHP_EOL; }
catch (\Throwable \$e) { echo 'OK: vincolo attivo'.PHP_EOL; }"
```
Expected: `OK: vincolo attivo`.

- [ ] **Step 6: Applicare la migration anche al DB di test**

Run: `docker exec php81_orchestrator bash -c "DB_DATABASE=orchestrator_test php artisan migrate"`
Expected: migration eseguita.

- [ ] **Step 7: Commit**

```bash
git add database/migrations/2026_09_02_120000_add_index_and_self_parent_check_to_stories.php
git commit -m "feat(oc:8445): indice su stories.parent_id e vincolo anti-auto-parentela"
```

---

### Task 2: `childStories()` come `hasMany` — test di regressione del bug

**Files:**
- Modify: `app/Models/Story.php:417-420` (metodo `childStories()`)
- Test: `tests/Feature/StoryRelationshipTest.php`

**Interfaces:**
- Consumes: Task 1 (indice su `parent_id`).
- Produces: `Story::childStories()` restituisce una `Illuminate\Database\Eloquent\Relations\HasMany`. Da qui in poi `attach()`/`sync()`/`detach()` **non sono più disponibili** su questa relazione: i task successivi collegano un figlio scrivendo `$child->parent_id = $parent->id; $child->save();`.

- [ ] **Step 1: Scrivere il test che riproduce il bug**

Sostituire **l'intero contenuto** di `tests/Feature/StoryRelationshipTest.php` con:

```php
<?php

namespace Tests\Feature;

use App\Models\Story;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StoryRelationshipTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function child_stories_e_una_relazione_hasmany_sulla_colonna_parent_id()
    {
        $parent = Story::create(['name' => 'Parent Story']);

        $this->assertInstanceOf(HasMany::class, $parent->childStories());
        $this->assertEquals('parent_id', $parent->childStories()->getForeignKeyName());
    }

    /** @test */
    public function il_padre_elenca_i_figli_collegati_solo_via_colonna_parent_id()
    {
        // Riproduce oc:8445: i figli sono collegati dalla sola colonna,
        // senza alcuna riga nel pivot story_story.
        $parent = Story::create(['name' => 'Parent Story']);
        $child1 = Story::create(['name' => 'Child 1', 'parent_id' => $parent->id]);
        $child2 = Story::create(['name' => 'Child 2', 'parent_id' => $parent->id]);

        $this->assertDatabaseCount('story_story', 0);

        $ids = $parent->fresh()->childStories->pluck('id')->sort()->values()->all();

        $this->assertEquals([$child1->id, $child2->id], $ids);
    }

    /** @test */
    public function il_figlio_referenzia_correttamente_il_padre()
    {
        $parent = Story::create(['name' => 'Parent Story']);
        $child = Story::create(['name' => 'Child Story', 'parent_id' => $parent->id]);

        $this->assertEquals($parent->id, $child->fresh()->parentStory->id);
    }

    /** @test */
    public function cancellando_il_padre_i_figli_restano_senza_padre()
    {
        $parent = Story::create(['name' => 'Parent Story']);
        $child = Story::create(['name' => 'Child Story', 'parent_id' => $parent->id]);

        $parent->delete();

        $this->assertNull($child->fresh()->parent_id);
    }

    /** @test */
    public function nessuna_riga_viene_piu_scritta_nel_pivot_story_story()
    {
        $parent = Story::create(['name' => 'Parent Story']);
        $child = Story::create(['name' => 'Child Story']);

        $child->parent_id = $parent->id;
        $child->save();

        $this->assertDatabaseCount('story_story', 0);
    }
}
```

- [ ] **Step 2: Eseguire i test per verificare che falliscano**

Run: `docker exec php81_orchestrator php artisan test --filter=StoryRelationshipTest`
Expected: FAIL — `child_stories_e_una_relazione_hasmany...` fallisce perché la relazione è ancora `BelongsToMany`, e `il_padre_elenca_i_figli...` restituisce un array vuoto (è esattamente il bug oc:8445).

- [ ] **Step 3: Convertire la relazione**

In `app/Models/Story.php`, sostituire:

```php
    // Relazione per ottenere le storie figlie
    public function childStories()
    {
        return $this->belongsToMany(Story::class, 'story_story', 'parent_id', 'child_id')->using(StoryPivot::class);
    }
```

con:

```php
    /**
     * Ticket figli.
     *
     * Fonte unica: la colonna stories.parent_id.
     * Il pivot story_story e' DEPRECATO (oc:8445) e non va piu' usato: si era
     * disallineato dalla colonna su 15 relazioni su 66 perche' la sua sincronizzazione
     * girava solo con un utente autenticato e solo in update, mai in create.
     */
    public function childStories(): HasMany
    {
        return $this->hasMany(Story::class, 'parent_id');
    }
```

Aggiungere l'import mancante in cima al file, accanto agli altri `use` di relazioni:

```php
use Illuminate\Database\Eloquent\Relations\HasMany;
```

- [ ] **Step 4: Eseguire i test**

Run: `docker exec php81_orchestrator php artisan test --filter=StoryRelationshipTest`
Expected: PASS su tutti e 5 i test.

- [ ] **Step 5: Verificare sul DB reale che le 15 relazioni siano ora visibili**

Run:
```bash
docker exec php81_orchestrator php artisan tinker --execute="
\$s = App\Models\Story::find(8180);
echo 'figli di 8180: '.\$s->childStories()->count().PHP_EOL;
echo 'totale relazioni visibili: '.App\Models\Story::whereNotNull('parent_id')->count().PHP_EOL;"
```
Expected: `figli di 8180: 1` (era 0 prima del fix) e `totale relazioni visibili: 66` (erano 51).

- [ ] **Step 6: Commit**

```bash
git add app/Models/Story.php tests/Feature/StoryRelationshipTest.php
git commit -m "fix(oc:8445): childStories legge la colonna parent_id, non il pivot"
```

---

### Task 3: Rimozione del cascade di status e della sync del pivot

**Files:**
- Modify: `app/Models/Story.php:253-303` (hook `static::updated`)
- Test: `tests/Feature/StoryRelationshipTest.php`

**Interfaces:**
- Consumes: Task 2 (`childStories()` è una `HasMany`).
- Produces: `Story::booted()` non contiene più alcun riferimento a `story_story`. Lo status di un figlio è indipendente da quello del padre.

- [ ] **Step 1: Scrivere il test che pretende l'indipendenza degli status**

Aggiungere in coda a `tests/Feature/StoryRelationshipTest.php`, prima della graffa di chiusura della classe:

```php
    /** @test */
    public function lo_status_del_padre_non_si_propaga_piu_ai_figli()
    {
        $user = \App\Models\User::factory()->create();
        $this->actingAs($user);

        $parent = Story::create(['name' => 'Parent Story', 'status' => 'new']);
        $child = Story::create(['name' => 'Child Story', 'status' => 'new', 'parent_id' => $parent->id]);

        $parent->update(['status' => 'done']);

        $this->assertEquals('new', $child->fresh()->status);
    }
```

- [ ] **Step 2: Eseguire il test per verificare che fallisca**

Run: `docker exec php81_orchestrator php artisan test --filter=lo_status_del_padre_non_si_propaga`
Expected: FAIL — il figlio risulta `done`, perché il cascade è ancora attivo (e ora, con la relazione corretta, lo raggiunge davvero).

- [ ] **Step 3: Rimuovere cascade e sync del pivot**

In `app/Models/Story.php`, sostituire **l'intero blocco** che inizia con `static::updated(function (Story $story) {` e comprende `$tablePivot`, il `foreach` sul cascade e tutto il ramo `if ($story->wasChanged('parent_id'))` con il relativo `try/catch`, con:

```php
        static::updated(function (Story $story) {
            //
        });
```

Se dopo la rimozione l'hook `static::updated` risulta vuoto, **eliminarlo del tutto** invece di lasciarne uno con corpo vuoto.

Attenzione: rimuovere **solo** il blocco che tocca il pivot e il cascade. Il resto di `booted()` (hook `created`, `saving`, notifiche di cambio status altrove nel file) resta invariato.

- [ ] **Step 4: Verificare che nessun riferimento al pivot sopravviva nel modello**

Run: `grep -n "story_story\|StoryPivot\|tablePivot" app/Models/Story.php`
Expected: nessun risultato.

- [ ] **Step 5: Eseguire l'intera suite dei test di relazione**

Run: `docker exec php81_orchestrator php artisan test --filter=StoryRelationshipTest`
Expected: PASS su tutti e 6 i test. Nota: la suite deve girare molto più velocemente di prima (il vecchio test sul cascade impiegava da solo ~66s per via delle mail).

- [ ] **Step 6: Commit**

```bash
git add app/Models/Story.php tests/Feature/StoryRelationshipTest.php
git commit -m "fix(oc:8445): rimuove cascade status padre-figli e sync del pivot"
```

---

### Task 4: Guardia "un figlio non può avere figli" come errore di validazione

**Files:**
- Modify: `app/Models/Story.php:342-347` (hook `static::saving`)
- Modify: `lang/it.json`, `lang/en.json`
- Test: `tests/Feature/StoryRelationshipTest.php`

**Interfaces:**
- Consumes: Task 2 (`childStories()` è una `HasMany`, quindi la guardia valuta ora l'insieme completo delle relazioni).
- Produces: la guardia solleva `Illuminate\Validation\ValidationException` sul campo `parent_id`.

- [ ] **Step 1: Scrivere il test**

Aggiungere in coda a `tests/Feature/StoryRelationshipTest.php`:

```php
    /** @test */
    public function una_storia_con_figli_non_puo_diventare_figlia_e_lancia_un_errore_di_validazione()
    {
        $grandparent = Story::create(['name' => 'Grandparent']);
        $parent = Story::create(['name' => 'Parent']);
        Story::create(['name' => 'Child', 'parent_id' => $parent->id]);

        $this->expectException(\Illuminate\Validation\ValidationException::class);

        $parent->parent_id = $grandparent->id;
        $parent->save();
    }
```

- [ ] **Step 2: Eseguire il test per verificare che fallisca**

Run: `docker exec php81_orchestrator php artisan test --filter=una_storia_con_figli_non_puo_diventare_figlia`
Expected: FAIL — viene sollevata una `\Exception` generica, non una `ValidationException`.

- [ ] **Step 3: Sostituire l'eccezione nuda**

In `app/Models/Story.php`, sostituire:

```php
        static::saving(function ($story) {
            if ($story->parent_id && $story->childStories()->exists()) {
                // Lancia un'eccezione o rifiuta il salvataggio
                throw new \Exception('Una storia che è figlia non può avere figli.');
            }
        });
```

con:

```php
        static::saving(function ($story) {
            if ($story->parent_id && $story->childStories()->exists()) {
                throw ValidationException::withMessages([
                    'parent_id' => __('A ticket that has child tickets cannot itself become a child ticket.'),
                ]);
            }
        });
```

Aggiungere l'import in cima al file:

```php
use Illuminate\Validation\ValidationException;
```

- [ ] **Step 4: Aggiungere la traduzione in entrambe le lingue**

In `lang/en.json`, aggiungere la coppia (rispettando l'ordine alfabetico del file):

```json
  "A ticket that has child tickets cannot itself become a child ticket.": "A ticket that has child tickets cannot itself become a child ticket.",
```

In `lang/it.json`:

```json
  "A ticket that has child tickets cannot itself become a child ticket.": "Un ticket che ha ticket figli non può a sua volta diventare un ticket figlio.",
```

- [ ] **Step 5: Verificare che i due file JSON siano validi**

Run: `docker exec php81_orchestrator php -r "json_decode(file_get_contents('lang/it.json'), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents('lang/en.json'), true, 512, JSON_THROW_ON_ERROR); echo 'JSON validi'.PHP_EOL;"`
Expected: `JSON validi`.

- [ ] **Step 6: Eseguire i test**

Run: `docker exec php81_orchestrator php artisan test --filter=StoryRelationshipTest`
Expected: PASS su tutti e 7 i test.

- [ ] **Step 7: Commit**

```bash
git add app/Models/Story.php lang/it.json lang/en.json tests/Feature/StoryRelationshipTest.php
git commit -m "fix(oc:8445): guardia figlio-con-figli come ValidationException"
```

---

### Task 5: Campo Nova "Ticket correlati" come `HasMany`

**Files:**
- Modify: `app/Nova/Story.php:195-200`
- Test: `tests/Feature/StoryChildFieldTest.php` (Create)

**Interfaces:**
- Consumes: Task 2 (`childStories()` è una `HasMany`).
- Produces: il campo Nova è un `HasMany` read-only. `->searchable()`, `->filterable()` e `->nullable()` **non sono più applicati** — non esistono su `HasMany` e la loro presenza produce un `BadMethodCallException`.

- [ ] **Step 1: Scrivere il test di regressione sul 500**

Creare `tests/Feature/StoryChildFieldTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\Story;
use App\Models\User;
use App\Nova\Story as NovaStory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Nova\Http\Requests\NovaRequest;
use Tests\TestCase;

class StoryChildFieldTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function i_campi_di_dettaglio_si_costruiscono_senza_errori()
    {
        // Regressione oc:8445: searchable()/filterable() non esistono su HasMany
        // e la loro presenza produceva un BadMethodCallException, cioe' un 500
        // sul detail di OGNI ticket.
        $user = User::factory()->create();
        $this->actingAs($user);

        $parent = Story::create(['name' => 'Parent']);
        Story::create(['name' => 'Child', 'parent_id' => $parent->id]);

        $request = NovaRequest::create('/', 'GET');
        $request->setUserResolver(fn () => $user);

        $resource = new NovaStory($parent);
        $fields = $resource->detailFields($request);

        $this->assertNotEmpty($fields);
    }

    /** @test */
    public function il_campo_ticket_correlati_e_un_hasmany()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $parent = Story::create(['name' => 'Parent']);

        $request = NovaRequest::create('/', 'GET');
        $request->setUserResolver(fn () => $user);

        $resource = new NovaStory($parent);

        $found = collect($resource->detailFields($request))
            ->flatten()
            ->first(fn ($field) => ($field->attribute ?? null) === 'childStories');

        $this->assertNotNull($found, 'Il campo childStories non e" presente nel detail.');
        $this->assertInstanceOf(\Laravel\Nova\Fields\HasMany::class, $found);
    }
}
```

- [ ] **Step 2: Eseguire il test per verificare che fallisca**

Run: `docker exec php81_orchestrator php artisan test --filter=StoryChildFieldTest`
Expected: FAIL — il campo è ancora un `BelongsToMany`.

- [ ] **Step 3: Convertire il campo**

In `app/Nova/Story.php`, sostituire:

```php
            BelongsToMany::make(__('Child Stories'), 'childStories', Story::class)
                ->nullable()
                ->searchable()
                ->canSee(function ($request) {
                    return empty($this->parent_id) && ! $request->user()->hasRole(UserRole::Customer);
                })->filterable(),
```

con:

```php
            // oc:8445 - HasMany di sola lettura sulla colonna parent_id.
            // searchable()/filterable() NON esistono su HasMany: aggiungerli
            // solleva un BadMethodCallException, cioe' un 500 sul detail.
            HasMany::make(__('Child Stories'), 'childStories', Story::class)
                ->canSee(function ($request) {
                    return empty($this->parent_id) && ! $request->user()->hasRole(UserRole::Customer);
                }),
```

- [ ] **Step 4: Rimuovere l'import ora inutilizzato**

Run: `grep -n "BelongsToMany" app/Nova/Story.php`
Se l'unico risultato è la riga `use Laravel\Nova\Fields\BelongsToMany;`, rimuoverla. Se compaiono altri usi, lasciarla.

- [ ] **Step 5: Eseguire i test**

Run: `docker exec php81_orchestrator php artisan test --filter=StoryChildFieldTest`
Expected: PASS su entrambi i test.

- [ ] **Step 6: Verifica manuale nel browser**

Aprire `http://localhost:8099/resources/stories/8180` e controllare:
- la pagina si carica senza errore 500;
- la sezione "Ticket correlati" elenca il ticket 8414;
- non è presente alcun bottone "Attach".

Aprire poi `http://localhost:8099/resources/stories/8414` (che è un figlio) e controllare che la sezione "Ticket correlati" **non** compaia e che "Ticket padre" mostri 8180.

- [ ] **Step 7: Commit**

```bash
git add app/Nova/Story.php tests/Feature/StoryChildFieldTest.php
git commit -m "fix(oc:8445): campo Ticket correlati come HasMany read-only"
```

---

### Task 6: `DuplicateStory` — il duplicato nasce isolato

**Files:**
- Modify: `app/Nova/Actions/DuplicateStory.php:39,45-46`
- Test: `tests/Feature/DuplicateStoryTest.php` (Create)

**Interfaces:**
- Consumes: Task 2 (`childStories()` è una `HasMany`, quindi `sync()` non è più disponibile e la riga attuale andrebbe comunque in errore).
- Produces: il duplicato ha `parent_id` a `null` e nessun figlio.

- [ ] **Step 1: Scrivere il test**

Creare `tests/Feature/DuplicateStoryTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\Story;
use App\Models\User;
use App\Nova\Actions\DuplicateStory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Laravel\Nova\Fields\ActionFields;
use Tests\TestCase;

class DuplicateStoryTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function il_duplicato_non_eredita_il_padre_ne_i_figli()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $grandparent = Story::create(['name' => 'Grandparent']);
        $original = Story::create(['name' => 'Original', 'parent_id' => $grandparent->id]);

        $before = Story::max('id');

        (new DuplicateStory())->handle(
            new ActionFields(new Collection(), new Collection()),
            new Collection([$original])
        );

        $duplicate = Story::where('id', '>', $before)->firstOrFail();

        $this->assertNull($duplicate->parent_id, 'Il duplicato non deve ereditare il padre.');
        $this->assertCount(0, $duplicate->childStories, 'Il duplicato non deve avere figli.');

        // Il ticket di un terzo non deve essere stato toccato.
        $this->assertEquals([$original->id], $grandparent->fresh()->childStories->pluck('id')->all());
    }
}
```

- [ ] **Step 2: Eseguire il test per verificare che fallisca**

Run: `docker exec php81_orchestrator php artisan test --filter=DuplicateStoryTest`
Expected: FAIL — il duplicato eredita `parent_id` e compare tra i figli di `$grandparent`, oppure l'azione va in errore su `childStories()->sync()`.

- [ ] **Step 3: Rimuovere l'ereditarietà della parentela**

In `app/Nova/Actions/DuplicateStory.php`, rimuovere la riga:

```php
            $newStory->parentStory()->associate($story->parentStory);
```

e le due righe:

```php
            $childStoryIds = $story->childStories->pluck('id')->toArray();
            $newStory->childStories()->sync($childStoryIds);
```

Impostare esplicitamente `parent_id` a `null` subito dopo la creazione, perché `Story::create($story->toArray())` copia anche quel campo. Sostituire:

```php
            $newStory = Story::create($story->toArray());
            $newStory->status = StoryStatus::New->value;
            $newStory->saveQuietly();
```

con:

```php
            $newStory = Story::create($story->toArray());
            $newStory->status = StoryStatus::New->value;
            // oc:8445 - il duplicato nasce isolato: nessun padre, nessun figlio.
            $newStory->parent_id = null;
            $newStory->saveQuietly();
```

Il resto del metodo (`developer`, `tester`, `participants`, `tags`) resta invariato.

- [ ] **Step 4: Eseguire il test**

Run: `docker exec php81_orchestrator php artisan test --filter=DuplicateStoryTest`
Expected: PASS.

- [ ] **Step 5: Verificare che nessun riferimento a childStories sopravviva nell'azione**

Run: `grep -n "childStories\|parentStory" app/Nova/Actions/DuplicateStory.php`
Expected: nessun risultato.

- [ ] **Step 6: Commit**

```bash
git add app/Nova/Actions/DuplicateStory.php tests/Feature/DuplicateStoryTest.php
git commit -m "fix(oc:8445): il duplicato di un ticket nasce senza padre e senza figli"
```

---

### Task 7: Help text del campo "Parent Story"

**Files:**
- Modify: `app/Nova/Story.php:250` (parametro di `->help()`)
- Modify: `lang/it.json`, `lang/en.json`

**Interfaces:**
- Consumes: Task 3 (il cascade non esiste più, quindi il testo attuale è falso).
- Produces: nuova chiave di traduzione per l'help text.

- [ ] **Step 1: Individuare la chiave attuale**

Run: `grep -n "Here you can attach the ticket" app/Nova/Story.php lang/it.json lang/en.json`
Expected: una occorrenza per ciascuno dei tre file.

- [ ] **Step 2: Sostituire la chiave nel codice**

In `app/Nova/Story.php`, dentro `fieldsInEdit()`, sostituire l'intero argomento di `->help(...)` del campo `parentStory` con:

```php
                ->help(__('Here you can attach the main ticket for this issue. If multiple tickets share the same issue, attach the main ticket to all of them. You can find it by searching for its title. The status of each ticket remains independent: changing the status of the main ticket does not change the status of the related ones.')),
```

- [ ] **Step 3: Sostituire la chiave in `lang/en.json`**

Rimuovere la vecchia riga (quella che inizia con `"Here you can attach the ticket that has the same issue.`) e inserire:

```json
  "Here you can attach the main ticket for this issue. If multiple tickets share the same issue, attach the main ticket to all of them. You can find it by searching for its title. The status of each ticket remains independent: changing the status of the main ticket does not change the status of the related ones.": "Here you can attach the main ticket for this issue. If multiple tickets share the same issue, attach the main ticket to all of them. You can find it by searching for its title. The status of each ticket remains independent: changing the status of the main ticket does not change the status of the related ones.",
```

- [ ] **Step 4: Sostituire la chiave in `lang/it.json`**

Rimuovere la vecchia riga corrispondente e inserire:

```json
  "Here you can attach the main ticket for this issue. If multiple tickets share the same issue, attach the main ticket to all of them. You can find it by searching for its title. The status of each ticket remains independent: changing the status of the main ticket does not change the status of the related ones.": "Qui puoi allegare il ticket principale per questo problema. Se più ticket condividono lo stesso problema, allega il ticket principale a tutti. Puoi trovarlo cercando il suo titolo. Lo stato di ogni ticket resta indipendente: cambiare lo stato del ticket principale non modifica lo stato di quelli correlati.",
```

- [ ] **Step 5: Verificare che la vecchia chiave sia sparita ovunque**

Run: `grep -rn "when the main ticket status changes" app/ lang/`
Expected: nessun risultato.

- [ ] **Step 6: Verificare i JSON e che entrambe le lingue abbiano la nuova chiave**

Run:
```bash
docker exec php81_orchestrator php -r "
\$it = json_decode(file_get_contents('lang/it.json'), true, 512, JSON_THROW_ON_ERROR);
\$en = json_decode(file_get_contents('lang/en.json'), true, 512, JSON_THROW_ON_ERROR);
\$k = 'Here you can attach the main ticket for this issue. If multiple tickets share the same issue, attach the main ticket to all of them. You can find it by searching for its title. The status of each ticket remains independent: changing the status of the main ticket does not change the status of the related ones.';
echo 'it: '.(isset(\$it[\$k]) ? 'OK' : 'MANCANTE').PHP_EOL;
echo 'en: '.(isset(\$en[\$k]) ? 'OK' : 'MANCANTE').PHP_EOL;"
```
Expected: `it: OK` e `en: OK`.

- [ ] **Step 7: Verifica manuale**

Aprire `http://localhost:8099/resources/stories/8414/edit` e controllare che sotto il campo "Ticket padre" compaia il testo italiano nuovo, senza la promessa sul cambio di stato.

- [ ] **Step 8: Commit**

```bash
git add app/Nova/Story.php lang/it.json lang/en.json
git commit -m "docs(oc:8445): help text Parent Story senza promessa di cascade"
```

---

### Task 8: Verifica finale e documentazione

**Files:**
- Create: `docs/features/8445-ticket-correlati-non-mostra-i-ticket-figli/notes.md`
- Modify: `CLAUDE.md`

**Interfaces:**
- Consumes: tutti i task precedenti.
- Produces: documentazione della feature e procedura di rollback.

- [ ] **Step 1: Eseguire l'intera suite di test**

Run: `docker exec php81_orchestrator php artisan test`
Expected: nessun test fallito. Se emergono rotture in test non toccati da questo ticket, annotarle in `notes.md` prima di intervenire.

- [ ] **Step 2: Rieseguire la query di invariante sul DB reale**

Run:
```bash
docker exec php81_orchestrator php artisan tinker --execute="
echo 'padre-e-figlio: '.count(DB::select('SELECT s.id FROM stories s WHERE s.parent_id IS NOT NULL AND EXISTS (SELECT 1 FROM stories c WHERE c.parent_id = s.id)')).PHP_EOL;
echo 'auto-parentela: '.count(DB::select('SELECT id FROM stories WHERE parent_id = id')).PHP_EOL;"
```
Expected: entrambi `0`. Questa verifica va ripetuta **al momento del deploy**, non solo qui.

- [ ] **Step 3: Verificare che il pivot non venga più scritto**

Run:
```bash
docker exec php81_orchestrator php artisan tinker --execute="
echo 'righe pivot: '.DB::table('story_story')->count().PHP_EOL;"
```
Expected: `51`, invariato rispetto a prima del ticket.

- [ ] **Step 4: Scrivere `notes.md`**

Creare il file con questa struttura, compilando le sezioni con quanto realmente accaduto:

```markdown
> Ticket: oc:8445

# Notes — Ticket correlati non mostra i ticket figli

## Deviazioni dal piano

## Bug trovati

## Decisioni

- La diagnosi iniziale del ticket ("condizione di visibilità errata sul `canSee`") si è rivelata sbagliata: il `canSee` funziona, la causa era la doppia fonte di verità colonna/pivot.
- Il cascade di status padre→figli è stato smantellato. In fase di challenge l'ipotesi è stata riaperta (valutato di mantenerlo con un avviso preventivo sul campo Stato quando il ticket ha figli) e richiusa: si resta sullo smantellamento completo, senza avviso.
- Perdita accettata: `searchable()` e `filterable()` sul campo "Ticket correlati" — non esistono su `HasMany` e la loro presenza produce un 500.
- Perdita accettata: il cascade eseguiva `$child->save()`, quindi generava anche StoryLog e notifiche. Chi lavora su un figlio non riceve più alcun segnale quando il padre cambia stato. Nessun rimpiazzo previsto.

## Procedura di rollback

Il rollback è **lossy** e la perdita cresce nel tempo: dal deploy in poi nessuno scrive più su `story_story`, quindi ogni nuova relazione padre-figlio esiste solo in colonna. Un `git revert` da solo renderebbe invisibili tutte le relazioni create nel frattempo — cioè ripresenterebbe il bug oc:8445 su un insieme più grande e in silenzio.

Un rollback corretto richiede, **prima** di ripristinare il codice:

```sql
INSERT INTO story_story (parent_id, child_id, created_at, updated_at)
SELECT parent_id, id, NOW(), NOW()
FROM stories
WHERE parent_id IS NOT NULL
ON CONFLICT (parent_id, child_id) DO NOTHING;
```

Poi:
- `php artisan migrate:rollback --step=1` per rimuovere indice e vincolo;
- ripristinare le due chiavi di traduzione dell'help text in `it.json` e `en.json`, altrimenti la UI dichiara stati indipendenti mentre il codice li cascada di nuovo.

## Follow-up

- Drop fisico di `story_story` e rimozione di `app/Models/StoryPivot.php` (i cui hook `saving`/`deleting` restano codice vivo) — ticket dedicato dopo un periodo di osservazione.
- Nova Action "Collega ticket figlio" per rimpiazzare il bottone "Attach", se l'assenza si fa sentire.
- Blocco dei cicli a due passaggi (A padre di B, B padre di A) e filtro dei candidati nel campo "Parent Story".
- Notifica ai figli quando il padre cambia stato, in sostituzione del cascade rimosso.
- **oc:8421 (rollup ore padre-figlio) dipende da questo ticket**: costruito sulla relazione precedente avrebbe sommato 51 collegamenti su 66, con totali silenziosamente più bassi del vero.
```

- [ ] **Step 5: Aggiornare `CLAUDE.md`**

Aggiungere in coda alla tabella della sezione `## Feature disponibili`:

```markdown
| Fix "Ticket correlati" vuoto — fonte unica su `parent_id` | oc:8445 | `app/Models/Story.php`, `app/Nova/Story.php`, `app/Nova/Actions/DuplicateStory.php`, `database/migrations/2026_09_02_120000_add_index_and_self_parent_check_to_stories.php`, `lang/it.json`, `lang/en.json`, `tests/Feature/StoryRelationshipTest.php`, `tests/Feature/StoryChildFieldTest.php`, `tests/Feature/DuplicateStoryTest.php` | `childStories()` da `belongsToMany` sul pivot `story_story` a `hasMany` su `stories.parent_id`: il pivot si era disallineato su 15 relazioni su 66. Pivot **deprecato** ma non droppato. Rimossi il cascade di status padre→figli e l'ereditarietà della parentela in `DuplicateStory`; campo Nova read-only senza `searchable`/`filterable`; indice + vincolo anti-auto-parentela |
```

Aggiungere in cima alla sezione `## Decisioni architetturali`:

```markdown
### Fix "Ticket correlati" vuoto — fonte unica su `parent_id` (oc:8445)
- **La tabella pivot `story_story` è DEPRECATA: non leggerla, non scriverla, non ricollegarla.** È rimasta in database con 51 righe stantie e `app/Models/StoryPivot.php` esiste ancora con i suoi hook `saving()`/`deleting()` vivi (scrivono e azzerano `stories.parent_id`), ma nessun codice li innesca più. La sola fonte di verità della relazione padre-figlio è la colonna `stories.parent_id`. Il drop fisico è rinviato a un ticket dedicato.
- **Perché il pivot si era disallineato** (15 relazioni su 66 mancanti, nessuna in eccesso): la sync viveva in `Story::booted()` → `static::updated` con tre difetti sommati — racchiusa in `if (auth()->user())` (nessuna scrittura da comandi, job o seed la raggiungeva), presente solo su `updated` e **mai** su `created` (una story creata già con `parent_id` non entrava mai nel pivot), e con `catch` silenzioso (`$e;`). Il bug era invisibile perché `parentStory()` leggeva la colonna e `childStories()` il pivot: dal figlio il legame si vedeva, dal padre no.
- **`searchable()` e `filterable()` NON esistono su `Laravel\Nova\Fields\HasMany`** (esistono su `BelongsToMany`): lasciarli nella catena durante la conversione del campo produce un `BadMethodCallException`, cioè un **500 sul detail di ogni Story**, non solo dei padri. Perdita accettata: niente più ricerca dentro il campo né filtro sull'index. Coperto da `tests/Feature/StoryChildFieldTest.php`.
- **Il cascade di status padre→figli è stato rimosso del tutto.** Faceva `$child->save()`, quindi non allineava solo lo stato: generava anche StoryLog, ricalcolo `hours` e notifiche a developer/tester del figlio. Da ora chi lavora su un figlio non riceve **alcun** segnale quando il padre cambia stato, e l'help text del campo "Parent Story" è stato riscritto in entrambe le lingue per non promettere più il contrario. L'ipotesi di mantenerlo con un avviso preventivo è stata valutata in challenge e scartata.
- **`DuplicateStory` non copia più né i figli né il padre**, e forza `parent_id = null` dopo `Story::create($story->toArray())` (che quel campo lo copia). Prima della conversione l'ereditarietà del padre era invisibile perché il pivot non veniva scritto in `created`; dopo, il duplicato sarebbe comparso automaticamente nell'elenco figli di un terzo ticket mai aperto dall'utente.
- **L'indice `stories_parent_id_index` non è una seconda fonte di verità**: PostgreSQL non indicizza automaticamente le colonne con FK, e l'unico indice di `stories` era `stories_pkey`. Il pivot forniva implicitamente un indice con il suo `unique(parent_id, child_id)`; senza, il passaggio alla colonna avrebbe peggiorato il piano di query su una tabella di 7.537 righe, letta per riga in campi Nova non eager-loaded (`fieldTrait.php`).
- **Il rollback è lossy** e sembra banale proprio perché non c'è migration di dati: vedi la procedura con la SQL di ripopolamento del pivot in `docs/features/8445-ticket-correlati-non-mostra-i-ticket-figli/notes.md`. Da eseguire **prima** del `git revert`, non dopo.
- **Verificato sui dati di produzione (2026-09-02): 0 story sono contemporaneamente padre e figlio, 0 sono padri di sé stesse.** L'invariante non era però garantita da nulla — `parent_id` è in `$fillable` e nella whitelist di `Api/StoryController::update()`, e il campo Nova non filtra i candidati. Ora il vincolo `stories_parent_id_not_self` copre l'auto-parentela; i cicli a due passaggi (A→B→A) restano possibili ma producono una `ValidationException` recuperabile invece di una `\Exception` nuda che rendeva la story **impossibile da salvare per sempre**, anche dai job in coda e dai comandi schedulati.
```

- [ ] **Step 6: Commit finale**

```bash
git add docs/features/8445-ticket-correlati-non-mostra-i-ticket-figli/ CLAUDE.md
git commit -m "docs(oc:8445): notes, procedura di rollback e aggiornamento CLAUDE.md"
```
