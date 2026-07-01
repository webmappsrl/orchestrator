> Ticket: oc:8137

# Ensure StoryLog is always created on story status change — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Garantire che ogni cambio di campo su una Story produca sempre una riga StoryLog, indipendentemente da come viene chiamato il save (`save()`, `saveQuietly()`, CLI, HTTP).

**Architecture:** La logica di creazione del StoryLog viene spostata dall'observer (`StoryObserver::createStoryLog()`) a un override del metodo `save()` nel modello `Story`. L'override cattura i dirty fields prima di `parent::save()`, poi crea il log dentro una `DB::transaction()` che avvolge entrambe le operazioni. I comandi che usavano `saveQuietly()` per cambi di status vengono convertiti a `save()` normale; `SlackRevertProgressCommand` mantiene `saveQuietly()` (per sopprimere email/calendario) ma rimuove il log manuale ora ridondante.

**Tech Stack:** Laravel 10, Eloquent ORM, PostgreSQL, PHPUnit, `DatabaseTransactions` trait nei test.

## Global Constraints

- Tutti i test girano su `orchestrator_test` DB: `docker exec php81_orchestrator php artisan test --filter=ClassName`
- Tutti i commit usano la convention `fix(oc:8137): ...`
- Nessun commit automatico — i commit nel piano sono istruzioni per l'utente
- Usare `DatabaseTransactions` in tutti i test Feature (rollback automatico)
- Il system user è `orchestrator_artisan@webmapp.it` (esiste via `PhpArtisanUserSeeder`)

---

## File Map

| File | Azione |
|------|--------|
| `app/Models/Story.php` | Aggiungere override `save()` |
| `app/Observers/StoryObserver.php` | Rimuovere chiamata `$this->createStoryLog($story)` dall'hook `updated` e il metodo privato `createStoryLog()` |
| `app/Console/Commands/AutoUpdateStoryStatus.php` | `saveQuietly()` → `save()` |
| `app/Console/Commands/MoveScrumStoriesInDoneCommand.php` | `saveQuietly()` → `save()` |
| `app/Console/Commands/SlackRevertProgressCommand.php` | Rimuovere `StoryLog::create()` manuale |
| `app/Console/Commands/SendWaitingStoryReminder.php` | `user_id => 1` → `orchestrator_artisan@webmapp.it` |
| `app/Nova/Actions/SetMilestoneEpicsToDone.php` | Aggiungere `@deprecated` |
| `tests/Feature/StoryLogOverrideTest.php` | Nuovo — testa override `save()` e `saveQuietly()` |
| `tests/Feature/StoryLogCommandsTest.php` | Nuovo — test regressione per tutti i comandi |

---

### Task 1: Override `Story::save()` con creazione StoryLog atomica

**Files:**
- Modify: `app/Models/Story.php`
- Test: `tests/Feature/StoryLogOverrideTest.php`

**Interfaces:**
- Produces: `Story::save(array $options = [])` — override che crea StoryLog atomicamente

- [ ] **Step 1: Crea il file di test**

```php
<?php

namespace Tests\Feature;

use App\Enums\StoryStatus;
use App\Enums\StoryType;
use App\Models\Story;
use App\Models\StoryLog;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class StoryLogOverrideTest extends TestCase
{
    use DatabaseTransactions;

    private function makeStory(): Story
    {
        return Story::factory()->create([
            'status' => StoryStatus::New->value,
            'type'   => StoryType::Helpdesk->value,
        ]);
    }

    /** @test */
    public function save_creates_story_log_on_field_change(): void
    {
        $story = $this->makeStory();
        $logsBefore = StoryLog::where('story_id', $story->id)->count();

        $story->status = StoryStatus::Assigned->value;
        $story->save();

        $this->assertGreaterThan($logsBefore, StoryLog::where('story_id', $story->id)->count());
        $log = StoryLog::where('story_id', $story->id)->latest()->first();
        $this->assertEquals(StoryStatus::Assigned->value, $log->changes['status']);
    }

    /** @test */
    public function save_quietly_also_creates_story_log(): void
    {
        $story = $this->makeStory();
        $logsBefore = StoryLog::where('story_id', $story->id)->count();

        $story->status = StoryStatus::Progress->value;
        $story->saveQuietly();

        $this->assertGreaterThan($logsBefore, StoryLog::where('story_id', $story->id)->count());
        $log = StoryLog::where('story_id', $story->id)->latest()->first();
        $this->assertEquals(StoryStatus::Progress->value, $log->changes['status']);
    }

    /** @test */
    public function save_without_changes_does_not_create_story_log(): void
    {
        $story = $this->makeStory();
        $logsBefore = StoryLog::where('story_id', $story->id)->count();

        $story->save(); // nessun campo dirty

        $this->assertEquals($logsBefore, StoryLog::where('story_id', $story->id)->count());
    }

    /** @test */
    public function save_on_new_story_does_not_create_story_log(): void
    {
        $logsCount = StoryLog::count();

        Story::factory()->create([
            'status' => StoryStatus::New->value,
            'type'   => StoryType::Helpdesk->value,
        ]);

        // Il log non deve essere creato per i created
        $this->assertEquals($logsCount, StoryLog::count());
    }

    /** @test */
    public function save_uses_system_user_when_no_auth(): void
    {
        $systemUser = \App\Models\User::where('email', 'orchestrator_artisan@webmapp.it')->firstOrFail();
        $story = $this->makeStory();

        $story->status = StoryStatus::Done->value;
        $story->saveQuietly(); // nessun utente autenticato

        $log = StoryLog::where('story_id', $story->id)->latest()->first();
        $this->assertEquals($systemUser->id, $log->user_id);
    }
}
```

