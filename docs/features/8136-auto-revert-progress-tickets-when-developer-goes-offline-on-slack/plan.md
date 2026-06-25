> Ticket: oc:8136

# Auto-revert Progress Tickets When Developer Goes Offline on Slack — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Aggiungere un comando schedulato che ogni 20 minuti (dalle 12 alle 18, Europe/Rome) verifica la presenza Slack dei developer con ticket in "progress" e, se offline, riporta silenziosamente il ticket in "todo" creando uno StoryLog.

**Architecture:** `SlackService` wrappa la chiamata HTTP a `users.getPresence`. `SlackRevertProgressCommand` recupera i developer con ticket in progress e `slack_user_id` valorizzato, chiama `SlackService` per ciascuno, e su `presence == away` esegue `saveQuietly()` + crea `StoryLog` manualmente. Il comando gira ogni 20 minuti con `withoutOverlapping()`. Il comando esistente `story:progress-to-todo` (18:00) rimane invariato come safety net.

**Tech Stack:** Laravel 10, Artisan Console, Laravel HTTP Client, Laravel Scheduler, Nova Resource fields, PHPUnit con DatabaseTransactions.

## Global Constraints

- Commit convention: `feat(oc:8136): ...`
- Test su DB `orchestrator_test` — tutti i test Feature usano `DatabaseTransactions`
- `saveQuietly()` obbligatorio per evitare trigger observer (email, calendar sync)
- StoryLog va creato manualmente dopo ogni `saveQuietly()` — user di sistema: `orchestrator_artisan@webmapp.it` (`PhpArtisanUserSeeder`)
- Errore API Slack = skip developer, mai revert su eccezione
- Solo `presence == "away"` esplicito triggera il revert
- No commit automatici — i commit nel piano sono istruzioni testuali

---

### Task 1: Migration + Model User

**Files:**
- Create: `database/migrations/<timestamp>_add_slack_user_id_to_users_table.php`
- Modify: `app/Models/User.php`

**Interfaces:**
- Produces: `User::$fillable` include `'slack_user_id'`; colonna `slack_user_id` nullable string su tabella `users`

- [ ] **Step 1: Crea la migration**

```bash
docker exec php81_orchestrator php artisan make:migration add_slack_user_id_to_users_table --table=users
```

Apri il file generato in `database/migrations/` e sostituisci il contenuto di `up()` e `down()`:

```php
public function up(): void
{
    Schema::table('users', function (Blueprint $table) {
        $table->string('slack_user_id')->nullable()->after('email');
    });
}

public function down(): void
{
    Schema::table('users', function (Blueprint $table) {
        $table->dropColumn('slack_user_id');
    });
}
```

- [ ] **Step 2: Esegui la migration**

```bash
docker exec php81_orchestrator php artisan migrate
```

Atteso: `Migrating: ...add_slack_user_id_to_users_table` → `Migrated`

- [ ] **Step 3: Aggiorna `$fillable` in `app/Models/User.php`**

```php
protected $fillable = [
    'name',
    'email',
    'password',
    'roles',
    'slack_user_id',
];
```

- [ ] **Step 4: Verifica la migration sul DB di test**

```bash
docker exec php81_orchestrator bash -c "DB_DATABASE=orchestrator_test php artisan migrate"
```

Atteso: `Migrated` (o `Nothing to migrate` se già allineato — in tal caso forza: aggiungi `--force`).

- [ ] **Step 5: Commit**

```bash
git add database/migrations/ app/Models/User.php
git commit -m "feat(oc:8136): add slack_user_id to users table"
```

---

### Task 2: Campo Nova su User Resource

**Files:**
- Modify: `app/Nova/User.php`

**Interfaces:**
- Consumes: `User::$fillable` include `'slack_user_id'` (Task 1)
- Produces: campo `Slack User ID` visibile e editabile in Nova nella scheda utente

- [ ] **Step 1: Aggiungi il campo in `app/Nova/User.php`**

Nel metodo `fields()`, dopo il campo `Password`, aggiungi:

```php
Text::make('Slack User ID', 'slack_user_id')
    ->nullable()
    ->hideFromIndex()
    ->help('ID utente Slack (es. U0123456789). Usato per verificare la presenza online.'),
```

- [ ] **Step 2: Verifica manuale in Nova**

Vai su `/nova/resources/users/<id>/edit` e verifica che il campo "Slack User ID" appaia e sia salvabile.

- [ ] **Step 3: Commit**

```bash
git add app/Nova/User.php
git commit -m "feat(oc:8136): expose slack_user_id field in Nova User resource"
```

---

### Task 3: SlackService + configurazione ENV

**Files:**
- Create: `app/Services/SlackService.php`
- Modify: `config/services.php`

**Interfaces:**
- Produces: `SlackService::getPresence(string $slackUserId): string` — ritorna `"active"` o `"away"`; lancia `\Exception` se la risposta non è valida o l'API fallisce

