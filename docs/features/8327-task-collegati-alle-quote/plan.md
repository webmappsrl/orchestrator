> Ticket: oc:8327

# Task collegati alle Quote (replica feature HubSpot) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Introdurre un modello `Task` (promemoria con scadenza) collegato a `Quote`, con vista globale filtrabile per stato/scadenza e sub-panel nel dettaglio Quote.

**Architecture:** Nuovo modello Eloquent `Task` (belongsTo `Quote`, cascade delete), assegnatario sempre derivato via accessor da `quote->user` (mai persistito). Nova Resource standalone con `indexQuery` scoping automatico sull'utente loggato e `Nova\Filters\TaskDueDateFilter` per isolare Overdue/Due today/Upcoming/Completed. Sub-panel `HasMany` nel dettaglio `Quote` esistente. Nessun Kanban, nessuna Lens.

**Tech Stack:** Laravel 10, Laravel Nova, PostgreSQL, `Marshmallow\Tiptap\Tiptap` (editor WYSIWYG già in uso su `Quote`), PHPUnit (`DatabaseTransactions`, DB `orchestrator_test`).

**Spec:** `docs/features/8327-task-collegati-alle-quote/overview.md`

## Global Constraints

- Nessun campo `type`/`priority`/`queue`/`reminder` sul `Task` (fuori scope esplicito)
- Assegnatario mai persistito su `Task` — sempre derivato da `quote->user` via accessor
- Creazione `Task` bloccata se `quote.user_id` è null (messaggio esplicito), nessun fallback "Non assegnato"
- `onDelete('cascade')` su `quote_id`: eliminare una Quote elimina i suoi Task
- Confronto "oggi" per Overdue/Due today/Upcoming sempre su timezone di sistema (`Europe/Rome`), nessuna gestione per-utente
- Vista globale = index Nova standard + `Filter` (mai Lens, mai Kanban — non toccare `nova-components/kanban-card/`)
- Nessuna notifica/promemoria, nessuna sync calendario esterna, nessuna vista cross-utente (si usa l'impersonation esistente)
- Locale default `it`, traduzioni richieste anche in `lang/en.json`
- Tutti i commit usano la convention `feat(oc:8327): ...` — sono istruzioni testuali per l'utente, **non vanno eseguiti automaticamente** durante l'esecuzione del piano

---

## File Structure

- `database/migrations/2026_08_19_113137_create_tasks_table.php` — nuova tabella `tasks`
- `app/Models/Task.php` — nuovo modello: relazioni, scope di query (overdue/dueToday/upcoming/forUser), hook di validazione e gestione `completed_at`
- `app/Models/Quote.php` — modifica: aggiunta relazione `tasks()`
- `app/Nova/Task.php` — nuova Nova Resource
- `app/Nova/Filters/TaskDueDateFilter.php` — nuovo filtro
- `app/Nova/Quote.php` — modifica: aggiunta campo `HasMany` per il sub-panel Task
- `app/Providers/NovaServiceProvider.php` — modifica: registrazione voce di menu `Task` nella sezione CRM
- `lang/it.json`, `lang/en.json` — modifica: nuove label
- `tests/Feature/TaskTest.php` — nuovo file di test

---

### Task 1: Migration + modello `Task` con relazioni e cascade delete

**Files:**
- Create: `database/migrations/2026_08_19_113137_create_tasks_table.php`
- Create: `app/Models/Task.php`
- Modify: `app/Models/Quote.php`
- Test: `tests/Feature/TaskTest.php`

**Interfaces:**
- Produces: `Task::quote(): BelongsTo`, `Quote::tasks(): HasMany`, colonne tabella `tasks` (`id`, `quote_id`, `title`, `notes`, `due_date`, `status`, `completed_at`, `timestamps`)

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Quote;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class TaskTest extends TestCase
{
    use DatabaseTransactions;

    private function makeQuoteWithOwner(): Quote
    {
        $customer = Customer::factory()->create();
        $user = User::factory()->create();

        return Quote::create([
            'title' => 'Quote di test',
            'customer_id' => $customer->id,
            'user_id' => $user->id,
        ]);
    }

    public function test_task_belongs_to_quote()
    {
        $quote = $this->makeQuoteWithOwner();

        $task = Task::create([
            'quote_id' => $quote->id,
            'title' => 'Richiamare il cliente',
            'due_date' => now()->addDay(),
        ]);

        $this->assertInstanceOf(Quote::class, $task->quote);
        $this->assertTrue($quote->tasks->contains($task));
    }

    public function test_deleting_quote_cascades_to_tasks()
    {
        $quote = $this->makeQuoteWithOwner();
        $task = Task::create([
            'quote_id' => $quote->id,
            'title' => 'Task da cancellare in cascata',
            'due_date' => now()->addDay(),
        ]);

        $quote->delete();

        $this->assertDatabaseMissing('tasks', ['id' => $task->id]);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run (dentro il container Docker):
```bash
docker exec php81_orchestrator php artisan test --filter=TaskTest
```
Expected: FAIL — `Class "App\Models\Task" not found` (il modello e la migration non esistono ancora).

- [ ] **Step 3: Write the migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quote_id')->constrained()->onDelete('cascade');
            $table->string('title');
            $table->text('notes')->nullable();
            $table->dateTime('due_date');
            $table->string('status')->default('todo');
            $table->dateTime('completed_at')->nullable();
            $table->timestamps();

            $table->index(['due_date', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};
```

- [ ] **Step 4: Write the `Task` model**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Task extends Model
{
    use HasFactory;

    public const STATUS_TODO = 'todo';
    public const STATUS_COMPLETED = 'completed';

    protected $fillable = [
        'quote_id',
        'title',
        'notes',
        'due_date',
        'status',
        'completed_at',
    ];

    protected $casts = [
        'due_date' => 'datetime',
        'completed_at' => 'datetime',
    ];

    protected $attributes = [
        'status' => self::STATUS_TODO,
    ];

    public function quote(): BelongsTo
    {
        return $this->belongsTo(Quote::class);
    }
}
```

- [ ] **Step 5: Add the `tasks()` relation on `Quote`**

In `app/Models/Quote.php`, aggiungi l'import `use Illuminate\Database\Eloquent\Relations\HasMany;` in cima al file e il metodo subito dopo `user()`:

```php
    public function tasks()
    {
        return $this->hasMany(Task::class);
    }
```

- [ ] **Step 6: Run migration on the test database**

```bash
docker exec php81_orchestrator bash -c "DB_DATABASE=orchestrator_test php artisan migrate"
```
Expected: `Migrating: ..._create_tasks_table` → `Migrated:  ..._create_tasks_table`

- [ ] **Step 7: Run test to verify it passes**

```bash
docker exec php81_orchestrator php artisan test --filter=TaskTest
```
Expected: PASS (2 test)

- [ ] **Step 8: Commit**

```bash
git add database/migrations/2026_08_19_113137_create_tasks_table.php app/Models/Task.php app/Models/Quote.php tests/Feature/TaskTest.php
git commit -m "feat(oc:8327): add Task model with cascade delete on Quote"
```

---

### Task 2: Validazione `user_id` mancante + gestione `completed_at`

**Files:**
- Modify: `app/Models/Task.php`
- Test: `tests/Feature/TaskTest.php`

**Interfaces:**
- Consumes: `Task` model da Task 1, `Quote::user()` (già esistente in `app/Models/Quote.php:75-78`)
- Produces: `Task` lancia `\Illuminate\Validation\ValidationException` alla creazione se `quote->user_id` è null; `Task::markCompleted()`/`markTodo()` non introdotti come API pubblica — la gestione di `completed_at` avviene internamente nel booted `saving` hook quando `status` cambia

- [ ] **Step 1: Write the failing tests**

Aggiungi a `tests/Feature/TaskTest.php`:

```php
    public function test_cannot_create_task_on_quote_without_owner()
    {
        $customer = Customer::factory()->create();
        $quote = Quote::create([
            'title' => 'Quote senza owner',
            'customer_id' => $customer->id,
        ]);

        $this->expectException(\Illuminate\Validation\ValidationException::class);

        Task::create([
            'quote_id' => $quote->id,
            'title' => 'Task che non deve essere creato',
            'due_date' => now()->addDay(),
        ]);
    }

    public function test_completing_task_sets_completed_at()
    {
        $quote = $this->makeQuoteWithOwner();
        $task = Task::create([
            'quote_id' => $quote->id,
            'title' => 'Da completare',
            'due_date' => now()->addDay(),
        ]);

        $task->status = Task::STATUS_COMPLETED;
        $task->save();

        $this->assertNotNull($task->fresh()->completed_at);
    }

    public function test_reopening_task_clears_completed_at()
    {
        $quote = $this->makeQuoteWithOwner();
        $task = Task::create([
            'quote_id' => $quote->id,
            'title' => 'Da riaprire',
            'due_date' => now()->addDay(),
            'status' => Task::STATUS_COMPLETED,
            'completed_at' => now(),
        ]);

        $task->status = Task::STATUS_TODO;
        $task->save();

        $this->assertNull($task->fresh()->completed_at);
    }
```

- [ ] **Step 2: Run tests to verify they fail**

```bash
docker exec php81_orchestrator php artisan test --filter=TaskTest
```
Expected: FAIL sui 3 nuovi test (nessuna validazione, nessun hook `completed_at`).

- [ ] **Step 3: Implement the `booted()` hooks on `Task`**

In `app/Models/Task.php`, aggiungi in cima il metodo `protected static function booted()`:

```php
    protected static function booted()
    {
        static::creating(function (Task $task) {
            $quote = $task->quote()->first() ?? Quote::find($task->quote_id);

            if ($quote === null || $quote->user_id === null) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'quote_id' => [__('Assegna prima un utente alla Quote prima di creare un Task.')],
                ]);
            }
        });

        static::saving(function (Task $task) {
            if (!$task->isDirty('status')) {
                return;
            }

            if ($task->status === self::STATUS_COMPLETED) {
                $task->completed_at = $task->completed_at ?? now();
            } else {
                $task->completed_at = null;
            }
        });
    }
```

- [ ] **Step 4: Run tests to verify they pass**

```bash
docker exec php81_orchestrator php artisan test --filter=TaskTest
```
Expected: PASS (5 test totali)

- [ ] **Step 5: Commit**

```bash
git add app/Models/Task.php tests/Feature/TaskTest.php
git commit -m "feat(oc:8327): block Task creation without Quote owner, manage completed_at"
```

---

### Task 3: Accessor assegnatario + query scope per scadenza

**Files:**
- Modify: `app/Models/Task.php`
- Test: `tests/Feature/TaskTest.php`

**Interfaces:**
- Consumes: `Task::quote()` (Task 1), `Quote::user()` (esistente)
- Produces: `Task::getAssigneeAttribute(): ?User` (accessor `$task->assignee`), scope `Task::scopeOverdue($query)`, `Task::scopeDueToday($query)`, `Task::scopeUpcoming($query)`, `Task::scopeCompletedStatus($query)`, `Task::scopeForUser($query, User $user)` — usati da Nova Resource (Task 4) e Filter (Task 5)

- [ ] **Step 1: Write the failing tests**

Aggiungi a `tests/Feature/TaskTest.php`:

```php
    public function test_assignee_accessor_resolves_via_quote_user()
    {
        $quote = $this->makeQuoteWithOwner();
        $task = Task::create([
            'quote_id' => $quote->id,
            'title' => 'Task con assegnatario',
            'due_date' => now()->addDay(),
        ]);

        $this->assertTrue($quote->user->is($task->assignee));
    }

    public function test_overdue_scope_returns_only_past_due_todo_tasks()
    {
        $quote = $this->makeQuoteWithOwner();
        $overdue = Task::create([
            'quote_id' => $quote->id,
            'title' => 'Scaduto',
            'due_date' => now()->subDay(),
        ]);
        Task::create([
            'quote_id' => $quote->id,
            'title' => 'Futuro',
            'due_date' => now()->addDay(),
        ]);

        $results = Task::overdue()->get();

        $this->assertCount(1, $results);
        $this->assertTrue($results->first()->is($overdue));
    }

    public function test_for_user_scope_filters_by_quote_owner()
    {
        $quoteA = $this->makeQuoteWithOwner();
        $quoteB = $this->makeQuoteWithOwner();

        $taskA = Task::create([
            'quote_id' => $quoteA->id,
            'title' => 'Task A',
            'due_date' => now()->addDay(),
        ]);
        Task::create([
            'quote_id' => $quoteB->id,
            'title' => 'Task B',
            'due_date' => now()->addDay(),
        ]);

        $results = Task::forUser($quoteA->user)->get();

        $this->assertCount(1, $results);
        $this->assertTrue($results->first()->is($taskA));
    }
```

- [ ] **Step 2: Run tests to verify they fail**

```bash
docker exec php81_orchestrator php artisan test --filter=TaskTest
```
Expected: FAIL — accessor/scope non esistono.

- [ ] **Step 3: Implement accessor and scopes**

Aggiungi in `app/Models/Task.php` (dopo il metodo `quote()`):

```php
    public function getAssigneeAttribute(): ?User
    {
        return $this->quote?->user;
    }

    public function scopeForUser($query, User $user)
    {
        return $query->whereHas('quote', function ($q) use ($user) {
            $q->where('user_id', $user->id);
        });
    }

    public function scopeOverdue($query)
    {
        return $query->where('status', self::STATUS_TODO)
            ->whereDate('due_date', '<', now()->toDateString());
    }

    public function scopeDueToday($query)
    {
        return $query->where('status', self::STATUS_TODO)
            ->whereDate('due_date', now()->toDateString());
    }

    public function scopeUpcoming($query)
    {
        return $query->where('status', self::STATUS_TODO)
            ->whereDate('due_date', '>', now()->toDateString());
    }

    public function scopeCompletedStatus($query)
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }
```

Aggiungi anche l'import in cima al file: `use App\Models\User;`.

- [ ] **Step 4: Run tests to verify they pass**

```bash
docker exec php81_orchestrator php artisan test --filter=TaskTest
```
Expected: PASS (8 test totali)

- [ ] **Step 5: Commit**

```bash
git add app/Models/Task.php tests/Feature/TaskTest.php
git commit -m "feat(oc:8327): add assignee accessor and due-date query scopes to Task"
```

---

### Task 4: Nova Filter `TaskDueDateFilter`

**Files:**
- Create: `app/Nova/Filters/TaskDueDateFilter.php`
- Test: `tests/Feature/TaskTest.php`

**Interfaces:**
- Consumes: scope `Task::scopeOverdue`/`scopeDueToday`/`scopeUpcoming`/`scopeCompletedStatus` (Task 3)
- Produces: `TaskDueDateFilter` usato da `app/Nova/Task.php` (Task 5)

- [ ] **Step 1: Write the failing test**

Aggiungi a `tests/Feature/TaskTest.php`:

```php
    public function test_due_date_filter_applies_overdue_scope()
    {
        $quote = $this->makeQuoteWithOwner();
        $overdue = Task::create([
            'quote_id' => $quote->id,
            'title' => 'Scaduto',
            'due_date' => now()->subDay(),
        ]);
        Task::create([
            'quote_id' => $quote->id,
            'title' => 'Futuro',
            'due_date' => now()->addDay(),
        ]);

        $filter = new \App\Nova\Filters\TaskDueDateFilter();
        $request = \Laravel\Nova\Http\Requests\NovaRequest::createFrom(request());

        $results = $filter->apply($request, Task::query(), 'overdue')->get();

        $this->assertCount(1, $results);
        $this->assertTrue($results->first()->is($overdue));
    }
```

- [ ] **Step 2: Run test to verify it fails**

```bash
docker exec php81_orchestrator php artisan test --filter=TaskTest
```
Expected: FAIL — `Class "App\Nova\Filters\TaskDueDateFilter" not found`.

- [ ] **Step 3: Implement the filter**

```php
<?php

namespace App\Nova\Filters;

use App\Models\Task;
use Laravel\Nova\Filters\Filter;
use Laravel\Nova\Http\Requests\NovaRequest;

class TaskDueDateFilter extends Filter
{
    public $component = 'select-filter';

    public function apply(NovaRequest $request, $query, $value)
    {
        return match ($value) {
            'overdue' => $query->overdue(),
            'due_today' => $query->dueToday(),
            'upcoming' => $query->upcoming(),
            'completed' => $query->completedStatus(),
            default => $query,
        };
    }

    public function options(NovaRequest $request)
    {
        return [
            __('Overdue') => 'overdue',
            __('Due today') => 'due_today',
            __('Upcoming') => 'upcoming',
            __('Completed') => 'completed',
        ];
    }

    public function name()
    {
        return __('Due date');
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

```bash
docker exec php81_orchestrator php artisan test --filter=TaskTest
```
Expected: PASS (9 test totali)

- [ ] **Step 5: Commit**

```bash
git add app/Nova/Filters/TaskDueDateFilter.php tests/Feature/TaskTest.php
git commit -m "feat(oc:8327): add TaskDueDateFilter Nova filter"
```

---

### Task 5: Nova Resource `Task` con badge urgenza, index scoping, registrazione menu

**Files:**
- Create: `app/Nova/Task.php`
- Modify: `app/Providers/NovaServiceProvider.php`
- Modify: `lang/it.json`, `lang/en.json`

**Interfaces:**
- Consumes: `Task` model (Task 1-3), `TaskDueDateFilter` (Task 4)
- Produces: Nova Resource `Task` registrata nel menu CRM, index ordinato per `due_date` asc, scoping automatico sull'utente loggato

- [ ] **Step 1: Add translation keys**

In `lang/it.json` aggiungi (mantieni l'ordine alfabetico esistente delle chiavi, se presente, altrimenti aggiungi in coda prima della chiusura `}`):

```json
  "Task": "Task",
  "Tasks": "Task",
  "Due date": "Scadenza",
  "Due today": "In scadenza oggi",
  "Upcoming": "Imminente",
  "Overdue": "Scaduto",
  "Completed": "Completato",
  "Assignee": "Assegnatario",
  "Overdue by :days days": "Scaduto da :days giorni",
  "Due in :days days": "Tra :days giorni",
  "Completed late": "Completato in ritardo"
```

In `lang/en.json` aggiungi le stesse chiavi con valore identico alla chiave (lingua di default del file sorgente):

```json
  "Task": "Task",
  "Tasks": "Tasks",
  "Due date": "Due date",
  "Due today": "Due today",
  "Upcoming": "Upcoming",
  "Overdue": "Overdue",
  "Completed": "Completed",
  "Assignee": "Assignee",
  "Overdue by :days days": "Overdue by :days days",
  "Due in :days days": "Due in :days days",
  "Completed late": "Completed late"
```

- [ ] **Step 2: Create the Nova Resource**

```php
<?php

namespace App\Nova;

use App\Models\Task as TaskModel;
use App\Nova\Filters\TaskDueDateFilter;
use Illuminate\Http\Request;
use Laravel\Nova\Fields\Badge;
use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Fields\Boolean;
use Laravel\Nova\Fields\DateTime;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\NovaRequest;
use Marshmallow\Tiptap\Tiptap;

class Task extends Resource
{
    public static $model = TaskModel::class;

    public static $title = 'title';

    public static $search = [
        'id',
        'title',
    ];

    public function fields(NovaRequest $request)
    {
        return [
            ID::make()->sortable(),

            Text::make(__('Task'), 'title')
                ->sortable()
                ->rules('required', 'max:255'),

            Text::make(__('Assignee'), 'assignee')
                ->displayUsing(fn () => $this->assignee?->name ?? '—')
                ->exceptOnForms(),

            BelongsTo::make(__('Quote'), 'quote', Quote::class)
                ->searchable()
                ->rules('required'),

            DateTime::make(__('Due date'), 'due_date')
                ->sortable()
                ->rules('required'),

            Badge::make(__('Status'), function () {
                return $this->urgencyBadgeLabel();
            })->map([
                'overdue' => 'danger',
                'due_today' => 'warning',
                'upcoming' => 'success',
                'completed' => 'info',
                'completed_late' => 'warning',
            ])->onlyOnIndex(),

            Boolean::make(__('Completed'), 'completed')
                ->fillUsing(function ($request, $model, $attribute, $requestAttribute) {
                    $model->status = $request->boolean($requestAttribute)
                        ? TaskModel::STATUS_COMPLETED
                        : TaskModel::STATUS_TODO;
                })
                ->resolveUsing(fn () => $this->status === TaskModel::STATUS_COMPLETED),

            Tiptap::make(__('Notes'), 'notes')
                ->hideFromIndex()
                ->buttons(['bold', 'italic', 'bulletList', 'orderedList', 'link']),
        ];
    }

    public function urgencyBadgeKey(): string
    {
        if ($this->status === TaskModel::STATUS_COMPLETED) {
            return $this->completed_at && $this->completed_at->gt($this->due_date)
                ? 'completed_late'
                : 'completed';
        }

        if ($this->due_date->isPast()) {
            return 'overdue';
        }

        if ($this->due_date->isToday()) {
            return 'due_today';
        }

        return 'upcoming';
    }

    public function urgencyBadgeLabel(): string
    {
        return match ($this->urgencyBadgeKey()) {
            'overdue' => __('Overdue by :days days', ['days' => (int) now()->diffInDays($this->due_date)]),
            'due_today' => __('Due today'),
            'upcoming' => __('Due in :days days', ['days' => (int) now()->diffInDays($this->due_date)]),
            'completed_late' => __('Completed late'),
            default => __('Completed'),
        };
    }

    public function filters(NovaRequest $request)
    {
        return [
            new TaskDueDateFilter(),
        ];
    }

    public static function indexQuery(NovaRequest $request, $query)
    {
        $user = $request->user();

        if ($user === null) {
            return $query->whereRaw('1 = 0');
        }

        return $query->forUser($user)->orderBy('due_date', 'asc');
    }
}
```

- [ ] **Step 3: Register the resource in the CRM menu**

In `app/Providers/NovaServiceProvider.php`, aggiungi l'import `use App\Nova\Task;` (verifica che non collida con altri use — se necessario usa `use App\Nova\Task as TaskResource;` e aggiorna il riferimento sotto), poi nella sezione `MenuSection::make('CRM', [...])` (riga ~129) aggiungi subito dopo `MenuItem::resource(Quote::class),`:

```php
                    MenuItem::resource(Task::class),
```

- [ ] **Step 4: Manual verification (no automated Nova HTTP test — UI registration)**

```bash
docker exec php81_orchestrator php artisan config:clear
```

Poi apri il browser su `http://localhost:8099/nova/resources/tasks` (porta da `DOCKER_SERVE_PORT` del progetto), verifica: la voce "Task" compare nel menu CRM, l'index è ordinato per scadenza, il filtro "Due date" mostra le 4 opzioni.

- [ ] **Step 5: Commit**

```bash
git add app/Nova/Task.php app/Providers/NovaServiceProvider.php lang/it.json lang/en.json
git commit -m "feat(oc:8327): add Task Nova Resource with urgency badge and menu entry"
```

---

### Task 6: Sub-panel `Task` nel dettaglio `Quote`

**Files:**
- Modify: `app/Nova/Quote.php`

**Interfaces:**
- Consumes: `App\Nova\Task` (Task 5), relazione `Quote::tasks()` (Task 1)

- [ ] **Step 1: Add the `HasMany` field to the Quote resource**

In `app/Nova/Quote.php`, aggiungi l'import `use Laravel\Nova\Fields\HasMany;` in cima al file, poi nel metodo `fields()` aggiungi, subito dopo il campo `BelongsTo::make(__('Owner'), 'user', ...)`:

```php
            HasMany::make(__('Tasks'), 'tasks', \App\Nova\Task::class),
```

- [ ] **Step 2: Manual verification**

Apri il dettaglio di una Quote con `user_id` valorizzato nel browser Nova, verifica che compaia il pannello "Tasks" con il pulsante di creazione, e che la creazione di un Task da lì colleghi correttamente `quote_id`.

Verifica anche il caso bloccante: apri una Quote **senza** `user_id`, prova a creare un Task dal sub-panel, conferma che compaia l'errore di validazione "Assegna prima un utente alla Quote prima di creare un Task."

- [ ] **Step 3: Commit**

```bash
git add app/Nova/Quote.php
git commit -m "feat(oc:8327): add Task sub-panel to Quote detail view"
```

---

## Self-Review (eseguita in fase di stesura del piano)

**1. Spec coverage:**
- Modello Task con tutti i campi richiesti → Task 1
- Validazione `user_id` mancante → Task 2
- Gestione `completed_at` (set/reset) → Task 2
- Assegnatario derivato → Task 3
- Scope temporali (overdue/dueToday/upcoming) → Task 3
- Filtro Nova → Task 4
- Nova Resource, badge urgenza, scoping utente, colonne index → Task 5
- Traduzioni IT/EN → Task 5
- Sub-panel in Quote → Task 6
- Cascade delete → Task 1
- Editor Tiptap su `notes` → Task 5

**2. Placeholder scan:** nessun placeholder — ogni step ha codice completo.

**3. Type consistency:** `Task::STATUS_TODO`/`STATUS_COMPLETED` usati in modo coerente tra Task 1 (definizione), Task 2 (hook), Task 5 (Nova Boolean field). Scope `forUser`, `overdue`, `dueToday`, `upcoming`, `completedStatus` definiti in Task 3, consumati identicamente in Task 4 (filtro) e Task 5 (`indexQuery`).

Nessun gap rilevato.
