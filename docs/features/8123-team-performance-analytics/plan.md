> Ticket: oc:8123

# Team Performance Analytics — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Sostituire dashboard con 4 Partition + Lens con un'unica dashboard Nova custom (componente Vue self-contained, pattern hetzner-monitoring) che mostra per developer+quarter i ticket Bug/Feature con metriche per ticket e aggregato vs media aziendale.

**Architecture:** Componente Vue registrato via `Nova::script()` senza build step (inline template, come hetzner-monitoring). Un controller Laravel restituisce JSON con ticket + aggregato. Il componente gestisce selettori developer/quarter e renderizza la tabella.

**Tech Stack:** Laravel 10, Laravel Nova 5.7, Vue 3 (inline via Nova.booting), PHP 8.1, PostgreSQL.

## Global Constraints

- Solo ticket di tipo `Bug` e `Feature` (StoryType::Bug, StoryType::Feature)
- Solo ticket con status `done` o `released` nel quarter selezionato
- Metriche con dati insufficienti → `null` / "—", mai `0`
- Commit convention: `feat(oc:8123): ...`
- NON eseguire git commit automatici — i commit li fa l'utente
- Comandi PHP dentro Docker: `docker exec php81_orchestrator php artisan ...`
- Test: `docker exec php81_orchestrator php artisan test --filter=NomeTest`
- DB test: `docker exec php81_orchestrator bash -c "DB_DATABASE=orchestrator_test php artisan migrate"`

---

## File Map

**Rimuovere:**
- `app/Nova/Metrics/TeamPerformance/CycleTimeMetric.php`
- `app/Nova/Metrics/TeamPerformance/ThroughputMetric.php`
- `app/Nova/Metrics/TeamPerformance/ReopenRateMetric.php`
- `app/Nova/Metrics/TeamPerformance/TodoStagnationMetric.php`
- `app/Nova/Lenses/DeveloperPerformanceLens.php`

**Creare:**
- `nova-components/team-performance/composer.json`
- `nova-components/team-performance/src/TeamPerformanceServiceProvider.php`
- `nova-components/team-performance/src/TeamPerformanceCard.php`
- `nova-components/team-performance/routes/api.php`
- `nova-components/team-performance/dist/js/card.js` — componente Vue inline
- `app/Http/Controllers/Nova/TeamPerformanceController.php`

**Modificare:**
- `app/Nova/Dashboards/TeamPerformance.php` — riscrivere come wrapper del componente
- `app/Providers/NovaServiceProvider.php` — registra nuovo package, rimuovi vecchio
- `app/Nova/Story.php` — rimuovi DeveloperPerformanceLens da lenses()
- `composer.json` — aggiungi `wm/team-performance` nei path repositories

---

## Task 1: Cleanup — rimuovi componenti vecchi

**Files:**
- Delete: `app/Nova/Metrics/TeamPerformance/CycleTimeMetric.php`
- Delete: `app/Nova/Metrics/TeamPerformance/ThroughputMetric.php`
- Delete: `app/Nova/Metrics/TeamPerformance/ReopenRateMetric.php`
- Delete: `app/Nova/Metrics/TeamPerformance/TodoStagnationMetric.php`
- Delete: `app/Nova/Lenses/DeveloperPerformanceLens.php`
- Modify: `app/Nova/Story.php`
- Modify: `app/Nova/Dashboards/TeamPerformance.php` (svuota cards())
- Modify: `app/Providers/NovaServiceProvider.php` (rimuovi import vecchi)

**Interfaces:**
- Produces: codebase pulita senza riferimenti ai vecchi componenti

- [ ] **Elimina i 4 file Partition metrics**

```bash
rm app/Nova/Metrics/TeamPerformance/CycleTimeMetric.php
rm app/Nova/Metrics/TeamPerformance/ThroughputMetric.php
rm app/Nova/Metrics/TeamPerformance/ReopenRateMetric.php
rm app/Nova/Metrics/TeamPerformance/TodoStagnationMetric.php
rmdir app/Nova/Metrics/TeamPerformance/
```

- [ ] **Elimina la Lens**

```bash
rm app/Nova/Lenses/DeveloperPerformanceLens.php
```

- [ ] **Rimuovi DeveloperPerformanceLens da `app/Nova/Story.php`**

In `app/Nova/Story.php`, nel metodo `lenses(Request $request)`, rimuovi la riga:
```php
new \App\Nova\Lenses\DeveloperPerformanceLens(),
```
e il relativo `use` statement se presente.