- [ ] **Step 1: Aggiungi la chiave `slack` in `config/services.php`**

In fondo all'array `return [...]`:

```php
'slack' => [
    'bot_token' => env('SLACK_BOT_TOKEN'),
],
```

- [ ] **Step 2: Aggiungi `SLACK_BOT_TOKEN` al file `.env`**

```
SLACK_BOT_TOKEN=xoxb-your-token-here
```

(Per ora puoi lasciare il valore placeholder — il servizio lo legge da ENV.)

- [ ] **Step 3: Crea `app/Services/SlackService.php`**

```php
<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class SlackService
{
    private string $token;

    public function __construct()
    {
        $this->token = config('services.slack.bot_token');
    }

    public function getPresence(string $slackUserId): string
    {
        $response = Http::withToken($this->token)
            ->get('https://slack.com/api/users.getPresence', [
                'user' => $slackUserId,
            ]);

        if (! $response->successful()) {
            throw new \Exception("Slack API HTTP error: {$response->status()}");
        }

        $data = $response->json();

        if (! ($data['ok'] ?? false)) {
            throw new \Exception("Slack API error: " . ($data['error'] ?? 'unknown'));
        }

        return $data['presence'];
    }
}
```

- [ ] **Step 4: Commit**

```bash
git add app/Services/SlackService.php config/services.php
git commit -m "feat(oc:8136): add SlackService and SLACK_BOT_TOKEN config"
```

---

### Task 4: SlackRevertProgressCommand

**Files:**
- Create: `app/Console/Commands/SlackRevertProgressCommand.php`

**Interfaces:**
- Consumes: `SlackService::getPresence(string $slackUserId): string` (Task 3); `User::$fillable` include `slack_user_id` (Task 1); `StoryLog::$fillable = ['story_id', 'user_id', 'viewed_at', 'changes']`
- Produces: comando Artisan `story:slack-revert-progress`

- [ ] **Step 1: Crea il comando**

```php
<?php

namespace App\Console\Commands;

use App\Enums\StoryStatus;
use App\Models\Story;
use App\Models\StoryLog;
use App\Models\User;
use App\Services\SlackService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SlackRevertProgressCommand extends Command
{
    protected $signature = 'story:slack-revert-progress';
    protected $description = 'Revert progress tickets to todo for developers who are offline on Slack';

    public function handle(SlackService $slackService): void
    {
        $systemUser = User::where('email', 'orchestrator_artisan@webmapp.it')->first();

        $developers = User::whereHas('stories', function ($q) {
            $q->where('status', StoryStatus::Progress->value);
        })->whereNotNull('slack_user_id')->get();

        foreach ($developers as $developer) {
            try {
                $presence = $slackService->getPresence($developer->slack_user_id);
            } catch (\Exception $e) {
                Log::warning("SlackRevertProgress: skip developer {$developer->id} — API error: {$e->getMessage()}");
                $this->warn("Skipped developer {$developer->id}: {$e->getMessage()}");
                continue;
            }

            if ($presence !== 'away') {
                continue;
            }

            $stories = Story::where('user_id', $developer->id)
                ->where('status', StoryStatus::Progress->value)
                ->get();

            foreach ($stories as $story) {
                $story->status = StoryStatus::Todo->value;
                $story->saveQuietly();

                StoryLog::create([
                    'story_id'  => $story->id,
                    'user_id'   => $systemUser->id,
                    'viewed_at' => now()->format('Y-m-d H:i'),
                    'changes'   => ['status' => StoryStatus::Todo->value],
                ]);

                $this->info("Reverted story {$story->id} (developer {$developer->id})");
                Log::info("SlackRevertProgress: reverted story {$story->id} for developer {$developer->id}");
            }
        }

        $this->info('story:slack-revert-progress complete.');
    }
}
```

- [ ] **Step 2: Commit**

```bash
git add app/Console/Commands/SlackRevertProgressCommand.php
git commit -m "feat(oc:8136): add SlackRevertProgressCommand"
```

---

### Task 5: Scheduling in Kernel

**Files:**
- Modify: `app/Console/Kernel.php`

**Interfaces:**
- Consumes: comando `story:slack-revert-progress` (Task 4)
- Produces: comando schedulato ogni 20 minuti dalle 12:00 alle 18:00 (Europe/Rome) con `withoutOverlapping()`

- [ ] **Step 1: Aggiungi lo scheduling in `app/Console/Kernel.php`**

Nel metodo `schedule()`, dopo il blocco `story:progress-to-todo`:

```php
$schedule->command('story:slack-revert-progress')
    ->timezone('Europe/Rome')
    ->everyTwentyMinutes()
    ->between('12:00', '18:00')
    ->withoutOverlapping()
    ->before(function () {
        Log::info('story:slack-revert-progress starting');
    })
    ->after(function () {
        Log::info('story:slack-revert-progress finished');
    });
```

- [ ] **Step 2: Verifica che il comando appaia nella lista schedulata**

