> Ticket: oc:8403

# API Task per follow-up e automazioni — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.
>
> **⚠️ Nessun commit o branch automatico durante l'esecuzione.** I comandi `git commit` in ogni task sono istruzioni testuali per lo sviluppatore, non azioni da eseguire autonomamente — vale sia in esecuzione inline sia subagent-driven. La fase di commit reale è gestita separatamente dal workflow `wm-plan` (Fase: execution → review-gate), dopo approvazione esplicita del developer.

**Goal:** Esporre la risorsa `Task` (già esistente, gestita solo via Nova) come API REST sotto `auth:sanctum`, per abilitare un flusso Cowork end-to-end: leggere task + quote collegata, generare bozze email, segnare come completato e creare follow-up.

**Architecture:** Nuovo `TaskController` (`app/Http/Controllers/Api/`) con 4 endpoint (`index`/`show`/`store`/`update`), mirror diretto del pattern già usato da `QuoteController`. Nuova `TaskPolicy` con autorizzazione differenziata per campo sul `PATCH` (`status` solo creator, `notes` qualsiasi ruolo abilitato) — pattern non standard nel progetto, isolato in un metodo dedicato `updateStatus()` invocato condizionalmente dal controller, così il comportamento resta esplicito e testabile senza intrecciare i due controlli in un solo `update()`. Nuovo metodo `Task::appendNote()` mirror di `Story::addDevNote()` (prepend, non accoda).

**Tech Stack:** Laravel 10, Sanctum, Eloquent Policy, `dedoc/scramble` per la documentazione API automatica, PHPUnit (`DatabaseTransactions`, DB `orchestrator_test`).

**Spec:** `docs/features/8403-api-task-per-follow-up-e-automazioni/overview.md`

## Global Constraints

- Tutte le route sotto il gruppo `Route::middleware('auth:sanctum')` già esistente in `routes/api.php`.
- Nessun `DELETE` in questo ciclo.
- Nessuna paginazione su `GET /api/tasks`.
- `creator_id` non è mai un campo accettato in input (`POST`/`PATCH`) — resta gestito solo dall'hook `Task::booted()` esistente. Applicato per costruzione: il controller legge solo i campi validati da `TaskApiRequest::rules()`, mai `$request->all()`.
- Nessuna migrazione nuova: lo schema `tasks` esiste già (oc:8327).
- Documentazione automatica via `dedoc/scramble`: docblock `@response` con shape inline completa (no alias `@phpstan-type`, non supportati — vedi CLAUDE.md), `#[QueryParameter]` per il parametro `sort` su `index()`.
- `has_phpstan_ci: false` per questo repo — nessuno step PHPStan previsto nel piano.

---

## File Structure

- `app/Policies/TaskPolicy.php` (nuovo) — autorizzazione ruolo-only (`before()`) + `create()` con blocco quote chiuse + `update()`/`updateStatus()` per l'autorizzazione differenziata per campo del PATCH.
- `app/Http/Requests/Api/TaskApiRequest.php` (nuovo) — whitelist di validazione, regole diverse per `POST` (`quote_id`/`title`/`due_date`/`notes`) e `PATCH` (`status`/`notes`).
- `app/Http/Controllers/Api/TaskController.php` (nuovo) — 4 metodi pubblici + `formatTask()` privato condiviso.
- `app/Models/Task.php` (modifica) — nuovo metodo pubblico `appendNote()`.
- `routes/api.php` (modifica) — 4 nuove route nel gruppo `auth:sanctum` esistente.
- `tests/Feature/Api/TaskApiTest.php` (nuovo) — test end-to-end sugli endpoint.
- `tests/Feature/Api/TaskPolicyTest.php` (nuovo) — test mirati sulla Policy (mirror di `QuotePolicyTest.php`).

---

## Task 1: `TaskPolicy` — ruolo base + blocco creazione su Quote chiusa

**Files:**
- Create: `app/Policies/TaskPolicy.php`
- Test: `tests/Feature/Api/TaskPolicyTest.php`

**Interfaces:**
- Consumes: `App\Models\User::hasRole(UserRole $role): bool` (già esistente), `App\Enums\UserRole` (`Admin`, `Manager`, `Developer`, `Editor`, `Customer`), `App\Enums\QuoteStatus` (`Closed_Won->value === 'closed won'`, `Closed_Lost->value === 'closed lost'`), `App\Models\Quote`.
- Produces: `TaskPolicy::before(User $user)`, `TaskPolicy::viewAny(User $user): bool`, `TaskPolicy::view(User $user, Task $task): bool`, `TaskPolicy::create(User $user, Quote $quote): bool`. Il controller (Task 3) chiamerà `$this->authorize('create', [Task::class, $quote])` — Laravel risolve la policy dal primo elemento dell'array e passa `$quote` come secondo argomento a `create()`.

- [ ] **Step 1: Scrivi il test che fallisce — non-Admin/Manager/Developer non passa `before()`**

```php
<?php

namespace Tests\Feature\Api;

use App\Enums\QuoteStatus;
use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\Quote;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class TaskPolicyTest extends TestCase
{
    use DatabaseTransactions;

    private function makeQuote(array $overrides = []): Quote
    {
        $customer = Customer::factory()->create();

        return Quote::create(array_merge([
            'title' => 'Quote di test',
            'customer_id' => $customer->id,
        ], $overrides));
    }

    private function makeTask(Quote $quote, array $overrides = []): Task
    {
        return Task::create(array_merge([
            'quote_id' => $quote->id,
            'title' => 'Task di test',
            'due_date' => now()->addDay(),
        ], $overrides));
    }

    /** @test */
    public function customer_non_puo_vedere_nessun_task(): void
    {
        $user = User::factory()->create(['roles' => [UserRole::Customer]]);
        $quote = $this->makeQuote();
        $task = $this->makeTask($quote);

        $this->assertFalse($user->can('viewAny', Task::class));
        $this->assertFalse($user->can('view', $task));
    }
}
```

- [ ] **Step 2: Esegui il test e verifica che fallisca**