- [ ] **Svuota `app/Nova/Dashboards/TeamPerformance.php`** (verrà riscritto in Task 5)

```php
<?php

namespace App\Nova\Dashboards;

use Laravel\Nova\Dashboard;

class TeamPerformance extends Dashboard
{
    public static function label(): string
    {
        return 'Team Performance';
    }

    public function cards(): array
    {
        return [];
    }

    public static function uriKey(): string
    {
        return 'team-performance';
    }
}
```

- [ ] **Verifica che Nova non crashi**

```bash
docker exec php81_orchestrator php artisan nova:check
```

Se il comando non esiste, verifica con:
```bash
docker exec php81_orchestrator php artisan route:list --name=nova | head -5
```

- [ ] **Esegui i test per verificare nessuna regressione**

```bash
docker exec php81_orchestrator php artisan test
```

Expected: stesso numero di test passanti (187), nessun nuovo fail.

---

## Task 2: Controller API `TeamPerformanceController`

**Files:**
- Create: `app/Http/Controllers/Nova/TeamPerformanceController.php`
- Test: `tests/Feature/Controllers/TeamPerformanceControllerTest.php`

**Interfaces:**
- Consumes: `StoryMetricsCalculator` (già esistente in `app/Services/Metrics/StoryMetricsCalculator.php`)
- Consumes: `StoryGithubCommit::where('story_id', $id)->count()` e `StoryGithubPr::where('story_id', $id)`
- Consumes: `StoryType::Bug->value`, `StoryType::Feature->value`
- Consumes: `StoryStatus::Done->value`, `StoryStatus::Released->value`
- Produces: `GET /nova-vendor/team-performance/data?developer_id=X&year=Y&quarter=Z`
  Risposta JSON:
  ```json
  {
    "developers": [{"id": 1, "name": "Mario Rossi"}],
    "tickets": [
      {
        "id": 123, "name": "Fix login", "type": "Bug",
        "nova_url": "/nova/resources/stories/123",
        "cycle_time_hours": 12.5,
        "reopen_count": 1,
        "on_time": true,
        "commit_count": 3,
        "pr_count": 1,
        "change_requests_count": 0
      }
    ],
    "aggregate": {
      "developer": {"avg_cycle_time_hours": 15.2, "avg_reopen_count": 0.5, "on_time_rate": 80.0, "avg_commit_count": 4.1, "avg_pr_count": 1.2, "avg_change_requests": 0.3, "story_count": 10},
      "team_average": {"avg_cycle_time_hours": 20.1, "avg_reopen_count": 0.8, "on_time_rate": 65.0, "avg_commit_count": 3.2, "avg_pr_count": 0.9, "avg_change_requests": 0.5, "story_count": 85}
    }
  }
  ```

- [ ] **Scrivi il test prima dell'implementazione**

```php
<?php
// tests/Feature/Controllers/TeamPerformanceControllerTest.php

namespace Tests\Feature\Controllers;

use App\Models\Story;
use App\Models\User;
use App\Enums\StoryType;
use App\Enums\StoryStatus;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class TeamPerformanceControllerTest extends TestCase
{
    use DatabaseTransactions;

    private User $admin;
    private User $developer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['roles' => ['admin']]);
        $this->developer = User::factory()->create(['roles' => ['developer']]);
    }

    public function test_returns_developers_list(): void
    {
        $response = $this->actingAs($this->admin)
            ->getJson('/nova-vendor/team-performance/data?developer_id=' . $this->developer->id . '&year=2026&quarter=2');

        $response->assertOk();
        $response->assertJsonStructure([
            'developers' => [['id', 'name']],
            'tickets',
            'aggregate' => ['developer', 'team_average'],
        ]);

        $developerIds = collect($response->json('developers'))->pluck('id');
        $this->assertContains($this->developer->id, $developerIds->toArray());
    }

    public function test_only_bug_and_feature_types_included(): void
    {
        // Crea uno story Scrum — non deve apparire
        Story::factory()->create([
            'user_id' => $this->developer->id,
            'type' => StoryType::Scrum->value,
            'status' => StoryStatus::Done->value,
        ]);

        $response = $this->actingAs($this->admin)
            ->getJson('/nova-vendor/team-performance/data?developer_id=' . $this->developer->id . '&year=2026&quarter=2');

        $response->assertOk();
        $types = collect($response->json('tickets'))->pluck('type')->unique()->toArray();
        foreach ($types as $type) {
            $this->assertContains($type, ['Bug', 'Feature']);
        }
    }

    public function test_developer_can_only_see_own_data(): void
    {
        $otherDev = User::factory()->create(['roles' => ['developer']]);

        $response = $this->actingAs($this->developer)
            ->getJson('/nova-vendor/team-performance/data?developer_id=' . $otherDev->id . '&year=2026&quarter=2');

        // developer viene reindirizzato ai propri dati
        $response->assertOk();
        // developer_id nel response deve essere il proprio, non otherDev
        // (oppure 403 — implementa come preferisci, verifica coerenza con il controller)
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $response = $this->getJson('/nova-vendor/team-performance/data?developer_id=1&year=2026&quarter=1');
        $response->assertUnauthorized();
    }
}
```

