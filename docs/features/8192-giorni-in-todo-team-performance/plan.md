> Ticket: oc:8192

# Mostra metrica "giorni in todo" nella card team performance — Piano

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Esporre i giorni lavorativi totali trascorsi in stato `todo` per ogni ticket nella card Nova team performance, sia per-ticket che come KPI aggregato.

**Architecture:** Aggiungere `todoStagnationTotalDays()` in `StoryMetricsCalculator` (somma tutti gli intervalli `todo`), esporlo nel controller, visualizzarlo nel componente Vue `card.js` (modificato direttamente, nessun sorgente).

**Tech Stack:** Laravel 10, PHP 8.1, Vue 3 (compilato in `dist/js/card.js`), PostgreSQL, Docker (`php81_orchestrator`).

## Global Constraints

- Commit convention: `feat(oc:8192): ...`
- Nessun commit automatico — solo su istruzione esplicita del developer
- Test eseguiti dentro il container: `docker exec php81_orchestrator php artisan test --filter=<test>`
- DB di test: `orchestrator_test` (configurato in `phpunit.xml`) — mai `DB_DATABASE=orchestrator`
- `null` = dato non calcolabile → mostrare `—`; `0` = dato calcolato e pari a zero → mostrare `0`
- Validare sintassi `card.js` con `node --check` prima del commit

---

### Task 1: `todoStagnationTotalDays()` in `StoryMetricsCalculator`

**Files:**
- Modify: `app/Services/Metrics/StoryMetricsCalculator.php:125-127`
- Test: `tests/Unit/Services/Metrics/StoryMetricsCalculatorTest.php`

**Interfaces:**
- Produces: `todoStagnationTotalDays(int $storyId): ?int` — somma giorni lavorativi di tutti gli intervalli in `todo`; `null` se nessun log `todo` trovato

- [ ] **Step 1: Scrivi i test**

Apri `tests/Unit/Services/Metrics/StoryMetricsCalculatorTest.php` e aggiungi dopo il test `test_todo_stagnation_counts_working_days` (riga 177):

```php
// --- Todo stagnation total ---

public function test_todo_stagnation_total_sums_multiple_intervals(): void
{
    $story = Story::factory()->create();
    $monday = Carbon::parse('next monday');

    // Primo intervallo: lunedì → mercoledì = 2 giorni
    StoryLog::create(['story_id' => $story->id, 'user_id' => $this->user->id, 'viewed_at' => $monday, 'changes' => ['status' => 'todo']]);
    StoryLog::create(['story_id' => $story->id, 'user_id' => $this->user->id, 'viewed_at' => $monday->copy()->addDays(2), 'changes' => ['status' => 'progress']]);

    // Secondo intervallo: venerdì → lunedì successivo = 1 giorno lavorativo
    StoryLog::create(['story_id' => $story->id, 'user_id' => $this->user->id, 'viewed_at' => $monday->copy()->addDays(4), 'changes' => ['status' => 'todo']]);
    StoryLog::create(['story_id' => $story->id, 'user_id' => $this->user->id, 'viewed_at' => $monday->copy()->addDays(7), 'changes' => ['status' => 'progress']]);

    $this->assertEquals(3, $this->calc->todoStagnationTotalDays($story->id));
}

public function test_todo_stagnation_total_returns_null_when_no_todo_logs(): void
{
    $story = Story::factory()->create();
    $monday = Carbon::parse('next monday');

    StoryLog::create(['story_id' => $story->id, 'user_id' => $this->user->id, 'viewed_at' => $monday, 'changes' => ['status' => 'progress']]);
    StoryLog::create(['story_id' => $story->id, 'user_id' => $this->user->id, 'viewed_at' => $monday->copy()->addDays(2), 'changes' => ['status' => 'done']]);

    $this->assertNull($this->calc->todoStagnationTotalDays($story->id));
}

public function test_todo_stagnation_total_single_interval(): void
{
    $story = Story::factory()->create();
    $monday = Carbon::parse('next monday');

    StoryLog::create(['story_id' => $story->id, 'user_id' => $this->user->id, 'viewed_at' => $monday, 'changes' => ['status' => 'todo']]);
    StoryLog::create(['story_id' => $story->id, 'user_id' => $this->user->id, 'viewed_at' => $monday->copy()->addDays(2), 'changes' => ['status' => 'progress']]);

    $this->assertEquals(2, $this->calc->todoStagnationTotalDays($story->id));
}
```

- [ ] **Step 2: Esegui i test per verificare che falliscano**

```bash
docker exec php81_orchestrator php artisan test --filter=test_todo_stagnation_total
```