Comando: `docker exec php81_orchestrator php artisan test --filter=TaskPolicyTest`
Atteso: FAIL — `Class "App\Policies\TaskPolicy" not found` o errore di risoluzione policy (`Task` non ha ancora una policy registrata via naming convention `App\Models\Task` → `App\Policies\TaskPolicy`).

- [ ] **Step 3: Implementa `TaskPolicy` (solo `before`/`viewAny`/`view`, `create` minimale)**

```php
<?php

namespace App\Policies;

use App\Enums\QuoteStatus;
use App\Enums\UserRole;
use App\Models\Quote;
use App\Models\Task;
use App\Models\User;

class TaskPolicy
{
    /**
     * Stesso perimetro ruoli di QuotePolicy: solo Admin, Manager, Developer
     * possono accedere alle API Task, indipendentemente dall'ability.
     */
    public function before(User $user)
    {
        if (!($user->hasRole(UserRole::Admin) || $user->hasRole(UserRole::Manager) || $user->hasRole(UserRole::Developer))) {
            return false;
        }
    }

    public function viewAny(User $user)
    {
        return true;
    }

    /**
     * Ruolo-only, nessuno scoping ownership: mirror esatto di
     * QuotePolicy::view(). Il dettaglio di un Task è visibile a chiunque
     * abbia un ruolo abilitato, anche se non è owner della Quote né
     * creatore del Task — la lista (GET /api/tasks) resta invece filtrata
     * via Task::scopeForUser() nel controller.
     */
    public function view(User $user, Task $task)
    {
        return true;
    }

    /**
     * Nessun vincolo di ownership sulla Quote: chiunque abbia un ruolo
     * abilitato (già garantito da before()) può creare un Task su
     * qualsiasi Quote, mirror della regola Nova esistente (oc:8327).
     * Unica eccezione, introdotta in Fase: challenge di questo ticket:
     * la Quote non deve essere closed_won/closed_lost, stesso vincolo già
     * applicato da QuotePolicy::update() sulla Quote stessa — evita
     * follow-up su trattative già chiuse.
     */
    public function create(User $user, Quote $quote)
    {
        return $quote->status !== QuoteStatus::Closed_Won->value
            && $quote->status !== QuoteStatus::Closed_Lost->value;
    }
}
```

- [ ] **Step 4: Esegui di nuovo il test e verifica che passi**

Comando: `docker exec php81_orchestrator php artisan test --filter=TaskPolicyTest`
Atteso: PASS

- [ ] **Step 5: Aggiungi test per `create()` su Quote chiusa e Quote aperta**

Aggiungi a `tests/Feature/Api/TaskPolicyTest.php`:

```php
    /** @test */
    public function admin_non_puo_creare_task_su_quote_chiusa_vinta(): void
    {
        $admin = User::factory()->create(['roles' => [UserRole::Admin]]);
        $quote = $this->makeQuote(['status' => QuoteStatus::Closed_Won->value]);

        $this->assertFalse($admin->can('create', [Task::class, $quote]));
    }

    /** @test */
    public function admin_non_puo_creare_task_su_quote_chiusa_persa(): void
    {
        $admin = User::factory()->create(['roles' => [UserRole::Admin]]);
        $quote = $this->makeQuote(['status' => QuoteStatus::Closed_Lost->value]);

        $this->assertFalse($admin->can('create', [Task::class, $quote]));
    }

    /** @test */
    public function admin_puo_creare_task_su_quote_aperta(): void
    {
        $admin = User::factory()->create(['roles' => [UserRole::Admin]]);
        $quote = $this->makeQuote(['status' => QuoteStatus::New->value]);

        $this->assertTrue($admin->can('create', [Task::class, $quote]));
    }
```

- [ ] **Step 6: Esegui i test e verifica che passino tutti**

Comando: `docker exec php81_orchestrator php artisan test --filter=TaskPolicyTest`
Atteso: PASS (4 test)

- [ ] **Step 7: Commit**

```bash
git add app/Policies/TaskPolicy.php tests/Feature/Api/TaskPolicyTest.php
git commit -m "feat(oc:8403): add TaskPolicy with role gate and closed-quote create block"
```

---

## Task 2: `TaskPolicy::update()` / `updateStatus()` — autorizzazione differenziata per campo

**Files:**
- Modify: `app/Policies/TaskPolicy.php`
- Modify: `tests/Feature/Api/TaskPolicyTest.php`

**Interfaces:**
- Consumes: `Task::$creator_id` (già esistente).
- Produces: `TaskPolicy::update(User $user, Task $task): bool` (baseline ruolo-only, sempre `true` — già garantito da `before()`), `TaskPolicy::updateStatus(User $user, Task $task): bool` (solo creator). Il controller (Task 4) chiamerà `update` sempre, e `updateStatus` **solo se** il payload contiene la chiave `status`, PRIMA di applicare qualsiasi modifica — questo è ciò che realizza il comportamento "tutto o niente" del PATCH misto.

- [ ] **Step 1: Scrivi i test che falliscono**

Aggiungi a `tests/Feature/Api/TaskPolicyTest.php`:

```php
    /** @test */
    public function creator_puo_aggiornare_status_del_proprio_task(): void
    {
        $creator = User::factory()->create(['roles' => [UserRole::Developer]]);
        $quote = $this->makeQuote();
        $task = $this->makeTask($quote, ['creator_id' => $creator->id]);

        $this->assertTrue($creator->can('updateStatus', $task));
    }

    /** @test */
    public function non_creator_non_puo_aggiornare_status(): void
    {
        $creator = User::factory()->create(['roles' => [UserRole::Developer]]);
        $altro = User::factory()->create(['roles' => [UserRole::Admin]]);
        $quote = $this->makeQuote();
        $task = $this->makeTask($quote, ['creator_id' => $creator->id]);

        $this->assertFalse($altro->can('updateStatus', $task));
    }

    /** @test */
    public function nessuno_puo_aggiornare_status_di_un_task_senza_creator(): void
    {
        $admin = User::factory()->create(['roles' => [UserRole::Admin]]);
        $quote = $this->makeQuote();
        $task = $this->makeTask($quote, ['creator_id' => null]);

        $this->assertFalse($admin->can('updateStatus', $task));
    }

    /** @test */
    public function qualsiasi_ruolo_abilitato_puo_aggiornare_notes(): void
    {
        $creator = User::factory()->create(['roles' => [UserRole::Developer]]);
        $altro = User::factory()->create(['roles' => [UserRole::Manager]]);
        $quote = $this->makeQuote();
        $task = $this->makeTask($quote, ['creator_id' => $creator->id]);

        $this->assertTrue($altro->can('update', $task));
    }
```