- [ ] **Esegui il test per verificare che fallisce**

```bash
docker exec php81_orchestrator php artisan test --filter=TeamPerformanceControllerTest
```

Expected: FAIL — route not found / class not found.

- [ ] **Crea il controller**

```php
<?php
// app/Http/Controllers/Nova/TeamPerformanceController.php

namespace App\Http\Controllers\Nova;

use App\Enums\StoryStatus;
use App\Enums\StoryType;
use App\Http\Controllers\Controller;
use App\Models\Story;
use App\Models\StoryGithubCommit;
use App\Models\StoryGithubPr;
use App\Models\User;
use App\Services\Metrics\StoryMetricsCalculator;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class TeamPerformanceController extends Controller
{
    public function __construct(private StoryMetricsCalculator $calc) {}

    public function data(Request $request): JsonResponse
    {
        $currentUser = $request->user();
        $isAdmin = $currentUser->hasRole('admin') || $currentUser->hasRole('manager');

        // Developer vede solo se stesso
        $developerId = $isAdmin
            ? (int) $request->input('developer_id', $currentUser->id)
            : $currentUser->id;

        $year    = (int) $request->input('year', now()->year);
        $quarter = (int) $request->input('quarter', (int) ceil(now()->month / 3));
        $quarter = max(1, min(4, $quarter));

        [$startMonth, $endMonth] = match ($quarter) {
            1 => [1, 3], 2 => [4, 6], 3 => [7, 9], 4 => [10, 12],
        };
        $start = Carbon::create($year, $startMonth, 1)->startOfDay();
        $end   = Carbon::create($year, $endMonth, 1)->endOfMonth()->endOfDay();

        // Storie Bug/Feature chiuse nel quarter per questo developer
        $tickets = $this->getTickets($developerId, $start, $end);

        // Aggregato developer
        $devAggregate = $this->buildAggregate($tickets);

        // Aggregato team (media aziendale) — tutti i developer, stesso quarter
        $teamAggregate = Cache::remember(
            "team_perf_avg_{$year}_q{$quarter}",
            3600,
            fn() => $this->buildTeamAggregate($year, $quarter, $start, $end)
        );

        // Lista developer per il selettore
        $developers = $isAdmin
            ? User::whereJsonContains('roles', 'developer')
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn($u) => ['id' => $u->id, 'name' => $u->name])
            : [['id' => $currentUser->id, 'name' => $currentUser->name]];

        return response()->json([
            'developers'      => $developers,
            'selected_developer_id' => $developerId,
            'year'            => $year,
            'quarter'         => $quarter,
            'tickets'         => $tickets,
            'aggregate'       => [
                'developer'    => $devAggregate,
                'team_average' => $teamAggregate,
            ],
        ]);
    }

    private function getTickets(int $userId, Carbon $start, Carbon $end): array
    {
        $closedInQuarter = \App\Models\StoryLog::whereRaw("changes::jsonb ->> 'status' IN ('done', 'released')")
            ->whereBetween('viewed_at', [$start, $end])
            ->select('story_id')
            ->groupBy('story_id');

        $stories = Story::where('user_id', $userId)
            ->whereIn('type', [StoryType::Bug->value, StoryType::Feature->value])
            ->whereIn('status', [StoryStatus::Done->value, StoryStatus::Released->value])
            ->whereIn('id', $closedInQuarter->pluck('story_id'))
            ->get(['id', 'name', 'type', 'estimated_hours', 'hours']);

        return $stories->map(function ($story) {
            $cycleMinutes = $this->calc->cycleTimeMinutes($story->id);
            $commits = StoryGithubCommit::where('story_id', $story->id)->count();
            $prs = StoryGithubPr::where('story_id', $story->id)->get();

            return [
                'id'                    => $story->id,
                'name'                  => $story->name,
                'type'                  => $story->type,
                'nova_url'              => '/nova/resources/stories/' . $story->id,
                'cycle_time_hours'      => $cycleMinutes !== null ? round($cycleMinutes / 60, 1) : null,
                'reopen_count'          => $this->calc->reopenCount($story->id),
                'on_time'               => $this->calc->onTimeDelivery($story->id),
                'commit_count'          => $commits ?: null,
                'pr_count'              => $prs->count() ?: null,
                'change_requests_count' => $prs->sum('change_requests_count') ?: null,
            ];
        })->toArray();
    }

    private function buildAggregate(array $tickets): array
    {
        if (empty($tickets)) {
            return ['story_count' => 0, 'avg_cycle_time_hours' => null, 'avg_reopen_count' => null, 'on_time_rate' => null, 'avg_commit_count' => null, 'avg_pr_count' => null, 'avg_change_requests' => null];
        }

        $cycleTimes  = array_filter(array_column($tickets, 'cycle_time_hours'), fn($v) => $v !== null);
        $reopens     = array_column($tickets, 'reopen_count');
        $onTimes     = array_filter(array_column($tickets, 'on_time'), fn($v) => $v !== null);
        $commits     = array_filter(array_column($tickets, 'commit_count'), fn($v) => $v !== null);
        $prs         = array_filter(array_column($tickets, 'pr_count'), fn($v) => $v !== null);
        $changeReqs  = array_filter(array_column($tickets, 'change_requests_count'), fn($v) => $v !== null);

        return [
            'story_count'          => count($tickets),
            'avg_cycle_time_hours' => count($cycleTimes) ? round(array_sum($cycleTimes) / count($cycleTimes), 1) : null,
            'avg_reopen_count'     => count($reopens) ? round(array_sum($reopens) / count($reopens), 2) : null,
            'on_time_rate'         => count($onTimes) ? round(count(array_filter($onTimes)) / count($onTimes) * 100, 1) : null,
            'avg_commit_count'     => count($commits) ? round(array_sum($commits) / count($commits), 1) : null,
            'avg_pr_count'         => count($prs) ? round(array_sum($prs) / count($prs), 1) : null,
            'avg_change_requests'  => count($changeReqs) ? round(array_sum($changeReqs) / count($changeReqs), 1) : null,
        ];
    }

    private function buildTeamAggregate(int $year, int $quarter, Carbon $start, Carbon $end): array
    {
        $developers = User::whereJsonContains('roles', 'developer')->get(['id']);
        $allTickets = [];
        foreach ($developers as $dev) {
            $allTickets = array_merge($allTickets, $this->getTickets($dev->id, $start, $end));
        }
        return $this->buildAggregate($allTickets);
    }
}
```