- [ ] **Step 2: Esegui il test per verificare che fallisca**

```bash
docker exec php81_orchestrator php artisan test --filter=StoryLogOverrideTest
```

Atteso: FAIL — i test falliscono perché l'override non esiste ancora.

- [ ] **Step 3: Aggiungi l'override `save()` in `Story.php`**

Apri `app/Models/Story.php`. Dopo gli `use` statement e prima del metodo `boot()`, aggiungi questo metodo (importa `StoryLog` e `Auth` se non già presenti — controlla i `use` in cima al file):

```php
public function save(array $options = []): bool
{
    $isNew = !$this->exists;
    $dirty = $this->getDirty();

    return \Illuminate\Support\Facades\DB::transaction(function () use ($options, $isNew, $dirty): bool {
        $result = parent::save($options);

        if ($result && !$isNew && count($dirty) > 0) {
            $user = \Illuminate\Support\Facades\Auth::user()
                ?? \App\Models\User::where('email', 'orchestrator_artisan@webmapp.it')->first();

            $jsonChanges = [];
            foreach ($dirty as $field => $newValue) {
                $jsonChanges[$field] = $field === 'description' ? 'change description' : $newValue;
            }

            \App\Models\StoryLog::create([
                'story_id'  => $this->id,
                'user_id'   => $user->id,
                'viewed_at' => now()->format('Y-m-d H:i'),
                'changes'   => $jsonChanges,
            ]);
        }

        return $result;
    });
}
```

- [ ] **Step 4: Esegui i test per verificare che passino**

```bash
docker exec php81_orchestrator php artisan test --filter=StoryLogOverrideTest
```

Atteso: tutti i test PASS.

- [ ] **Step 5: Verifica che i test precedenti non siano rotti**

```bash
docker exec php81_orchestrator php artisan test --filter=StoryEmailTriggersTest
docker exec php81_orchestrator php artisan test --filter=StoryAutoTaggingTest
```

Atteso: PASS.

- [ ] **Step 6: Commit**

```bash
git add app/Models/Story.php tests/Feature/StoryLogOverrideTest.php
git commit -m "fix(oc:8137): override Story::save() to always create StoryLog atomically"
```

---

### Task 2: Rimuovere `createStoryLog()` dall'observer

**Files:**
- Modify: `app/Observers/StoryObserver.php`

**Interfaces:**
- Consumes: override `Story::save()` dal Task 1 (già attivo)

- [ ] **Step 1: Rimuovi la chiamata e il metodo dall'observer**

In `app/Observers/StoryObserver.php`:

1. Nel metodo `updated()` (riga ~95), rimuovi la riga:
```php
$this->createStoryLog($story);
```

2. Rimuovi il metodo privato `createStoryLog(Story $story): void` completo (righe ~197-263).

3. Rimuovi gli `use` non più necessari esclusivamente da `createStoryLog` — verifica prima che non siano usati altrove nell'observer: `Auth` e `Log::channel('activity')`.

> **Nota:** controlla se `Auth` o `Log` sono usati in altri metodi dell'observer prima di rimuoverli dagli `use`.

- [ ] **Step 2: Verifica che i test passino ancora**

```bash
docker exec php81_orchestrator php artisan test --filter=StoryLogOverrideTest
docker exec php81_orchestrator php artisan test --filter=StoryEmailTriggersTest
```