```bash
docker exec php81_orchestrator php artisan schedule:list
```

Atteso: `story:slack-revert-progress` con `Every 20 minutes between 12:00 and 18:00`.

- [ ] **Step 3: Commit**

```bash
git add app/Console/Kernel.php
git commit -m "feat(oc:8136): schedule slack-revert-progress every 20 min between 12-18"
```

---

### Task 6: Test Feature

**Files:**
- Create: `tests/Feature/SlackRevertProgressCommandTest.php`

**Interfaces:**
- Consumes: `story:slack-revert-progress` (Task 4); `SlackService::getPresence()` (Task 3)

- [ ] **Step 1: Scrivi il test**

```php
<?php

namespace Tests\Feature;

use App\Console\Commands\SlackRevertProgressCommand;
use App\Enums\StoryStatus;
use App\Enums\StoryType;
use App\Enums\UserRole;
use App\Models\Story;
use App\Models\StoryLog;
use App\Models\User;
use App\Services\SlackService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class SlackRevertProgressCommandTest extends TestCase
{
    use DatabaseTransactions;

    private function makeDeveloper(array $attrs = []): User
    {
        return User::factory()->create(array_merge([
            'roles' => collect([UserRole::Developer]),
            'slack_user_id' => 'U0123456789',
        ], $attrs));
    }

    private function makeProgressStory(User $developer): Story
    {
        return Story::factory()->create([
            'user_id' => $developer->id,
            'status'  => StoryStatus::Progress->value,
            'type'    => StoryType::Helpdesk->value,
        ]);
    }

    /** @test */
    public function it_reverts_progress_story_to_todo_when_developer_is_away(): void
    {
        $developer = $this->makeDeveloper();
        $story = $this->makeProgressStory($developer);

        $slack = $this->mock(SlackService::class);
        $slack->shouldReceive('getPresence')
            ->with($developer->slack_user_id)
            ->once()
            ->andReturn('away');

        $this->artisan('story:slack-revert-progress')->assertExitCode(0);

        $this->assertEquals(StoryStatus::Todo->value, $story->fresh()->status);
    }

    /** @test */
    public function it_creates_story_log_on_revert(): void
    {
        $developer = $this->makeDeveloper();
        $story = $this->makeProgressStory($developer);

        $slack = $this->mock(SlackService::class);
        $slack->shouldReceive('getPresence')->andReturn('away');

        $this->artisan('story:slack-revert-progress');

        $log = StoryLog::where('story_id', $story->id)->latest()->first();
        $this->assertNotNull($log);
        $this->assertEquals(['status' => StoryStatus::Todo->value], $log->changes);
    }

    /** @test */
    public function it_does_not_revert_when_developer_is_active(): void
    {
        $developer = $this->makeDeveloper();
        $story = $this->makeProgressStory($developer);

        $slack = $this->mock(SlackService::class);
        $slack->shouldReceive('getPresence')
            ->with($developer->slack_user_id)
            ->once()
            ->andReturn('active');

        $this->artisan('story:slack-revert-progress');

        $this->assertEquals(StoryStatus::Progress->value, $story->fresh()->status);
    }

    /** @test */
    public function it_skips_developer_on_slack_api_exception(): void
    {
        $developer = $this->makeDeveloper();
        $story = $this->makeProgressStory($developer);

        $slack = $this->mock(SlackService::class);
        $slack->shouldReceive('getPresence')
            ->andThrow(new \Exception('Slack API error'));

        $this->artisan('story:slack-revert-progress')->assertExitCode(0);

        $this->assertEquals(StoryStatus::Progress->value, $story->fresh()->status);
    }

    /** @test */
    public function it_skips_developer_without_slack_user_id(): void
    {
        $developer = $this->makeDeveloper(['slack_user_id' => null]);
        $story = $this->makeProgressStory($developer);

        $slack = $this->mock(SlackService::class);
        $slack->shouldReceive('getPresence')->never();

        $this->artisan('story:slack-revert-progress');

        $this->assertEquals(StoryStatus::Progress->value, $story->fresh()->status);
    }
}
```

- [ ] **Step 2: Esegui i test per verificare che falliscano (TDD)**

```bash
docker exec php81_orchestrator php artisan test --filter=SlackRevertProgressCommandTest
```

Atteso: FAIL — le classi non esistono ancora o i test rilevano comportamenti mancanti.

- [ ] **Step 3: Esegui i test dopo l'implementazione**

```bash
docker exec php81_orchestrator php artisan test --filter=SlackRevertProgressCommandTest
```

Atteso: tutti e 5 i test PASS.

- [ ] **Step 4: Esegui la suite completa per verificare no regressioni**

```bash
docker exec php81_orchestrator php artisan test
```

Atteso: suite verde.

- [ ] **Step 5: Commit**

```bash
git add tests/Feature/SlackRevertProgressCommandTest.php
git commit -m "feat(oc:8136): add feature tests for SlackRevertProgressCommand"
```