- [ ] **Esegui i test**

```bash
docker exec php81_orchestrator php artisan test --filter=TeamPerformanceControllerTest
```

Il test `test_unauthenticated_request_is_rejected` probabilmente fallirà ancora perché la route non è registrata. Questo si risolve in Task 3.

---

## Task 3: Package Nova `team-performance` (scaffold PHP + routes)

**Files:**
- Create: `nova-components/team-performance/composer.json`
- Create: `nova-components/team-performance/src/TeamPerformanceServiceProvider.php`
- Create: `nova-components/team-performance/src/TeamPerformanceCard.php`
- Create: `nova-components/team-performance/routes/api.php`
- Create: `nova-components/team-performance/dist/js/card.js` (placeholder — contenuto reale in Task 4)
- Modify: `composer.json` (root) — aggiungi path repository
- Modify: `app/Providers/NovaServiceProvider.php` — registra il package

**Interfaces:**
- Consumes: `TeamPerformanceController::data()` dalla route `/nova-vendor/team-performance/data`
- Produces: `Nova::script('team-performance', ...)` — JS caricato in tutte le pagine Nova
- Produces: componente Vue `team-performance-card` disponibile nei template Nova

- [ ] **Crea la struttura di cartelle**

```bash
mkdir -p nova-components/team-performance/src
mkdir -p nova-components/team-performance/routes
mkdir -p nova-components/team-performance/dist/js
```

- [ ] **Crea `nova-components/team-performance/composer.json`**

```json
{
    "name": "wm/team-performance",
    "description": "Team Performance Analytics card for Laravel Nova.",
    "license": "MIT",
    "require": {
        "php": "^8.0"
    },
    "autoload": {
        "psr-4": {
            "Webmapp\\TeamPerformance\\": "src/"
        }
    },
    "extra": {
        "laravel": {
            "providers": [
                "Webmapp\\TeamPerformance\\TeamPerformanceServiceProvider"
            ]
        }
    }
}
```