- [ ] **Step 2: Esegui il test e verifica che fallisca**

Comando: `docker exec php81_orchestrator php artisan test --filter=TaskPolicyTest`
Atteso: FAIL — `updateStatus`/`update` non esistono ancora sulla Policy (Laravel restituisce `false` di default per ability sconosciute, quindi `assertTrue` sui primi due nuovi test fallisce).

- [ ] **Step 3: Implementa `update()`/`updateStatus()`**

Aggiungi a `app/Policies/TaskPolicy.php` (dopo `create()`):

```php
    /**
     * Autorizzazione "base" per il PATCH: copre il campo `notes` (chiunque
     * abbia un ruolo abilitato può aggiungere una nota, mirror di
     * Story::addDevNote() dove qualunque utente autorizzato può annotare).
     * Il campo `status` NON è coperto qui: richiede il check aggiuntivo
     * updateStatus(), invocato esplicitamente da TaskController::update()
     * SOLO quando il payload contiene la chiave `status`, PRIMA di
     * applicare qualsiasi modifica — questo realizza il comportamento
     * "tutto o niente" quando un payload misto {status, notes} arriva da
     * un utente che non è il creator: la richiesta fallisce con 403 prima
     * che `notes` venga persistito. Questa autorizzazione differenziata
     * per campo diverge intenzionalmente dal pattern "un verdetto per
     * endpoint" usato da QuotePolicy — decisione presa in Fase: challenge
     * di oc:8403.
     */
    public function update(User $user, Task $task)
    {
        return true;
    }

    /**
     * Solo il creatore del Task può cambiarne lo status (mirror esatto di
     * App\Nova\Actions\ToggleTaskCompleted::authorizedToRun()). Un Task
     * con creator_id nullo (utente creatore eliminato, nullOnDelete) non è
     * completabile/riapribile da nessuno — limite ereditato dal
     * comportamento Nova esistente, non introdotto da questa API.
     */
    public function updateStatus(User $user, Task $task)
    {
        return $task->creator_id !== null && $task->creator_id === $user->id;
    }
```

- [ ] **Step 4: Esegui i test e verifica che passino tutti**

Comando: `docker exec php81_orchestrator php artisan test --filter=TaskPolicyTest`
Atteso: PASS (8 test)

- [ ] **Step 5: Commit**

```bash
git add app/Policies/TaskPolicy.php tests/Feature/Api/TaskPolicyTest.php
git commit -m "feat(oc:8403): add per-field PATCH authorization to TaskPolicy"
```

---

## Task 3: `Task::appendNote()`

**Files:**
- Modify: `app/Models/Task.php`
- Test: `tests/Feature/TaskAppendNoteTest.php`

**Interfaces:**
- Consumes: `auth()->user()` (utente autenticato corrente), `$task->notes` (nullable string esistente).
- Produces: `Task::appendNote(string $note, bool $persist = true): void`. Consumato da `TaskController::update()` (Task 4).

- [ ] **Step 1: Scrivi il test che fallisce**

Crea `tests/Feature/TaskAppendNoteTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Quote;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TaskAppendNoteTest extends TestCase
{
    use DatabaseTransactions;

    private function makeTask(): Task
    {
        $customer = Customer::factory()->create();
        $quote = Quote::create([
            'title' => 'Quote di test',
            'customer_id' => $customer->id,
        ]);

        return Task::create([
            'quote_id' => $quote->id,
            'title' => 'Task di test',
            'due_date' => now()->addDay(),
            'notes' => 'Nota originale',
        ]);
    }

    /** @test */
    public function appendNote_prepende_la_nuova_nota_e_preserva_quella_esistente(): void
    {
        $user = User::factory()->create(['name' => 'Mario Rossi']);
        Sanctum::actingAs($user);
        $task = $this->makeTask();

        $task->appendNote('Chiamato il cliente, richiamare domani');

        $this->assertStringContainsString('Mario Rossi', $task->notes);
        $this->assertStringContainsString('Chiamato il cliente, richiamare domani', $task->notes);
        $this->assertStringContainsString('Nota originale', $task->notes);
        $this->assertTrue(
            strpos($task->notes, 'Chiamato il cliente') < strpos($task->notes, 'Nota originale'),
            'La nuova nota deve precedere quella esistente (prepend, non append)'
        );
    }

    /** @test */
    public function appendNote_persiste_su_db_di_default(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $task = $this->makeTask();

        $task->appendNote('Nota persistita');

        $this->assertStringContainsString('Nota persistita', $task->fresh()->notes);
    }

    /** @test */
    public function appendNote_non_persiste_se_persist_false(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $task = $this->makeTask();

        $task->appendNote('Nota non persistita', false);

        $this->assertStringNotContainsString('Nota non persistita', $task->fresh()->notes);
    }

    /** @test */
    public function appendNote_funziona_anche_se_notes_e_inizialmente_vuoto(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $customer = Customer::factory()->create();
        $quote = Quote::create(['title' => 'Quote', 'customer_id' => $customer->id]);
        $task = Task::create([
            'quote_id' => $quote->id,
            'title' => 'Task senza note iniziali',
            'due_date' => now()->addDay(),
        ]);

        $task->appendNote('Prima nota');

        $this->assertStringContainsString('Prima nota', $task->notes);
    }
}
```

- [ ] **Step 2: Esegui il test e verifica che fallisca**

Comando: `docker exec php81_orchestrator php artisan test --filter=TaskAppendNoteTest`
Atteso: FAIL — `Call to undefined method App\Models\Task::appendNote()`

- [ ] **Step 3: Implementa `appendNote()`**

Aggiungi a `app/Models/Task.php`, dopo `getAssigneeAttribute()`:

```php
    /**
     * Prepende una nota in cima a `notes`, con autore e timestamp, mirror
     * del comportamento reale di Story::addDevNote() (che nonostante il
     * nome storico "prepende", non accoda — la nota più recente resta
     * sempre in cima). Mai sovrascrittura del contenuto esistente.
     */
    public function appendNote(string $note, bool $persist = true): void
    {
        $sender = auth()->user();
        $divider = "<div style='height: 2px; background-color: #e2e8f0; margin: 20px 0;'></div>";
        $style = "style='background-color: #f8f9fa; border-left: 4px solid #6c757d; padding: 10px 20px;'";

        $formatted = $sender->name . ' ha aggiunto una nota il: ' . now()->format('d-m-Y H:i')
            . "\n <div $style> <p>" . $note . ' </p> </div>' . $divider;

        $this->notes = $formatted . ($this->notes ?? '');

        if ($persist) {
            $this->save();
        }
    }
```

- [ ] **Step 4: Esegui i test e verifica che passino**

Comando: `docker exec php81_orchestrator php artisan test --filter=TaskAppendNoteTest`
Atteso: PASS (4 test)

- [ ] **Step 5: Commit**

```bash
git add app/Models/Task.php tests/Feature/TaskAppendNoteTest.php
git commit -m "feat(oc:8403): add Task::appendNote() mirroring Story::addDevNote()"
```

---

## Task 4: `TaskApiRequest`

**Files:**
- Create: `app/Http/Requests/Api/TaskApiRequest.php`

**Interfaces:**
- Consumes: `App\Models\Task::STATUS_TODO`, `App\Models\Task::STATUS_COMPLETED` (costanti già esistenti).
- Produces: `TaskApiRequest` — usato come type-hint in `TaskController::store()`/`update()` (Task 5). `rules()` ritorna regole diverse per `POST` (`quote_id`, `title`, `due_date`, `notes`) e per `PATCH` (`status`, `notes`) — **nessuna regola per `creator_id` in nessuno dei due casi**, così `$request->validated()` non lo contiene mai.

Non serve un test dedicato per questo task: la validazione viene esercitata end-to-end dai test del controller (Task 5).

- [ ] **Step 1: Implementa `TaskApiRequest`**

```php
<?php

namespace App\Http\Requests\Api;

use App\Models\Task;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TaskApiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Whitelist esplicita per metodo HTTP. `creator_id` non compare MAI in
     * nessuna delle due liste: resta gestito solo da Task::booted()
     * (assegnato all'utente autenticato alla creazione), non è mai un
     * campo accettato in input — impedisce il mass-assignment via API.
     */
    public function rules(): array
    {
        if ($this->isMethod('POST')) {
            return [
                'quote_id' => ['required', 'integer', 'exists:quotes,id'],
                'title'    => ['required', 'string', 'max:255'],
                'notes'    => ['sometimes', 'nullable', 'string'],
                'due_date' => ['required', 'date'],
            ];
        }

        return [
            'status' => ['sometimes', Rule::in([Task::STATUS_TODO, Task::STATUS_COMPLETED])],
            'notes'  => ['sometimes', 'string'],
        ];
    }
}
```

- [ ] **Step 2: Commit**

```bash
git add app/Http/Requests/Api/TaskApiRequest.php
git commit -m "feat(oc:8403): add TaskApiRequest with explicit field whitelist"
```

---

## Task 5: `TaskController` + route

**Files:**
- Create: `app/Http/Controllers/Api/TaskController.php`
- Modify: `routes/api.php`
- Test: `tests/Feature/Api/TaskApiTest.php`

**Interfaces:**
- Consumes: `TaskPolicy` (Task 1-2), `TaskApiRequest` (Task 4), `Task::appendNote()` (Task 3), `Task::scopeForUser()` (già esistente), `Task::$assignee` accessor (già esistente).
- Produces: `GET /api/tasks`, `GET /api/tasks/{task}`, `POST /api/tasks`, `PATCH /api/tasks/{task}` — tutti sotto `auth:sanctum`. `formatTask(Task $task): array` privato, riusato da tutti e 4 i metodi, shape: `{id, quote_id, quote_title, title, notes, due_date, status, completed_at, creator_id, assignee, created_at, updated_at}`.

- [ ] **Step 1: Scrivi i test che falliscono (autenticazione e ruolo)**

Crea `tests/Feature/Api/TaskApiTest.php`:

```php
<?php

namespace Tests\Feature\Api;

use App\Enums\QuoteStatus;
use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\Quote;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TaskApiTest extends TestCase
{
    use DatabaseTransactions;

    private function actingAs(array $roles = [UserRole::Admin]): User
    {
        $user = User::factory()->create(['roles' => $roles]);
        Sanctum::actingAs($user);
        return $user;
    }

    private function makeQuote(array $overrides = []): Quote
    {
        $customer = Customer::factory()->create();

        return Quote::create(array_merge([
            'title' => 'Quote di test',
            'customer_id' => $customer->id,
        ], $overrides));
    }

    private function makeTask(Quote $quote, array $overrides = []): Task
    {
        return Task::create(array_merge([
            'quote_id' => $quote->id,
            'title' => 'Task di test',
            'due_date' => now()->addDay(),
        ], $overrides));
    }

    /** @test */
    public function utente_non_autenticato_ottiene_401(): void
    {
        $this->getJson('/api/tasks')->assertStatus(401);
    }

    /** @test */
    public function customer_non_puo_accedere_alle_api_task(): void
    {
        $this->actingAs([UserRole::Customer]);

        $this->getJson('/api/tasks')->assertStatus(403);
    }
}
```

- [ ] **Step 2: Esegui il test e verifica che fallisca**

Comando: `docker exec php81_orchestrator php artisan test --filter=TaskApiTest`
Atteso: FAIL — 404 (route non ancora registrata) invece di 401/403.

- [ ] **Step 3: Implementa `TaskController` (solo `index`, minimale) e registra la route**

Crea `app/Http/Controllers/Api/TaskController.php`:

```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\TaskApiRequest;
use App\Models\Quote;
use App\Models\Task;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TaskController extends Controller
{
    /**
     * List the authenticated user's tasks — Task::scopeForUser() returns
     * tasks where the user owns the linked quote OR is the task creator.
     * Sorted by `due_date` ascending by default; opt-in `?sort=created_at`
     * (ascending) / `?sort=-created_at` (descending) mirrors the sort
     * syntax already used by QuoteController::index(). No pagination.
     *
     * @response array<array{id: int, quote_id: int, quote_title: string|null, title: string, notes: string|null, due_date: string, status: string, completed_at: string|null, creator_id: int|null, assignee: array{id: int, name: string, email: string}|null, created_at: string|null, updated_at: string|null}>
     */
    #[QueryParameter('sort', description: 'Sort by created_at: "created_at" for ascending, "-created_at" for descending. Any other value (including omitting this parameter) falls back to due_date ascending.', type: 'string')]
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Task::class);

        $query = Task::query()->forUser($request->user())->with('quote.user');

        if ($request->get('sort') === '-created_at') {
            $query->orderByDesc('created_at')->orderBy('id');
        } elseif ($request->get('sort') === 'created_at') {
            $query->orderBy('created_at')->orderBy('id');
        } else {
            $query->orderBy('due_date')->orderBy('id');
        }

        $tasks = $query->get();

        return response()->json($tasks->map(fn (Task $task) => $this->formatTask($task)));
    }

    private function formatTask(Task $task): array
    {
        $assignee = $task->assignee;

        return [
            'id'           => $task->id,
            'quote_id'     => $task->quote_id,
            'quote_title'  => $task->quote?->title,
            'title'        => $task->title,
            'notes'        => $task->notes,
            'due_date'     => optional($task->due_date)->toIso8601String(),
            'status'       => $task->status,
            'completed_at' => optional($task->completed_at)->toIso8601String(),
            'creator_id'   => $task->creator_id,
            'assignee'     => $assignee ? [
                'id'    => $assignee->id,
                'name'  => $assignee->name,
                'email' => $assignee->email,
            ] : null,
            'created_at'   => optional($task->created_at)->toIso8601String(),
            'updated_at'   => optional($task->updated_at)->toIso8601String(),
        ];
    }
}
```

Modifica `routes/api.php`: aggiungi l'import in cima al file (accanto agli altri import `Api\*`)

```php
use App\Http\Controllers\Api\TaskController;
```

e, dentro il gruppo `Route::middleware('auth:sanctum')->group(function () { ... })` esistente, subito dopo il blocco `/customers`:

```php
    Route::get('/tasks', [TaskController::class, 'index']);
```

- [ ] **Step 4: Esegui i test e verifica che passino**

Comando: `docker exec php81_orchestrator php artisan test --filter=TaskApiTest`
Atteso: PASS (2 test)

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/Api/TaskController.php routes/api.php tests/Feature/Api/TaskApiTest.php
git commit -m "feat(oc:8403): add TaskController::index with GET /api/tasks route"
```

- [ ] **Step 6: Scrivi il test che fallisce — scoping della lista**

Aggiungi a `tests/Feature/Api/TaskApiTest.php`:

```php
    /** @test */
    public function index_mostra_solo_task_di_cui_lutente_e_owner_o_creatore(): void
    {
        $owner = $this->actingAs([UserRole::Admin]);
        $quoteMia = $this->makeQuote();
        $quoteMia->user_id = $owner->id;
        $quoteMia->save();
        $taskMio = $this->makeTask($quoteMia);

        $quoteAltrui = $this->makeQuote();
        $altroCreator = User::factory()->create();
        $taskAltrui = $this->makeTask($quoteAltrui, ['creator_id' => $altroCreator->id]);

        $response = $this->getJson('/api/tasks')->assertStatus(200);

        $ids = collect($response->json())->pluck('id');
        $this->assertTrue($ids->contains($taskMio->id));
        $this->assertFalse($ids->contains($taskAltrui->id));
    }

    /** @test */
    public function index_mostra_task_creati_dallutente_anche_su_quote_non_proprie(): void
    {
        $user = $this->actingAs([UserRole::Developer]);
        $quoteAltrui = $this->makeQuote();
        $taskCreatoDaMe = $this->makeTask($quoteAltrui, ['creator_id' => $user->id]);

        $response = $this->getJson('/api/tasks')->assertStatus(200);

        $ids = collect($response->json())->pluck('id');
        $this->assertTrue($ids->contains($taskCreatoDaMe->id));
    }

    /** @test */
    public function index_ordina_per_due_date_asc_di_default(): void
    {
        $user = $this->actingAs([UserRole::Admin]);
        $quote = $this->makeQuote();
        $quote->user_id = $user->id;
        $quote->save();

        $tardi = $this->makeTask($quote, ['due_date' => now()->addDays(10)]);
        $presto = $this->makeTask($quote, ['due_date' => now()->addDay()]);

        $response = $this->getJson('/api/tasks')->assertStatus(200);

        $ids = collect($response->json())->pluck('id')->values();
        $this->assertEquals([$presto->id, $tardi->id], $ids->toArray());
    }

    /** @test */
    public function index_sort_created_at_desc_mostra_i_piu_recenti_prima(): void
    {
        $user = $this->actingAs([UserRole::Admin]);
        $quote = $this->makeQuote();
        $quote->user_id = $user->id;
        $quote->save();

        $vecchio = $this->makeTask($quote);
        $nuovo = $this->makeTask($quote);

        $response = $this->getJson('/api/tasks?sort=-created_at')->assertStatus(200);

        $ids = collect($response->json())->pluck('id')->values();
        $this->assertEquals([$nuovo->id, $vecchio->id], $ids->toArray());
    }

    /** @test */
    public function index_include_sempre_assignee_e_quote_title(): void
    {
        $owner = User::factory()->create(['name' => 'Owner Quote']);
        $this->actingAs([UserRole::Admin]);
        $quote = $this->makeQuote(['title' => 'Titolo Quote di Test']);
        $quote->user_id = $owner->id;
        $quote->save();
        $this->makeTask($quote, ['creator_id' => $owner->id]);

        $response = $this->getJson('/api/tasks')->assertStatus(200);

        $item = $response->json()[0];
        $this->assertEquals('Titolo Quote di Test', $item['quote_title']);
        $this->assertEquals('Owner Quote', $item['assignee']['name']);
    }

    /** @test */
    public function index_assignee_null_se_quote_senza_owner(): void
    {
        $creator = $this->actingAs([UserRole::Admin]);
        $quoteSenzaOwner = $this->makeQuote();
        $this->makeTask($quoteSenzaOwner, ['creator_id' => $creator->id]);

        $response = $this->getJson('/api/tasks')->assertStatus(200);

        $item = collect($response->json())->firstWhere('quote_id', $quoteSenzaOwner->id);
        $this->assertArrayHasKey('assignee', $item);
        $this->assertNull($item['assignee']);
    }