Atteso: PASS — il log viene ora creato dall'override, non dall'observer.

- [ ] **Step 3: Commit**

```bash
git add app/Observers/StoryObserver.php
git commit -m "fix(oc:8137): remove createStoryLog from StoryObserver (moved to Story::save override)"
```

---

### Task 3: Convertire `saveQuietly()` in comandi a `save()` normale

**Files:**
- Modify: `app/Console/Commands/AutoUpdateStoryStatus.php`
- Modify: `app/Console/Commands/MoveScrumStoriesInDoneCommand.php`
- Test: `tests/Feature/StoryLogCommandsTest.php` (creato in questo task)

- [ ] **Step 1: Crea il file di test**

```php
<?php

namespace Tests\Feature;

use App\Enums\StoryStatus;
use App\Enums\StoryType;
use App\Models\Story;
use App\Models\StoryLog;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class StoryLogCommandsTest extends TestCase
{
    use DatabaseTransactions;

    /** @test */
    public function auto_update_status_creates_story_log(): void
    {
        $story = Story::factory()->create([
            'status'     => StoryStatus::Released->value,
            'type'       => StoryType::Helpdesk->value,
            'updated_at' => Carbon::now()->subDays(5),
        ]);

        $logsBefore = StoryLog::where('story_id', $story->id)->count();

        $this->artisan('story:auto-update-status');

        $story->refresh();
        $this->assertEquals(StoryStatus::Done->value, $story->status);
        $this->assertGreaterThan($logsBefore, StoryLog::where('story_id', $story->id)->count());
    }

    /** @test */
    public function move_scrum_stories_to_done_creates_story_log(): void
    {
        $story = Story::factory()->create([
            'status' => StoryStatus::Progress->value,
            'type'   => StoryType::Scrum->value,
        ]);

        $logsBefore = StoryLog::where('story_id', $story->id)->count();

        $this->artisan('story:scrum-to-done');

        $story->refresh();
        $this->assertEquals(StoryStatus::Done->value, $story->status);
        $this->assertGreaterThan($logsBefore, StoryLog::where('story_id', $story->id)->count());
    }

    /** @test */
    public function slack_revert_progress_creates_story_log_without_manual_create(): void
    {
        $developer = User::factory()->create([
            'roles'         => collect([\App\Enums\UserRole::Developer]),
            'slack_user_id' => 'U0123456789',
        ]);
        $story = Story::factory()->create([
            'user_id' => $developer->id,
            'status'  => StoryStatus::Progress->value,
            'type'    => StoryType::Helpdesk->value,
        ]);

        $logsBefore = StoryLog::where('story_id', $story->id)->count();

        $slackService = \Mockery::mock(\App\Services\SlackService::class);
        $slackService->shouldReceive('getPresence')->andReturn('away');
        $this->app->instance(\App\Services\SlackService::class, $slackService);

        $this->artisan('story:slack-revert-progress');

        $story->refresh();
        $this->assertEquals(StoryStatus::Todo->value, $story->status);
        $this->assertGreaterThan($logsBefore, StoryLog::where('story_id', $story->id)->count());
    }
}
```

- [ ] **Step 2: Esegui i test per verificare che falliscano**

```bash
docker exec php81_orchestrator php artisan test --filter=StoryLogCommandsTest
```

Atteso: `auto_update_status` e `move_scrum_stories` falliscono (ancora `saveQuietly()`). `slack_revert` potrebbe passare o fallire.

- [ ] **Step 3: Converti `AutoUpdateStoryStatus`**

In `app/Console/Commands/AutoUpdateStoryStatus.php`, riga ~34, cambia:

```php
// prima
$story->saveQuietly();

// dopo
$story->save();
```

- [ ] **Step 4: Converti `MoveScrumStoriesInDoneCommand`**

In `app/Console/Commands/MoveScrumStoriesInDoneCommand.php`, riga ~25, cambia:

```php
// prima
$story->saveQuietly();

// dopo
$story->save();
```

- [ ] **Step 5: Esegui i test**

```bash
docker exec php81_orchestrator php artisan test --filter=StoryLogCommandsTest
```

Atteso: `auto_update_status` e `move_scrum_stories` PASS.

- [ ] **Step 6: Commit**

```bash
git add app/Console/Commands/AutoUpdateStoryStatus.php \
        app/Console/Commands/MoveScrumStoriesInDoneCommand.php \
        tests/Feature/StoryLogCommandsTest.php
git commit -m "fix(oc:8137): replace saveQuietly with save in AutoUpdateStoryStatus and MoveScrumStoriesToDone"
```