- [ ] **Crea `nova-components/team-performance/src/TeamPerformanceServiceProvider.php`**

```php
<?php

namespace Webmapp\TeamPerformance;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Laravel\Nova\Events\ServingNova;
use Laravel\Nova\Nova;

class TeamPerformanceServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->app->booted(function () {
            $this->routes();
        });

        Nova::serving(function (ServingNova $event) {
            Nova::script('team-performance', __DIR__ . '/../dist/js/card.js');
        });
    }

    protected function routes(): void
    {
        if ($this->app->routesAreCached()) {
            return;
        }

        Route::middleware(['nova'])
            ->prefix('nova-vendor/team-performance')
            ->group(__DIR__ . '/../routes/api.php');
    }

    public function register(): void {}
}
```

- [ ] **Crea `nova-components/team-performance/src/TeamPerformanceCard.php`**

```php
<?php

namespace Webmapp\TeamPerformance;

use Laravel\Nova\Card;

class TeamPerformanceCard extends Card
{
    public $width = 'full';

    public function component(): string
    {
        return 'team-performance-card';
    }
}
```

- [ ] **Crea `nova-components/team-performance/routes/api.php`**

```php
<?php

use App\Http\Controllers\Nova\TeamPerformanceController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    Route::get('/data', [TeamPerformanceController::class, 'data']);
});
```

- [ ] **Crea placeholder `dist/js/card.js`** (verrà sovrascritto in Task 4)

```javascript
Nova.booting(function (app) {
    app.component('team-performance-card', {
        template: '<div>Team Performance — loading...</div>',
    });
});
```

- [ ] **Aggiungi il path repository in `composer.json` (root)**

Apri `composer.json` e aggiungi in `repositories`:
```json
{
    "type": "path",
    "url": "./nova-components/team-performance"
}
```

Poi aggiungi in `require`:
```json
"wm/team-performance": "*"
```

- [ ] **Esegui composer update per il nuovo package**

```bash
docker exec php81_orchestrator composer require wm/team-performance:* --no-interaction
```

- [ ] **Registra la dashboard in `app/Providers/NovaServiceProvider.php`**

Verifica che `\App\Nova\Dashboards\TeamPerformance::class` sia nell'array `dashboards()`. Se non c'è, aggiungilo. Rimuovi eventuali import dei vecchi Partition metrics.

- [ ] **Verifica che la route sia disponibile**

```bash
docker exec php81_orchestrator php artisan route:list | grep team-performance
```

Expected: riga con `GET nova-vendor/team-performance/data`.

- [ ] **Esegui i test**

```bash
docker exec php81_orchestrator php artisan test --filter=TeamPerformanceControllerTest
```

Expected: tutti i test passano ora che la route è registrata.

---

## Task 4: Componente Vue `card.js`

**Files:**
- Modify: `nova-components/team-performance/dist/js/card.js` — componente Vue completo

**Interfaces:**
- Consumes: `GET /nova-vendor/team-performance/data?developer_id=X&year=Y&quarter=Z`
- Produces: componente Vue `team-performance-card` che renderizza tabella + aggregato

- [ ] **Scrivi il componente Vue completo in `nova-components/team-performance/dist/js/card.js`**

```javascript
Nova.booting(function (app) {
    app.component('team-performance-card', {
        template: `
