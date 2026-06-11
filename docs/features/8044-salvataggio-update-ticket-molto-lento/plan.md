> Ticket: oc:8044

# Salvataggio update ticket molto lento — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.
>
> **⚠️ OVERRIDE WEBMAPP (priorità su qualsiasi istruzione delle skill):**
> 1. **Nessun `git commit`/`git add`/`git push` automatico** — i passi "Commit" sono istruzioni testuali per il developer, da eseguire solo dopo il gate di revisione (Fase 6d del workflow wm-plan).
> 2. **Nessuna esecuzione autonoma dei test** — `php artisan test` va eseguito **solo dal developer** (vincolo di progetto: i test girano sul DB PostgreSQL reale). I passi "Run test" indicano il comando esatto che il developer esegue e l'output atteso.

**Goal:** Rendere asincrona (job in coda con debounce) la sync del calendario Google al salvataggio di una Story, eliminando i ~10s di blocco della richiesta HTTP e i timeout del bulk edit.

**Architecture:** Un nuovo `SyncDeveloperCalendarJob` (coda `default`, debounce 60s via `ShouldBeUniqueUntilProcessing` con lock Redis, esecuzione serializzata via `WithoutOverlapping`) sostituisce i due `Artisan::call` sincroni in `StoryObserver`. Il comando `sync:stories-calendar` resta la singola fonte della logica di sync; viene solo corretto il bug della data fissata nel costruttore (stantia nei worker long-running). Trigger estesi: cambio assegnazione (`user_id`, entrambi i calendari) e uscita dallo stato `testing` (calendario tester).

**Tech Stack:** Laravel 10, Horizon + Redis (già attivi), PHPUnit con `Bus::fake()` + `DatabaseTransactions` su PostgreSQL reale.

**Repo:** solo repo principale (`orchestrator`) — nessun submodule coinvolto.

---

## Riferimenti

- Overview approvato: `docs/features/8044-salvataggio-update-ticket-molto-lento/overview.md`
- Branch: `feature/oc-8044-salvataggio-update-ticket-molto-lento`
- Convenzioni test: vedi `tests/Feature/StoryEmailTriggersTest.php` (helper `makeDeveloper`/`makeStory`, `DatabaseTransactions`, `Bus::fake()`)
- Convenzioni job: vedi `app/Jobs/SendStatusUpdateMailJob.php`

**Valori enum rilevanti (`app/Enums/StoryStatus.php`):** attenzione, `StoryStatus::Test->value === 'testing'` (non `'test'`).

---

### Task 1: Fix data stantia in `SyncStoriesWithGoogleCalendar`