```

- [ ] **Step 7: Esegui i test e verifica che falliscano dove atteso**

Comando: `docker exec php81_orchestrator php artisan test --filter=TaskApiTest`
Atteso: i nuovi test relativi a scoping/ordinamento/shape passano già con l'implementazione minimale di Step 3 (nessuna modifica prevista qui) — se qualcuno fallisce, verificare che `Task::scopeForUser()` e l'ordinamento in `index()` siano cablati correttamente prima di proseguire.

- [ ] **Step 8: Commit**

```bash
git add tests/Feature/Api/TaskApiTest.php
git commit -m "test(oc:8403): cover index scoping, ordering and response shape"
```

---

## Task 6: `TaskController::show`

**Files:**
- Modify: `app/Http/Controllers/Api/TaskController.php`
- Modify: `routes/api.php`
- Modify: `tests/Feature/Api/TaskApiTest.php`

**Interfaces:**
- Consumes: `TaskPolicy::view()` (Task 1), `formatTask()` (Task 5).
- Produces: `GET /api/tasks/{task}`.

- [ ] **Step 1: Scrivi i test che falliscono**

Aggiungi a `tests/Feature/Api/TaskApiTest.php`:

```php
    /** @test */
    public function show_ritorna_il_dettaglio_del_task(): void
    {
        $this->actingAs([UserRole::Admin]);
        $quote = $this->makeQuote();
        $task = $this->makeTask($quote);

        $response = $this->getJson("/api/tasks/{$task->id}")->assertStatus(200);

        $response->assertJson(['id' => $task->id, 'title' => $task->title]);
    }

    /** @test */
    public function show_e_ruolo_only_non_richiede_ownership(): void
    {
        $this->actingAs([UserRole::Manager]);
        $quote = $this->makeQuote();
        $altroCreator = User::factory()->create();
        $taskAltrui = $this->makeTask($quote, ['creator_id' => $altroCreator->id]);

        $this->getJson("/api/tasks/{$taskAltrui->id}")->assertStatus(200);
    }
```

- [ ] **Step 2: Esegui il test e verifica che fallisca**

Comando: `docker exec php81_orchestrator php artisan test --filter=TaskApiTest`
Atteso: FAIL — 404 (route `show` non ancora registrata).

- [ ] **Step 3: Implementa `show()` e la route**

Aggiungi a `app/Http/Controllers/Api/TaskController.php`, dopo `index()`:

```php
    /**
     * Retrieve a single task's detail. Role-only authorization — no
     * ownership scoping (mirror QuotePolicy::view()): any Admin/Manager/
     * Developer can view any task's detail, even if not its quote owner
     * nor its creator.
     *
     * @response array{id: int, quote_id: int, quote_title: string|null, title: string, notes: string|null, due_date: string, status: string, completed_at: string|null, creator_id: int|null, assignee: array{id: int, name: string, email: string}|null, created_at: string|null, updated_at: string|null}
     */
    public function show(Task $task): JsonResponse
    {
        $this->authorize('view', $task);

        $task->load('quote.user');

        return response()->json($this->formatTask($task));
    }
```

Modifica `routes/api.php`, subito dopo `Route::get('/tasks', ...)`:

```php
    Route::get('/tasks/{task}', [TaskController::class, 'show']);
```

- [ ] **Step 4: Esegui i test e verifica che passino**

Comando: `docker exec php81_orchestrator php artisan test --filter=TaskApiTest`
Atteso: PASS

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/Api/TaskController.php routes/api.php tests/Feature/Api/TaskApiTest.php
git commit -m "feat(oc:8403): add TaskController::show with GET /api/tasks/{task} route"
```

---

## Task 7: `TaskController::store`

**Files:**
- Modify: `app/Http/Controllers/Api/TaskController.php`
- Modify: `routes/api.php`
- Modify: `tests/Feature/Api/TaskApiTest.php`

**Interfaces:**
- Consumes: `TaskPolicy::create()` (Task 1), `TaskApiRequest` (Task 4), `formatTask()` (Task 5).
- Produces: `POST /api/tasks` → `201` con il task creato.

- [ ] **Step 1: Scrivi i test che falliscono**

Aggiungi a `tests/Feature/Api/TaskApiTest.php`:

```php
    /** @test */
    public function store_crea_un_task_su_quote_aperta(): void
    {
        $this->actingAs([UserRole::Admin]);
        $quote = $this->makeQuote(['status' => QuoteStatus::New->value]);

        $response = $this->postJson('/api/tasks', [
            'quote_id' => $quote->id,
            'title'    => 'Richiamare il cliente',
            'due_date' => now()->addDay()->toDateString(),
        ])->assertStatus(201);

        $response->assertJson(['quote_id' => $quote->id, 'title' => 'Richiamare il cliente', 'status' => Task::STATUS_TODO]);
        $this->assertDatabaseHas('tasks', ['quote_id' => $quote->id, 'title' => 'Richiamare il cliente']);
    }

    /** @test */
    public function store_su_quote_chiusa_ritorna_403(): void
    {
        $this->actingAs([UserRole::Admin]);
        $quote = $this->makeQuote(['status' => QuoteStatus::Closed_Won->value]);

        $this->postJson('/api/tasks', [
            'quote_id' => $quote->id,
            'title'    => 'Follow-up non permesso',
            'due_date' => now()->addDay()->toDateString(),
        ])->assertStatus(403);
    }

    /** @test */
    public function store_senza_quote_id_ritorna_422(): void
    {
        $this->actingAs([UserRole::Admin]);

        $this->postJson('/api/tasks', [
            'title'    => 'Task senza quote',
            'due_date' => now()->addDay()->toDateString(),
        ])->assertStatus(422);
    }

    /** @test */
    public function store_ignora_creator_id_nel_payload(): void
    {
        $user = $this->actingAs([UserRole::Admin]);
        $altro = User::factory()->create();
        $quote = $this->makeQuote();

        $response = $this->postJson('/api/tasks', [
            'quote_id'   => $quote->id,
            'title'      => 'Task con creator_id spoofato',
            'due_date'   => now()->addDay()->toDateString(),
            'creator_id' => $altro->id,
        ])->assertStatus(201);

        $this->assertEquals($user->id, $response->json('creator_id'));
        $this->assertDatabaseHas('tasks', ['title' => 'Task con creator_id spoofato', 'creator_id' => $user->id]);
    }

    /** @test */
    public function store_ammette_due_date_nel_passato(): void
    {
        $this->actingAs([UserRole::Admin]);
        $quote = $this->makeQuote();

        $this->postJson('/api/tasks', [
            'quote_id' => $quote->id,
            'title'    => 'Task retroattivo',
            'due_date' => now()->subWeek()->toDateString(),
        ])->assertStatus(201);
    }
```