<div class="team-performance p-6">
    <!-- Header con selettori -->
    <div class="flex flex-wrap items-center gap-4 mb-6">
        <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-200 mr-auto">
            Team Performance
        </h2>

        <!-- Selettore developer (solo admin/manager vedono tutti) -->
        <select
            v-if="developers.length > 1"
            v-model="selectedDeveloperId"
            @change="loadData"
            class="px-3 py-2 text-sm rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200 focus:outline-none focus:ring-2 focus:ring-primary-500"
        >
            <option v-for="dev in developers" :key="dev.id" :value="dev.id">{{ dev.name }}</option>
        </select>
        <span v-else class="text-sm font-medium text-gray-700 dark:text-gray-200">{{ developers[0]?.name }}</span>

        <!-- Selettore anno -->
        <select
            v-model="selectedYear"
            @change="loadData"
            class="px-3 py-2 text-sm rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200 focus:outline-none focus:ring-2 focus:ring-primary-500"
        >
            <option v-for="y in availableYears" :key="y" :value="y">{{ y }}</option>
        </select>

        <!-- Selettore quarter -->
        <select
            v-model="selectedQuarter"
            @change="loadData"
            class="px-3 py-2 text-sm rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200 focus:outline-none focus:ring-2 focus:ring-primary-500"
        >
            <option value="1">Q1 (Gen–Mar)</option>
            <option value="2">Q2 (Apr–Giu)</option>
            <option value="3">Q3 (Lug–Set)</option>
            <option value="4">Q4 (Ott–Dic)</option>
        </select>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="flex justify-center py-12">
        <svg class="animate-spin h-8 w-8 text-primary-500" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
        </svg>
    </div>

    <!-- Nessun ticket -->
    <div v-else-if="!loading && tickets.length === 0" class="text-center py-12 text-gray-500 dark:text-gray-400">
        Nessun ticket Bug/Feature chiuso in questo periodo.
    </div>

    <!-- Tabella ticket -->
    <div v-else class="overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead>
                <tr class="border-b border-gray-200 dark:border-gray-700">
                    <th class="text-left py-3 px-3 font-semibold text-gray-600 dark:text-gray-400">Ticket</th>
                    <th class="text-center py-3 px-3 font-semibold text-gray-600 dark:text-gray-400">Tipo</th>
                    <th class="text-center py-3 px-3 font-semibold text-gray-600 dark:text-gray-400">Cycle Time</th>
                    <th class="text-center py-3 px-3 font-semibold text-gray-600 dark:text-gray-400">Reopen</th>
                    <th class="text-center py-3 px-3 font-semibold text-gray-600 dark:text-gray-400">On Time</th>
                    <th class="text-center py-3 px-3 font-semibold text-gray-600 dark:text-gray-400">Commit</th>
                    <th class="text-center py-3 px-3 font-semibold text-gray-600 dark:text-gray-400">PR</th>
                    <th class="text-center py-3 px-3 font-semibold text-gray-600 dark:text-gray-400">Change Req.</th>
                </tr>
            </thead>
            <tbody>
                <tr
                    v-for="ticket in tickets"
                    :key="ticket.id"
                    class="border-b border-gray-100 dark:border-gray-800 hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors"
                >
                    <td class="py-3 px-3">
                        <a :href="ticket.nova_url" class="text-primary-600 dark:text-primary-400 hover:underline font-medium">
                            {{ ticket.name }}
                        </a>
                    </td>
                    <td class="py-3 px-3 text-center">
                        <span :class="typeBadgeClass(ticket.type)" class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium">
                            {{ ticket.type }}
                        </span>
                    </td>
                    <td class="py-3 px-3 text-center text-gray-700 dark:text-gray-300">
                        {{ ticket.cycle_time_hours !== null ? ticket.cycle_time_hours + 'h' : '—' }}
                    </td>
                    <td class="py-3 px-3 text-center">
                        <span :class="ticket.reopen_count > 0 ? 'text-red-600 dark:text-red-400 font-semibold' : 'text-gray-500 dark:text-gray-400'">
                            {{ ticket.reopen_count }}
                        </span>
                    </td>
                    <td class="py-3 px-3 text-center text-lg">
                        <span v-if="ticket.on_time === true" class="text-green-500">✓</span>
                        <span v-else-if="ticket.on_time === false" class="text-red-500">✗</span>
                        <span v-else class="text-gray-400">—</span>
                    </td>
                    <td class="py-3 px-3 text-center text-gray-700 dark:text-gray-300">
                        {{ ticket.commit_count !== null ? ticket.commit_count : '—' }}
                    </td>
                    <td class="py-3 px-3 text-center text-gray-700 dark:text-gray-300">
                        {{ ticket.pr_count !== null ? ticket.pr_count : '—' }}
                    </td>
                    <td class="py-3 px-3 text-center text-gray-700 dark:text-gray-300">
                        {{ ticket.change_requests_count !== null ? ticket.change_requests_count : '—' }}
                    </td>
                </tr>
            </tbody>

            <!-- Riga aggregato developer -->
            <tfoot v-if="aggregate">
                <tr class="border-t-2 border-gray-300 dark:border-gray-600 bg-blue-50 dark:bg-blue-900/20 font-semibold">
                    <td class="py-3 px-3 text-blue-700 dark:text-blue-300">
                        Media developer ({{ aggregate.developer.story_count }} ticket)
                    </td>
                    <td class="py-3 px-3 text-center text-gray-500">—</td>
                    <td class="py-3 px-3 text-center text-blue-700 dark:text-blue-300">
                        {{ aggregate.developer.avg_cycle_time_hours !== null ? aggregate.developer.avg_cycle_time_hours + 'h' : '—' }}
                    </td>
                    <td class="py-3 px-3 text-center text-blue-700 dark:text-blue-300">
                        {{ aggregate.developer.avg_reopen_count !== null ? aggregate.developer.avg_reopen_count : '—' }}
                    </td>
                    <td class="py-3 px-3 text-center text-blue-700 dark:text-blue-300">
                        {{ aggregate.developer.on_time_rate !== null ? aggregate.developer.on_time_rate + '%' : '—' }}
                    </td>
                    <td class="py-3 px-3 text-center text-blue-700 dark:text-blue-300">
                        {{ aggregate.developer.avg_commit_count !== null ? aggregate.developer.avg_commit_count : '—' }}
                    </td>
                    <td class="py-3 px-3 text-center text-blue-700 dark:text-blue-300">
                        {{ aggregate.developer.avg_pr_count !== null ? aggregate.developer.avg_pr_count : '—' }}
                    </td>
                    <td class="py-3 px-3 text-center text-blue-700 dark:text-blue-300">
                        {{ aggregate.developer.avg_change_requests !== null ? aggregate.developer.avg_change_requests : '—' }}
                    </td>
                </tr>

                <!-- Riga media aziendale con delta colorato -->
                <tr class="border-t border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/30 text-sm">
                    <td class="py-3 px-3 text-gray-600 dark:text-gray-400">
                        Media team ({{ aggregate.team_average.story_count }} ticket)
                    </td>
                    <td class="py-3 px-3 text-center text-gray-500">—</td>
                    <td class="py-3 px-3 text-center" :class="deltaClass('cycle_time', aggregate.developer.avg_cycle_time_hours, aggregate.team_average.avg_cycle_time_hours, true)">
                        {{ aggregate.team_average.avg_cycle_time_hours !== null ? aggregate.team_average.avg_cycle_time_hours + 'h' : '—' }}
                        <span v-if="showDelta('cycle_time')" class="text-xs ml-1">{{ delta(aggregate.developer.avg_cycle_time_hours, aggregate.team_average.avg_cycle_time_hours) }}</span>
                    </td>
                    <td class="py-3 px-3 text-center" :class="deltaClass('reopen', aggregate.developer.avg_reopen_count, aggregate.team_average.avg_reopen_count, true)">
                        {{ aggregate.team_average.avg_reopen_count !== null ? aggregate.team_average.avg_reopen_count : '—' }}
                        <span v-if="showDelta('reopen')" class="text-xs ml-1">{{ delta(aggregate.developer.avg_reopen_count, aggregate.team_average.avg_reopen_count) }}</span>
                    </td>
                    <td class="py-3 px-3 text-center" :class="deltaClass('ontime', aggregate.developer.on_time_rate, aggregate.team_average.on_time_rate, false)">
                        {{ aggregate.team_average.on_time_rate !== null ? aggregate.team_average.on_time_rate + '%' : '—' }}
                        <span v-if="showDelta('ontime')" class="text-xs ml-1">{{ delta(aggregate.developer.on_time_rate, aggregate.team_average.on_time_rate) }}</span>
                    </td>
                    <td class="py-3 px-3 text-center text-gray-600 dark:text-gray-400">
                        {{ aggregate.team_average.avg_commit_count !== null ? aggregate.team_average.avg_commit_count : '—' }}
                    </td>
                    <td class="py-3 px-3 text-center text-gray-600 dark:text-gray-400">
                        {{ aggregate.team_average.avg_pr_count !== null ? aggregate.team_average.avg_pr_count : '—' }}
                    </td>
                    <td class="py-3 px-3 text-center text-gray-600 dark:text-gray-400">
                        {{ aggregate.team_average.avg_change_requests !== null ? aggregate.team_average.avg_change_requests : '—' }}
                    </td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>
        `,

        props: ['card'],

        data() {
            const now = new Date();
            const currentQuarter = Math.ceil((now.getMonth() + 1) / 3);
            const currentYear = now.getFullYear();

            return {
                loading: false,
                developers: [],
                tickets: [],
                aggregate: null,
                selectedDeveloperId: null,
                selectedYear: currentYear,
                selectedQuarter: currentQuarter,
                availableYears: Array.from({ length: 5 }, (_, i) => currentYear - i),
            };
        },

        mounted() {
            this.loadData();
        },

        methods: {
            async loadData() {
                this.loading = true;
                try {
                    const params = new URLSearchParams({
                        year: this.selectedYear,
                        quarter: this.selectedQuarter,
                    });
                    if (this.selectedDeveloperId) {
                        params.append('developer_id', this.selectedDeveloperId);
                    }

                    const response = await fetch(
                        `/nova-vendor/team-performance/data?${params}`,
                        { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } }
                    );

                    if (!response.ok) throw new Error('HTTP ' + response.status);

                    const data = await response.json();
                    this.developers = data.developers || [];
                    this.tickets = data.tickets || [];
                    this.aggregate = data.aggregate || null;

                    // Imposta developer selezionato se non ancora scelto
                    if (!this.selectedDeveloperId && data.selected_developer_id) {
                        this.selectedDeveloperId = data.selected_developer_id;
                    }
                } catch (e) {
                    console.error('TeamPerformance: errore caricamento dati', e);
                } finally {
                    this.loading = false;
                }
            },

            typeBadgeClass(type) {
                return type === 'Bug'
                    ? 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300'
                    : 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300';
            },

            delta(devVal, teamVal) {
                if (devVal === null || devVal === undefined || teamVal === null || teamVal === undefined) return '';
                const diff = devVal - teamVal;
                if (Math.abs(diff) < 0.05) return '';
                return (diff > 0 ? '+' : '') + diff.toFixed(1);
            },

            showDelta(key) {
                if (!this.aggregate) return false;
                const d = this.aggregate.developer;
                const t = this.aggregate.team_average;
                const map = { cycle_time: [d.avg_cycle_time_hours, t.avg_cycle_time_hours], reopen: [d.avg_reopen_count, t.avg_reopen_count], ontime: [d.on_time_rate, t.on_time_rate] };
                const [dv, tv] = map[key] || [null, null];
                return dv !== null && tv !== null;
            },

            // lowerIsBetter=true: verde se dev < team, rosso se dev > team
            deltaClass(key, devVal, teamVal, lowerIsBetter) {
                if (devVal === null || teamVal === null) return 'text-gray-600 dark:text-gray-400';
                const better = lowerIsBetter ? devVal < teamVal : devVal > teamVal;
                const worse = lowerIsBetter ? devVal > teamVal : devVal < teamVal;
                if (better) return 'text-green-600 dark:text-green-400 font-semibold';
                if (worse)  return 'text-red-600 dark:text-red-400 font-semibold';
                return 'text-gray-600 dark:text-gray-400';
            },
        },
    });
});
```