Il costruttore fissa `$this->today`/`$this->startTime` una volta per processo. Nei worker Horizon (long-running, istanza del comando cachata dall'applicazione Artisan) dopo mezzanotte il comando cancellerebbe gli eventi di oggi ma li ricreerebbe su ieri. Fix: inizializzare le date all'inizio di `handle()`.

**Files:**
- Modify: `app/Console/Commands/SyncStoriesWithGoogleCalendar.php:32-40`

- [ ] **Step 1: Sposta l'inizializzazione delle date dal costruttore a `handle()`**

Il codice attuale (righe 32-40):

```php
    public function __construct()
    {
        parent::__construct();
        $this->today = Carbon::today('Europe/Rome');
        $this->startTime = $this->today->setTime(0, 1);
    }

    public function handle()
    {
```

Diventa:

```php
    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        // Initialize dates here and not in the constructor: Artisan caches the
        // command instance per process, so in long-running queue workers a
        // constructor-set date goes stale after midnight (events recreated on
        // the wrong day).
        $this->today = Carbon::today('Europe/Rome');
        $this->startTime = $this->today->setTime(0, 1);
```

⚠️ **Nota Carbon (comportamento da preservare, non da correggere):** `Carbon` è mutabile — `$this->today->setTime(0, 1)` muta anche `$this->today`, quindi `today` e `startTime` puntano alla **stessa istanza** alle 00:01. È il comportamento attuale del comando: replicarlo identico, non "sistemarlo" con `copy()`.

Nessun'altra modifica al comando. Il resto di `handle()` (da `$developerId = null;` in giù) resta invariato.

- [ ] **Step 2: Verifica sintassi**

Run: `docker exec php81_orchestrator php -l app/Console/Commands/SyncStoriesWithGoogleCalendar.php`
Expected: `No syntax errors detected`

- [ ] **Step 3: Commit (istruzione testuale — eseguita dal developer dopo il gate di revisione)**

```bash
git add app/Console/Commands/SyncStoriesWithGoogleCalendar.php
git commit -m "fix(oc:8044): init sync dates in handle() to avoid stale dates in long-running workers"
```

---

### Task 2: Crea `SyncDeveloperCalendarJob`

**Files:**
- Create: `app/Jobs/SyncDeveloperCalendarJob.php`

- [ ] **Step 1: Crea il job completo**

```php
<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class SyncDeveloperCalendarJob implements ShouldQueue, ShouldBeUniqueUntilProcessing
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Debounce window in seconds: every dispatch is delayed by this amount and
     * deduplicated by the unique lock, so N saves in a short burst produce a
     * single sync that reads the final state from the database.
     */
    public const DEBOUNCE_SECONDS = 60;

    /**
     * Safety expiry of the unique lock: if the job dies before processing,
     * the lock is released after this window and a new sync can be queued.
     *
     * @var int
     */
    public $uniqueFor = 300;

    /**
     * WithoutOverlapping releases an overlapping job back onto the queue and
     * every release counts as an attempt: keep enough tries so a release does
     * not mark the job as failed (supervisor default is tries=1).
     *
     * @var int
     */
    public $tries = 5;

    public string $developerEmail;

    public function __construct(string $developerEmail)
    {
        $this->developerEmail = $developerEmail;
        $this->delay(self::DEBOUNCE_SECONDS);
    }

    /**
     * One pending sync per developer: dispatches within the debounce window
     * are deduplicated by this key.
     */
    public function uniqueId(): string
    {
        return $this->developerEmail;
    }

    /**
     * Keep the unique lock on Redis: it survives `php artisan cache:clear`
     * (which clears the default file store) and does not depend on web and
     * worker sharing the same filesystem.
     */
    public function uniqueVia(): Repository
    {
        return Cache::driver('redis');
    }

    /**
     * Never run two syncs for the same developer concurrently: the sync is
     * delete-then-recreate, idempotent only when serialized. An overlapping
     * job is released back onto the queue and retried after 60 seconds.
     */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping($this->developerEmail))
                ->releaseAfter(60)
                ->expireAfter(300),
        ];
    }

    public function handle(): void
    {
        Artisan::call('sync:stories-calendar', ['developerEmail' => $this->developerEmail]);
        $output = Artisan::output();

        // The command swallows Google API exceptions internally and reports
        // them only on its output: surface them in the logs, otherwise the
        // job always looks green on Horizon.
        if (str_contains($output, 'Failed to')) {
            Log::warning("Calendar sync for {$this->developerEmail} completed with errors", ['output' => $output]);
        } else {
            Log::info("Calendar sync completed for {$this->developerEmail}");
        }
    }
}
```

Decisioni incorporate (dall'overview approvato):
- `ShouldBeUniqueUntilProcessing` (non `ShouldBeUnique`): il lock si rilascia all'avvio dell'esecuzione → un save *durante* la sync accoda un nuovo job, mai una modifica persa
- delay nel **costruttore**: ogni dispatch è automaticamente debounced, nessun rischio di dimenticare `->delay()` nei punti di dispatch
- coda `default`: nessuna modifica a `config/horizon.php` (decisione Fase 2, Domanda 3)

- [ ] **Step 2: Verifica sintassi**

Run: `docker exec php81_orchestrator php -l app/Jobs/SyncDeveloperCalendarJob.php`
Expected: `No syntax errors detected`

---

### Task 3: Scrivi i test (prima dell'observer: devono fallire)

**Files:**
- Create: `tests/Feature/SyncDeveloperCalendarJobTest.php`

- [ ] **Step 1: Crea il file di test completo**

Convenzioni mutuate da `tests/Feature/StoryEmailTriggersTest.php` (stesso progetto): `DatabaseTransactions` su DB PostgreSQL reale, helper privati per le factory, `Bus::fake()`.

```php
<?php

namespace Tests\Feature;

use App\Enums\StoryStatus;
use App\Enums\StoryType;
use App\Enums\UserRole;
use App\Jobs\SyncDeveloperCalendarJob;
use App\Models\Story;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class SyncDeveloperCalendarJobTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        // ShouldBeUniqueUntilProcessing acquires its unique lock even when the
        // Bus is faked (the lock lives in PendingDispatch, before the fake
        // dispatcher). The job uses Cache::driver('redis') for the lock: point
        // the redis store to the array driver so tests never touch Redis.
        config(['cache.stores.redis.driver' => 'array']);
    }

    private function makeCustomer(): User
    {
        return User::factory()->create(['roles' => collect([UserRole::Customer])]);
    }

    private function makeDeveloper(): User
    {
        return User::factory()->create(['roles' => collect([UserRole::Developer])]);
    }

    private function makeStory(array $attrs = []): Story
    {
        $customer = $attrs['creator'] ?? $this->makeCustomer();

        return Story::query()->create(array_merge([
            'name' => 'Test story',
            'type' => StoryType::Helpdesk->value,
            'status' => StoryStatus::New->value,
            'creator_id' => $customer->id,
            'customer_request' => '<p>hello</p>',
        ], $attrs));
    }

    private function syncJobsFor(string $email): \Illuminate\Support\Collection
    {
        return Bus::dispatched(SyncDeveloperCalendarJob::class)
            ->filter(fn (SyncDeveloperCalendarJob $job) => $job->developerEmail === $email);
    }

    /** @test */
    public function status_change_dispatches_sync_for_assigned_developer(): void
    {
        Bus::fake();
        $developer = $this->makeDeveloper();
        $story = $this->makeStory(['user_id' => $developer->id]);

        Auth::login($developer);
        $story->status = StoryStatus::Todo->value;
        $story->save();

        $this->assertCount(1, $this->syncJobsFor($developer->email));
    }

    /** @test */
    public function status_to_testing_dispatches_sync_for_developer_and_tester(): void
    {
        Bus::fake();
        $developer = $this->makeDeveloper();
        $tester = $this->makeDeveloper();
        $story = $this->makeStory([
            'user_id' => $developer->id,
            'tester_id' => $tester->id,
            'status' => StoryStatus::Progress->value,
        ]);

        Auth::login($developer);
        $story->status = StoryStatus::Test->value;
        $story->save();

        $this->assertCount(1, $this->syncJobsFor($developer->email));
        $this->assertCount(1, $this->syncJobsFor($tester->email));
    }

    /** @test */
    public function status_leaving_testing_dispatches_sync_for_tester(): void
    {
        Bus::fake();
        $developer = $this->makeDeveloper();
        $tester = $this->makeDeveloper();
        $story = $this->makeStory([
            'user_id' => $developer->id,
            'tester_id' => $tester->id,
            'status' => StoryStatus::Test->value,
        ]);

        Auth::login($tester);
        $story->status = StoryStatus::Done->value;
        $story->save();

        $this->assertCount(1, $this->syncJobsFor($developer->email));
        $this->assertCount(1, $this->syncJobsFor($tester->email));
    }

    /** @test */
    public function assignee_change_dispatches_sync_for_both_developers(): void
    {
        Bus::fake();
        $oldDeveloper = $this->makeDeveloper();
        $newDeveloper = $this->makeDeveloper();
        $story = $this->makeStory([
            'user_id' => $oldDeveloper->id,
            'status' => StoryStatus::Todo->value,
        ]);

        Auth::login($oldDeveloper);
        $story->user_id = $newDeveloper->id;
        $story->save();

        $this->assertCount(1, $this->syncJobsFor($oldDeveloper->email));
        $this->assertCount(1, $this->syncJobsFor($newDeveloper->email));
    }

    /** @test */
    public function save_without_status_or_assignee_change_dispatches_nothing(): void
    {
        Bus::fake();
        $developer = $this->makeDeveloper();
        $story = $this->makeStory([
            'user_id' => $developer->id,
            'status' => StoryStatus::Todo->value,
        ]);

        Auth::login($developer);
        $story->name = 'Renamed story';
        $story->save();

        Bus::assertNotDispatched(SyncDeveloperCalendarJob::class);
    }

    /** @test */
    public function dispatched_job_is_delayed_by_the_debounce_window(): void
    {
        Bus::fake();
        $developer = $this->makeDeveloper();
        $story = $this->makeStory(['user_id' => $developer->id]);

        Auth::login($developer);
        $story->status = StoryStatus::Todo->value;
        $story->save();

        Bus::assertDispatched(
            SyncDeveloperCalendarJob::class,
            fn (SyncDeveloperCalendarJob $job) => $job->delay === SyncDeveloperCalendarJob::DEBOUNCE_SECONDS
        );
    }
}
```

Note sui casi coperti (requisiti dall'overview):
- cambio status → developer ✓
- ingresso in `testing` → anche tester ✓
- uscita da `testing` → anche tester (nuovo trigger) ✓
- cambio `user_id` → entrambi (nuovo trigger) ✓
- save neutro → nessun dispatch ✓
- debounce delay presente su ogni dispatch ✓

- [ ] **Step 2: Il developer esegue i test e verifica che FALLISCANO**

Comando (eseguito **dal developer**, mai da Claude — i test girano sul DB reale):

```bash
docker exec -it php81_orchestrator bash -c "DB_DATABASE=orchestrator php artisan test --filter=SyncDeveloperCalendarJobTest"
```

Expected: **FAIL** — i 5 test che si aspettano dispatch falliscono (`assertCount(1, ...)` trova 0) perché l'observer chiama ancora `Artisan::call` sincrono. Il test `save_without_status_or_assignee_change_dispatches_nothing` passa già (falso verde atteso a questo stadio).

⚠️ Se i test falliscono per motivi diversi (es. factory, colonne mancanti), fermarsi e indagare prima di procedere al Task 4.

---

### Task 4: Sostituisci la sync sincrona in `StoryObserver` con il dispatch del job

**Files:**
- Modify: `app/Observers/StoryObserver.php:12` (import), `app/Observers/StoryObserver.php:74` (chiamata), `app/Observers/StoryObserver.php:136-153` (metodo)

- [ ] **Step 1: Aggiorna gli import**

L'import `use Illuminate\Support\Facades\Artisan;` (riga 12) non serve più — sostituirlo con l'import del job. Il blocco import attuale:

```php
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Artisan;
use App\Enums\UserRole;
```

Diventa:

```php
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Jobs\SyncDeveloperCalendarJob;
use App\Enums\UserRole;
```

- [ ] **Step 2: Riscrivi il metodo di sync (e rinominalo: ora copre anche il cambio assegnazione)**

Il metodo attuale (righe 136-153):

```php
    private function syncStoryCalendarIfStatusChanged(Story $story): void
    {
        if ($story->isDirty('status')) {
            $developerId = $story->user_id;
            if ($developerId) {
                $developer = DB::table('users')->where('id', $developerId)->first();
                if ($developer && $developer->email) {
                    Artisan::call('sync:stories-calendar', ['developerEmail' => $developer->email]);
                }
            }
            if ($story->status === StoryStatus::Test->value) {
                $tester = $story->tester;
                if ($tester && $tester->email) {
                    Artisan::call('sync:stories-calendar', ['developerEmail' => $tester->email]);
                }
            }
        }
    }
```

Diventa:

```php
    private function dispatchCalendarSyncIfNeeded(Story $story): void
    {
        $emails = [];

        if ($story->isDirty('status')) {
            $emails[] = $this->userEmail($story->user_id);

            // Sync the tester calendar both when the story enters and when it
            // leaves the testing status (the 2BETESTED event must disappear).
            if (
                $story->status === StoryStatus::Test->value
                || $story->getOriginal('status') === StoryStatus::Test->value
            ) {
                $tester = $story->tester;
                $emails[] = $tester?->email;
            }
        }

        // On reassignment sync both calendars: the old assignee loses the
        // event, the new one gains it.
        if ($story->isDirty('user_id')) {
            $emails[] = $this->userEmail($story->getOriginal('user_id'));
            $emails[] = $this->userEmail($story->user_id);
        }

        // The job is delayed (debounce) and unique per email: bursts of saves
        // collapse into a single sync that reads the final state from the DB.
        foreach (array_unique(array_filter($emails)) as $email) {
            SyncDeveloperCalendarJob::dispatch($email);
        }
    }

    private function userEmail(?int $userId): ?string
    {
        if (!$userId) {
            return null;
        }

        return DB::table('users')->where('id', $userId)->value('email');
    }
```

- [ ] **Step 3: Aggiorna la chiamata in `updated()`**

Riga 74, da:

```php
        $this->syncStoryCalendarIfStatusChanged($story);
```

a:

```php
        $this->dispatchCalendarSyncIfNeeded($story);
```

Le altre due chiamate in `updated()` (`createStoryLog`, `notifyDeveloperIfIdle`) restano invariate. **Non toccare** il cascade demote in `saving()` (righe 107-117): il `save()` "rumoroso" è una decisione esplicita (preserva StoryLog → StoryTimeService).

- [ ] **Step 4: Verifica sintassi e assenza di `Artisan::call` residui**

Run: `docker exec php81_orchestrator php -l app/Observers/StoryObserver.php; grep -c "Artisan" app/Observers/StoryObserver.php`
Expected: `No syntax errors detected` e `0` occorrenze di `Artisan` (grep esce con codice 1 quando non trova nulla: è l'esito atteso)

- [ ] **Step 5: Il developer esegue i test e verifica che PASSINO**

Comando (eseguito **dal developer**):

```bash
docker exec -it php81_orchestrator bash -c "DB_DATABASE=orchestrator php artisan test --filter=SyncDeveloperCalendarJobTest"
```

Expected: **PASS** — 6 test verdi.

- [ ] **Step 6: Il developer esegue i test correlati per escludere regressioni**

L'observer è condiviso con i trigger email: verificare che non si sia rotto nulla.

```bash
docker exec -it php81_orchestrator bash -c "DB_DATABASE=orchestrator php artisan test --filter=StoryEmailTriggersTest"
```

Expected: **PASS** — tutti i test esistenti verdi.

- [ ] **Step 7: Commit (istruzione testuale — eseguita dal developer dopo il gate di revisione)**

```bash
git add app/Jobs/SyncDeveloperCalendarJob.php app/Observers/StoryObserver.php tests/Feature/SyncDeveloperCalendarJobTest.php
git commit -m "fix(oc:8044): dispatch calendar sync as debounced queued job instead of blocking the request"
```

---

### Task 5: Verifica manuale end-to-end (developer, opzionale ma consigliata)

Nessun file da modificare — checklist di verifica funzionale in locale.

- [ ] **Step 1: Avvia un worker in locale**

```bash
docker exec -it php81_orchestrator php artisan queue:work --queue=default
```

- [ ] **Step 2: Da Nova, cambia lo status di una Story assegnata a un developer con calendario Google configurato**

Expected: la risposta del save è immediata (< 2s); dopo ~60s il worker logga l'esecuzione di `SyncDeveloperCalendarJob` e il calendario si aggiorna.

- [ ] **Step 3: Bulk edit su 5+ Story dello stesso developer (azione Nova "Edit")**

Expected: azione completata senza timeout; nei log del worker **un solo** job per l'email del developer (dedup), non 5.

- [ ] **Step 4: Controlla i log**

```bash
docker exec php81_orchestrator tail -50 storage/logs/laravel.log | grep "Calendar sync"
```

Expected: righe `Calendar sync completed for <email>` (o `completed with errors` se Google non è configurato in locale — comunque nessun job rosso).

---

## Riepilogo commit (eseguiti dal developer dopo il gate di revisione, Fase 6d)

| # | Commit | File |
|---|---|---|
| 1 | `fix(oc:8044): init sync dates in handle() to avoid stale dates in long-running workers` | `SyncStoriesWithGoogleCalendar.php` |
| 2 | `fix(oc:8044): dispatch calendar sync as debounced queued job instead of blocking the request` | `SyncDeveloperCalendarJob.php`, `StoryObserver.php`, `SyncDeveloperCalendarJobTest.php` |
| 3 | `docs(oc:8044): 📝 add feature docs and update CLAUDE.md` (a fine workflow, con notes.md e CLAUDE.md) | `docs/features/8044-.../*`, `CLAUDE.md` |

## Cosa NON fa questo piano (out of scope, dall'overview)

- Nessuna modifica a `config/horizon.php`, `app/Nova/Actions/EditStories.php`, `app/Console/Kernel.php`
- Nessun `saveQuietly()` sul cascade demote
- Nessun refactoring interno di `SyncStoriesWithGoogleCalendar` oltre al fix data stantia
- Sync su delete/create di Story, cambio `tester_id` senza status, edge mezzanotte: comportamento invariato
- Supervisione Horizon: ticket dedicato oc:8059