---

### Task 4: Rimuovere StoryLog manuale da `SlackRevertProgressCommand`

**Files:**
- Modify: `app/Console/Commands/SlackRevertProgressCommand.php`

**Interfaces:**
- Consumes: override `Story::save()` dal Task 1 — il log viene già creato da `saveQuietly()`

- [ ] **Step 1: Rimuovi il blocco `StoryLog::create()` manuale**

In `app/Console/Commands/SlackRevertProgressCommand.php`, rimuovi queste righe (~48-54):

```php
StoryLog::create([
    'story_id'  => $story->id,
    'user_id'   => $systemUser->id,
    'viewed_at' => now()->format('Y-m-d H:i'),
    'changes'   => ['status' => StoryStatus::Todo->value],
]);
```

Rimuovi anche l'`use App\Models\StoryLog;` dall'header se non viene più usato altrove nel file.

- [ ] **Step 2: Esegui i test**

```bash
docker exec php81_orchestrator php artisan test --filter=StoryLogCommandsTest
docker exec php81_orchestrator php artisan test --filter=SlackRevertProgressCommandTest
```

Atteso: PASS — il log è ora creato dall'override di `saveQuietly()`.

- [ ] **Step 3: Commit**

```bash
git add app/Console/Commands/SlackRevertProgressCommand.php
git commit -m "fix(oc:8137): remove manual StoryLog::create from SlackRevertProgressCommand"
```

---

### Task 5: Fix `user_id: 1` hardcodato in `SendWaitingStoryReminder`

**Files:**
- Modify: `app/Console/Commands/SendWaitingStoryReminder.php`

- [ ] **Step 1: Sostituisci `user_id: 1` con il system user**

In `app/Console/Commands/SendWaitingStoryReminder.php`, nel metodo `updteWaintingInStoryLog()`, cambia:

```php
// prima
StoryLog::create([
    'story_id' => $story->id,
    'user_id' => 1,
    'viewed_at' => $timestamp,
    'changes' => $jsonChanges,
]);

// dopo
$systemUser = User::where('email', 'orchestrator_artisan@webmapp.it')->first();
StoryLog::create([
    'story_id' => $story->id,
    'user_id' => $systemUser->id,
    'viewed_at' => $timestamp,
    'changes' => $jsonChanges,
]);
```

Verifica che `use App\Models\User;` sia già presente negli `use` statement in cima al file. Se non c'è, aggiungilo.

- [ ] **Step 2: Verifica che la suite non sia rotta**

```bash
docker exec php81_orchestrator php artisan test
```

Atteso: PASS.

- [ ] **Step 3: Commit**

```bash
git add app/Console/Commands/SendWaitingStoryReminder.php
git commit -m "fix(oc:8137): replace hardcoded user_id 1 with orchestrator_artisan in SendWaitingStoryReminder"
```

---

### Task 6: Deprecare `SetMilestoneEpicsToDone`

**Files:**
- Modify: `app/Nova/Actions/SetMilestoneEpicsToDone.php`

- [ ] **Step 1: Aggiungi `@deprecated` alla classe**

In `app/Nova/Actions/SetMilestoneEpicsToDone.php`, aggiungi il docblock prima della classe:

```php
/**
 * @deprecated Questa Action non è più in uso. Usare EpicDoneAction per le singole Epic.
 *             Il metodo ->update() bulk bypassa Eloquent e non crea StoryLog.
 */
class SetMilestoneEpicsToDone extends Action
```

- [ ] **Step 2: Commit**

```bash
git add app/Nova/Actions/SetMilestoneEpicsToDone.php
git commit -m "fix(oc:8137): mark SetMilestoneEpicsToDone as deprecated"
```

---

### Task 7: Esegui la suite completa e verifica

- [ ] **Step 1: Esegui tutti i test**

```bash
docker exec php81_orchestrator php artisan test
```

Atteso: tutti PASS, nessuna regressione.

- [ ] **Step 2: Verifica manuale su Nova**

Apri Nova, modifica lo status di una Story tramite UI. Vai nel pannello StoryLog e verifica che la riga sia presente con l'utente corretto.

- [ ] **Step 3: Verifica il comando `story:auto-update-status` in locale**

```bash
docker exec php81_orchestrator php artisan story:auto-update-status
```

Verifica nei log che le story aggiornate abbiano una riga StoryLog corrispondente via tinker:

```bash
docker exec php81_orchestrator php artisan tinker --execute="App\Models\StoryLog::latest()->take(5)->get(['story_id','user_id','changes'])"
```