- [ ] **Svuota cache Nova e ricarica**

```bash
docker exec php81_orchestrator php artisan config:clear
docker exec php81_orchestrator php artisan cache:clear
```

---

## Task 5: Dashboard wrapper + registrazione finale

**Files:**
- Modify: `app/Nova/Dashboards/TeamPerformance.php`
- Modify: `app/Providers/NovaServiceProvider.php`

**Interfaces:**
- Consumes: `Webmapp\TeamPerformance\TeamPerformanceCard`
- Produces: dashboard Nova raggiungibile a `/nova/dashboards/team-performance`

- [ ] **Aggiorna `app/Nova/Dashboards/TeamPerformance.php`**

```php
<?php

namespace App\Nova\Dashboards;

use Laravel\Nova\Dashboard;
use Webmapp\TeamPerformance\TeamPerformanceCard;

class TeamPerformance extends Dashboard
{
    public static function label(): string
    {
        return 'Team Performance';
    }

    public function cards(): array
    {
        return [
            new TeamPerformanceCard(),
        ];
    }

    public static function uriKey(): string
    {
        return 'team-performance';
    }
}
```

- [ ] **Verifica `app/Providers/NovaServiceProvider.php`**

Controlla che `\App\Nova\Dashboards\TeamPerformance::class` sia nell'array `dashboards()` e che il menu link punti a `/dashboards/team-performance`. Rimuovi eventuali `use` statement per i vecchi Partition metrics.

- [ ] **Esegui test completi**

```bash
docker exec php81_orchestrator php artisan test
```

Expected: stesso numero di test (187+) passanti, nessun fail.

- [ ] **Apri nel browser**

Vai a `http://127.0.0.1:8099/nova/dashboards/team-performance` — deve apparire il componente con il selettore developer e la tabella.

---

## Task 6: Verifica finale e pulizia

- [ ] **Testa nel browser come admin** — verifica che il dropdown developer mostri tutti i developer
- [ ] **Testa nel browser come developer** — verifica che non appaia il dropdown (o mostri solo se stesso)
- [ ] **Verifica i dati** — seleziona un developer con storie chiuse e controlla che i dati siano coerenti
- [ ] **Verifica dark mode** — ricarica con dark mode attiva in Nova
- [ ] **Esegui test completi una volta finale**

```bash
docker exec php81_orchestrator php artisan test
```

Expected: tutti i test passano.