- [ ] **Step 2: Esegui il test e verifica che fallisca**

Comando: `docker exec php81_orchestrator php artisan test --filter=TaskApiTest`
Atteso: FAIL — 404 (route `store` non ancora registrata).

- [ ] **Step 3: Implementa `store()` e la route**

Aggiungi a `app/Http/Controllers/Api/TaskController.php`, dopo `show()`:

```php
    /**
     * Create a new task on an existing quote (also used for follow-ups,
     * as a plain task on the same quote — no explicit link to the
     * originating task is stored). `creator_id` is never accepted from
     * input: it is set automatically to the authenticated user by
     * Task::booted(). Denied (403) when the target quote is closed_won or
     * closed_lost (see TaskPolicy::create()).
     *
     * @response array{id: int, quote_id: int, quote_title: string|null, title: string, notes: string|null, due_date: string, status: string, completed_at: string|null, creator_id: int|null, assignee: array{id: int, name: string, email: string}|null, created_at: string|null, updated_at: string|null}
     */
    public function store(TaskApiRequest $request): JsonResponse
    {
        $quote = Quote::findOrFail($request->input('quote_id'));

        $this->authorize('create', [Task::class, $quote]);

        $task = new Task();
        $task->quote_id = $quote->id;
        $task->title = $request->input('title');
        $task->notes = $request->input('notes');
        $task->due_date = $request->input('due_date');
        $task->save();

        $task->load('quote.user');

        return response()->json($this->formatTask($task), 201);
    }
```

Modifica `routes/api.php`, subito dopo `Route::get('/tasks/{task}', ...)`:

```php
    Route::post('/tasks', [TaskController::class, 'store']);
```

- [ ] **Step 4: Esegui i test e verifica che passino**

Comando: `docker exec php81_orchestrator php artisan test --filter=TaskApiTest`
Atteso: PASS

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/Api/TaskController.php routes/api.php tests/Feature/Api/TaskApiTest.php
git commit -m "feat(oc:8403): add TaskController::store with POST /api/tasks route"
```

---

## Task 8: `TaskController::update`

**Files:**
- Modify: `app/Http/Controllers/Api/TaskController.php`
- Modify: `routes/api.php`
- Modify: `tests/Feature/Api/TaskApiTest.php`

**Interfaces:**
- Consumes: `TaskPolicy::update()`/`updateStatus()` (Task 2), `Task::appendNote()` (Task 3), `TaskApiRequest` (Task 4), `formatTask()` (Task 5).
- Produces: `PATCH /api/tasks/{task}` → `200` con il task aggiornato.

- [ ] **Step 1: Scrivi i test che falliscono**

Aggiungi a `tests/Feature/Api/TaskApiTest.php`:

```php
    /** @test */
    public function creator_puo_segnare_il_proprio_task_completato_e_completed_at_si_valorizza(): void
    {
        $creator = $this->actingAs([UserRole::Developer]);
        $quote = $this->makeQuote();
        $task = $this->makeTask($quote, ['creator_id' => $creator->id]);

        $response = $this->patchJson("/api/tasks/{$task->id}", ['status' => Task::STATUS_COMPLETED])
            ->assertStatus(200);

        $response->assertJson(['status' => Task::STATUS_COMPLETED]);
        $this->assertNotNull($task->fresh()->completed_at);
    }

    /** @test */
    public function riaprire_un_task_azzera_completed_at(): void
    {
        $creator = $this->actingAs([UserRole::Developer]);
        $quote = $this->makeQuote();
        $task = $this->makeTask($quote, ['creator_id' => $creator->id, 'status' => Task::STATUS_COMPLETED, 'completed_at' => now()]);

        $this->patchJson("/api/tasks/{$task->id}", ['status' => Task::STATUS_TODO])->assertStatus(200);

        $this->assertNull($task->fresh()->completed_at);
    }

    /** @test */
    public function non_creator_non_puo_cambiare_status(): void
    {
        $this->actingAs([UserRole::Admin]);
        $quote = $this->makeQuote();
        $altroCreator = User::factory()->create();
        $task = $this->makeTask($quote, ['creator_id' => $altroCreator->id]);

        $this->patchJson("/api/tasks/{$task->id}", ['status' => Task::STATUS_COMPLETED])->assertStatus(403);
        $this->assertEquals(Task::STATUS_TODO, $task->fresh()->status);
    }

    /** @test */
    public function non_creator_puo_aggiungere_solo_notes(): void
    {
        $this->actingAs([UserRole::Manager]);
        $quote = $this->makeQuote();
        $altroCreator = User::factory()->create();
        $task = $this->makeTask($quote, ['creator_id' => $altroCreator->id]);

        $response = $this->patchJson("/api/tasks/{$task->id}", ['notes' => 'Email inviata al cliente'])
            ->assertStatus(200);

        $this->assertStringContainsString('Email inviata al cliente', $response->json('notes'));
        $this->assertStringContainsString('Email inviata al cliente', $task->fresh()->notes);
    }

    /** @test */
    public function payload_misto_da_non_creator_fallisce_tutto_o_niente(): void
    {
        $this->actingAs([UserRole::Admin]);
        $quote = $this->makeQuote();
        $altroCreator = User::factory()->create();
        $task = $this->makeTask($quote, ['creator_id' => $altroCreator->id, 'notes' => 'Nota preesistente']);

        $this->patchJson("/api/tasks/{$task->id}", [
            'status' => Task::STATUS_COMPLETED,
            'notes'  => 'Questa nota non deve essere salvata',
        ])->assertStatus(403);

        $fresh = $task->fresh();
        $this->assertEquals(Task::STATUS_TODO, $fresh->status);
        $this->assertEquals('Nota preesistente', $fresh->notes);
    }

    /** @test */
    public function status_non_valido_ritorna_422(): void
    {
        $creator = $this->actingAs([UserRole::Developer]);
        $quote = $this->makeQuote();
        $task = $this->makeTask($quote, ['creator_id' => $creator->id]);

        $this->patchJson("/api/tasks/{$task->id}", ['status' => 'archived'])->assertStatus(422);
    }

    /** @test */
    public function update_ignora_creator_id_nel_payload(): void
    {
        $creator = $this->actingAs([UserRole::Developer]);
        $altro = User::factory()->create();
        $quote = $this->makeQuote();
        $task = $this->makeTask($quote, ['creator_id' => $creator->id]);

        $this->patchJson("/api/tasks/{$task->id}", [
            'notes'      => 'Nota qualsiasi',
            'creator_id' => $altro->id,
        ])->assertStatus(200);

        $this->assertEquals($creator->id, $task->fresh()->creator_id);
    }