Atteso: FAIL con `Call to undefined method ... todoStagnationTotalDays()`

- [ ] **Step 3: Implementa `todoStagnationTotalDays()`**

In `app/Services/Metrics/StoryMetricsCalculator.php`, aggiungi dopo `todoStagnationDays()` (riga 128):

```php
/**
 * Giorni lavorativi totali in cui la story è rimasta in `todo`, sommando tutti gli intervalli.
 * Restituisce null se non esistono log con status `todo`.
 */
public function todoStagnationTotalDays(int $storyId): ?int
{
    $logs = $this->getStatusLogs($storyId);
    $totalDays = 0;
    $hasTodo = false;

    for ($i = 0; $i < $logs->count(); $i++) {
        if ($logs[$i]['status'] !== 'todo') {
            continue;
        }

        $hasTodo = true;
        $start = Carbon::parse($logs[$i]['viewed_at']);
        $end   = isset($logs[$i + 1]) ? Carbon::parse($logs[$i + 1]['viewed_at']) : Carbon::now();
        $totalDays += $this->workingDaysBetween($start, $end);
    }

    return $hasTodo ? $totalDays : null;
}
```

- [ ] **Step 4: Esegui i test per verificare che passino**

```bash
docker exec php81_orchestrator php artisan test --filter=test_todo_stagnation_total
```

Atteso: 3 test PASS

- [ ] **Step 5: Esegui tutta la suite del calculator per verificare nessuna regressione**

```bash
docker exec php81_orchestrator php artisan test --filter=StoryMetricsCalculatorTest
```

Atteso: tutti i test esistenti PASS

---

### Task 2: Esponi `todo_stagnation_days` nel controller

**Files:**
- Modify: `app/Http/Controllers/Nova/TeamPerformanceController.php`

**Interfaces:**
- Consumes: `StoryMetricsCalculator::todoStagnationTotalDays(int $storyId): ?int`
- Produces:
  - `getTickets()` → aggiunge `'todo_stagnation_days' => int|null` per ogni ticket
  - `buildAggregate()` → aggiunge `'avg_todo_stagnation_days' => float|null`
  - `buildTeamAggregate()` → aggiunge `'avg_todo_stagnation_days' => float|null`

- [ ] **Step 1: Aggiungi `todo_stagnation_days` in `getTickets()`**

In `TeamPerformanceController::getTickets()`, nel return del `map()` (intorno alla riga 104-117), aggiungi la riga dopo `'change_requests_count'`:

```php
'todo_stagnation_days'  => $this->calc->todoStagnationTotalDays($story->id),
```

Il blocco completo del return diventa:

```php
return [
    'id'                    => $story->id,
    'name'                  => $story->name,
    'type'                  => $story->type,
    'nova_url'              => '/resources/customer-stories/' . $story->id,
    'cycle_time_hours'      => $cycleMinutes !== null ? round($cycleMinutes / 60, 1) : null,
    'reopen_count'          => $this->calc->reopenCount($story->id),
    'on_time'               => $this->calc->onTimeDelivery($story->id, $teamAvgCycleMinutes),
    'on_time_diff_hours'    => $this->onTimeDiff($story->id, $teamAvgCycleMinutes),
    'on_time_detail'        => $this->onTimeDetail($story, $teamAvgCycleMinutes),
    'commit_count'          => $commits ?: null,
    'pr_count'              => $prs->count() ?: null,
    'change_requests_count' => $prs->sum('change_requests_count') ?: null,
    'todo_stagnation_days'  => $this->calc->todoStagnationTotalDays($story->id),
];
```

- [ ] **Step 2: Aggiungi `avg_todo_stagnation_days` in `buildAggregate()`**

Nel metodo `buildAggregate()`, aggiungi dopo la riga `$changeReqs`:

```php
$todoStagnation = array_filter(array_column($tickets, 'todo_stagnation_days'), fn ($v) => $v !== null);
```

Nel return di `buildAggregate()` (caso vuoto, intorno riga 178-185), aggiungi:

```php
'avg_todo_stagnation_days' => null,
```

Nel return del caso non vuoto (intorno riga 196-205), aggiungi:

```php
'avg_todo_stagnation_days' => count($todoStagnation) ? round(array_sum($todoStagnation) / count($todoStagnation), 1) : null,
```

- [ ] **Step 3: Aggiungi `avg_todo_stagnation_days` in `buildTeamAggregate()`**

Nel return del caso vuoto (riga 231), aggiungi `'avg_todo_stagnation_days' => null` alla lista:

```php
return ['story_count' => 0, 'avg_cycle_time_hours' => null, 'avg_reopen_count' => null, 'on_time_rate' => null, 'avg_commit_count' => null, 'avg_pr_count' => null, 'avg_change_requests' => null, 'avg_todo_stagnation_days' => null];
```

Nel return del caso non vuoto (intorno riga 236-245), aggiungi:

```php
'avg_todo_stagnation_days' => $avg('avg_todo_stagnation_days') !== null ? round($avg('avg_todo_stagnation_days'), 1) : null,
```

- [ ] **Step 4: Verifica manuale che il controller restituisca il campo**

```bash
docker exec php81_orchestrator php artisan tinker --execute="
use App\Http\Controllers\Nova\TeamPerformanceController;
use App\Services\Metrics\StoryMetricsCalculator;
\$ctrl = new TeamPerformanceController(new StoryMetricsCalculator());
echo 'Controller instanziato correttamente';
"
```

Atteso: nessun errore PHP

---

### Task 3: Colonna e KPI nel componente Vue `card.js`

**Files:**
- Modify: `nova-components/team-performance/dist/js/card.js`

**Interfaces:**
- Consumes: `ticket.todo_stagnation_days` (int|null), `aggregate.developer.avg_todo_stagnation_days` (float|null), `aggregate.team_average.avg_todo_stagnation_days` (float|null)

- [ ] **Step 1: Aggiungi intestazione colonna nella `<thead>`**

Cerca la riga contenente:

```
<th class="text-center py-3 px-3 font-semibold text-gray-600 dark:text-gray-400" style="cursor:help" title="Numero totale di review ricevute sulle PR collegate (commenti, approvazioni, richieste di modifica).">Reviews ⓘ</th>
```

Aggiungi **dopo** quella riga (prima di `</thead>`):

```
<th class="text-center py-3 px-3 font-semibold text-gray-600 dark:text-gray-400" style="cursor:help" title="Totale giorni lavorativi trascorsi in stato todo prima di essere presi in carico. Somma di tutti gli intervalli todo nella storia del ticket.">Giorni in todo ⓘ</th>
```

- [ ] **Step 2: Aggiungi cella dati per-ticket nel `<tbody>`**

Cerca la riga contenente `ticket.change_requests_count` nella sezione `<tbody>` (intorno a riga 100-105 del file). Aggiungi **dopo** quella `<td>`:

```
<td class="py-2 px-3 text-center text-gray-700 dark:text-gray-300">{{ ticket.todo_stagnation_days !== null ? ticket.todo_stagnation_days : '-' }}</td>
```

- [ ] **Step 3: Aggiungi cella KPI media team**

Cerca il blocco dei KPI della riga "Team" (quella con `aggregate.team_average.avg_change_requests`). Aggiungi **dopo** quella `<td>`:

```
<td class="py-3 px-3 text-center">{{ aggregate.team_average.avg_todo_stagnation_days !== null ? aggregate.team_average.avg_todo_stagnation_days : '-' }}</td>
```

- [ ] **Step 4: Aggiungi cella KPI developer**

Cerca il blocco dei KPI della riga "Developer" (quella con `aggregate.developer.avg_change_requests`). Aggiungi **dopo** quella `<td>`:

```
<td class="py-3 px-3 text-center" :class="deltaClass(aggregate.developer.avg_todo_stagnation_days, aggregate.team_average.avg_todo_stagnation_days, true)" style="cursor:help" title="Media giorni in todo del developer. Verde = meno giorni della media team, rosso = più giorni.">
    {{ aggregate.developer.avg_todo_stagnation_days !== null ? aggregate.developer.avg_todo_stagnation_days : '-' }}
    <span v-if="aggregate.developer.avg_todo_stagnation_days !== null && aggregate.team_average.avg_todo_stagnation_days !== null" class="text-xs ml-1">{{ delta(aggregate.developer.avg_todo_stagnation_days, aggregate.team_average.avg_todo_stagnation_days) }}</span>
</td>
```

- [ ] **Step 5: Valida la sintassi del file**

```bash
node --check nova-components/team-performance/dist/js/card.js
```

Atteso: nessun output (nessun errore sintattico)

- [ ] **Step 6: Verifica visiva nel browser**

Apri la card team performance in Nova (`/nova/dashboards/team-performance`), verifica che:
- La colonna "Giorni in todo" appaia nella tabella
- I valori numerici o `—` siano mostrati correttamente
- Il KPI "Media giorni in todo" appaia nella riga aggregato team e developer
- Il colore verde/rosso funzioni correttamente rispetto alla media team