```

- [ ] **Step 2: Esegui il test e verifica che fallisca**

Comando: `docker exec php81_orchestrator php artisan test --filter=TaskApiTest`
Atteso: FAIL — 404 (route `update` non ancora registrata).

- [ ] **Step 3: Implementa `update()` e la route**

Aggiungi a `app/Http/Controllers/Api/TaskController.php`, dopo `store()`:

```php
    /**
     * Update a task, limited to two fields with per-field authorization
     * (documented in TaskPolicy): `status` requires the authenticated user
     * to be the task's creator (mirror of
     * App\Nova\Actions\ToggleTaskCompleted::authorizedToRun()); `notes` is
     * open to any Admin/Manager/Developer. The `status` authorization
     * check runs BEFORE any mutation is applied, so a mixed payload
     * {status, notes} from a non-creator fails the entire request with
     * 403 — `notes` is never persisted in that case, even though it would
     * have been allowed on its own. `completed_at` is updated
     * automatically by the existing Task::booted() hook.
     *
     * @response array{id: int, quote_id: int, quote_title: string|null, title: string, notes: string|null, due_date: string, status: string, completed_at: string|null, creator_id: int|null, assignee: array{id: int, name: string, email: string}|null, created_at: string|null, updated_at: string|null}
     */
    public function update(TaskApiRequest $request, Task $task): JsonResponse
    {
        $this->authorize('update', $task);

        if ($request->has('status')) {
            $this->authorize('updateStatus', $task);
        }

        if ($request->has('status')) {
            $task->status = $request->input('status');
            $task->save();
        }

        if ($request->has('notes')) {
            $task->appendNote($request->input('notes'));
        }

        $task->load('quote.user');

        return response()->json($this->formatTask($task));
    }
```

Modifica `routes/api.php`, subito dopo `Route::post('/tasks', ...)`:

```php
    Route::patch('/tasks/{task}', [TaskController::class, 'update']);
```

- [ ] **Step 4: Esegui i test e verifica che passino**

Comando: `docker exec php81_orchestrator php artisan test --filter=TaskApiTest`
Atteso: PASS

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/Api/TaskController.php routes/api.php tests/Feature/Api/TaskApiTest.php
git commit -m "feat(oc:8403): add TaskController::update with per-field PATCH authorization"
```

---

## Task 9: Suite completa e verifica documentazione Scramble

**Files:**
- Nessuna modifica di codice — solo verifica.

- [ ] **Step 1: Esegui l'intera suite dei test Task**

Comando: `docker exec php81_orchestrator php artisan test --filter=Task`
Atteso: PASS (tutti i test di `TaskPolicyTest`, `TaskAppendNoteTest`, `TaskApiTest`, `TaskTest` esistente).

- [ ] **Step 2: Esegui l'intera suite del progetto per verificare nessuna regressione**

Comando: `docker exec php81_orchestrator php artisan test`
Atteso: PASS (nessun test preesistente rotto — in particolare `QuoteApiTest`, `QuotePolicyTest`, `ApiDocsTest`).

- [ ] **Step 3: Verifica che la documentazione Scramble si generi senza errori**

Comando: `docker exec php81_orchestrator php artisan route:list --path=api/tasks` (verifica che le 4 route siano registrate correttamente)

Se il progetto ha un comando/endpoint per rigenerare `/docs/api.json` esplicitamente, eseguilo e verifica che non sollevi eccezioni per i nuovi endpoint Task (altrimenti la generazione è lazy, verificata automaticamente da `tests/Feature/Api/ApiDocsTest.php` già eseguito allo Step 2).

- [ ] **Step 4: Commit finale (se necessario, es. fix minori emersi dalla suite completa)**

```bash
git add -A
git commit -m "test(oc:8403): verify full suite and Scramble docs generation"
```

*(Se lo Step 2/3 non richiede alcuna modifica, questo commit va omesso.)*

---

## Self-Review (checklist per chi esegue il piano)

- **Copertura spec:** ogni bullet di "Requisiti" in `overview.md` ha un task corrispondente (TaskPolicy → Task 1-2; GET index/show → Task 5-6; POST → Task 7; PATCH → Task 8; appendNote → Task 3; TaskApiRequest whitelist → Task 4; nessun DELETE → nessun task, coerente; documentazione Scramble → docblock inline in ogni metodo di Task 5-8, verificata in Task 9).
- **Nessun placeholder:** ogni step contiene codice completo, nessun "TODO"/"gestisci gli edge case" generico.
- **Coerenza dei tipi:** `formatTask()` (Task 5) è l'unica funzione che produce la shape di risposta, riusata identica in `show()`/`store()`/`update()` — nessuna divergenza di nomi di campo tra i task.
