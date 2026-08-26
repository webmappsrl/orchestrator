> Ticket: oc:8402

# Piano di implementazione — Riordino colonne e filtro assegnatario Task

Riferimento: `docs/features/8402-riordino-colonne-filtro-assegnatario-task/overview.md` (approvato).

Nota: la sub-skill `superpowers:writing-plans` non è installata in questa sessione — questo piano è stato scritto direttamente da `wm-plan` seguendo la stessa struttura richiesta (task step-by-step, convenzione commit `oc:8402`), senza passare dalla sub-skill.

## Task 1 — Riordino campi e Date::make in `app/Nova/Task.php`

File: `app/Nova/Task.php`

1. Riordina l'array ritornato da `fields()` nel nuovo ordine: `ID`, `Customer` (Cliente), `Quote` (Preventivo), `Text title` (Task), `Due date` (Scadenza), `Badge status` (Stato), `Boolean completed` (Completato), `Assignee` (Assegnatario, vedi Task 2), `Tiptap notes`.
   - Attenzione: il campo `Customer` oggi è definito come `Text::make(__('Customer'), function () {...})->asHtml()->exceptOnForms()` — sposta il blocco così com'è, nessuna modifica alla logica interna.
2. Sostituisci `DateTime::make(__('Due date'), 'due_date')` con `Date::make(__('Due date'), 'due_date')`. Aggiorna l'import `use Laravel\Nova\Fields\DateTime;` → `use Laravel\Nova\Fields\Date;` (rimuovi l'import `DateTime` se non più usato altrove nel file — verifica con grep prima di rimuovere).
3. Non toccare `urgencyBadgeKey()`/`urgencyBadgeLabel()` — restano invariati (vedi Rischi in overview.md, comportamento accettato).

Commit: `feat(oc:8402): reorder Task Nova fields and switch Due date to date-only`

## Task 2 — Nuovo campo Assegnatario in `app/Nova/Task.php`

File: `app/Nova/Task.php`

1. Aggiungi un nuovo campo computed, posizionato secondo l'ordine del Task 1 (dopo Completato):
   ```php
   Text::make(__('Assignee'), function () {
       return $this->assignee?->name ?? '—';
   })->exceptOnForms(),
   ```
   - Segui lo stesso pattern del campo `Customer` esistente (computed, `exceptOnForms()`), ma senza `asHtml()`/link cliccabile — l'overview non richiede un link, solo la visualizzazione del nome.
   - `$this->assignee` usa l'accessor `Task::assignee` (`app/Models/Task.php:46`, già esistente da oc:8327) — non serve crearlo.
2. Verifica manualmente (tinker o test, vedi Task 5) che un Task con `quote->user_id` null mostri `—` senza errori.

Commit: `feat(oc:8402): add computed Assignee column to Task Nova resource`

## Task 3 — Nuovo filtro `TaskAssigneeFilter`

File: `app/Nova/Filters/TaskAssigneeFilter.php` (nuovo)

Ricalca 1:1 la struttura di `app/Nova/Filters/TaskDueDateFilter.php` (stesso namespace, stesso `$component = 'select-filter'`):

```php
<?php

namespace App\Nova\Filters;

use App\Models\User;
use Laravel\Nova\Filters\Filter;
use Laravel\Nova\Http\Requests\NovaRequest;

class TaskAssigneeFilter extends Filter
{
    public $component = 'select-filter';

    public function apply(NovaRequest $request, $query, $value)
    {
        return $query->whereHas('quote', function ($q) use ($value) {
            $q->where('user_id', $value);
        });
    }

    public function options(NovaRequest $request)
    {
        return User::whereHas('quotes.tasks')
            ->orderBy('name')
            ->get()
            ->mapWithKeys(fn (User $user) => [$user->name => $user->id])
            ->all();
    }

    public function name()
    {
        return __('Assignee');
    }
}
```

Nota: `name()` riusa la stessa chiave `Assignee` già presente in `lang/it.json`/`lang/en.json` (oc:8327) — stessa etichetta del campo colonna, nessuna nuova chiave necessaria per questo filtro.

Registra il filtro in `Task.php::filters()`:
```php
public function filters(NovaRequest $request)
{
    return [
        new TaskDueDateFilter(),
        new TaskAssigneeFilter(),
    ];
}
```

Commit: `feat(oc:8402): add TaskAssigneeFilter Nova filter`

## Task 4 — Branching per ruolo in `Task::indexQuery()`

File: `app/Nova/Task.php`

Modifica `indexQuery()` per bypassare `forUser()` solo per Admin/Manager, lasciando invariato il comportamento per tutti gli altri ruoli:

```php
public static function indexQuery(NovaRequest $request, $query)
{
    if ($request->viaResource === 'quotes') {
        return $query;
    }

    $user = $request->user();

    if ($user === null) {
        return $query->whereRaw('1 = 0');
    }

    if ($user->hasRole(UserRole::Admin) || $user->hasRole(UserRole::Manager)) {
        return $query->reorder('due_date', 'asc');
    }

    return $query->forUser($user)->reorder('due_date', 'asc');
}
```

Aggiungi l'import `use App\Enums\UserRole;` in cima al file.

Commit: `feat(oc:8402): restrict Task global index bypass of forUser to Admin/Manager roles`

## Task 5 — Test

File: `tests/Feature/TaskNovaResourceTest.php` (nuovo — separato da `tests/Feature/TaskTest.php` che copre il model, non la risorsa Nova)

Segui il pattern già in uso nel progetto per testare `indexQuery`/filtri Nova senza instradare l'HTTP reale (vedi `tests/Feature/NovaShardFiltersTest.php`, `tests/Feature/QuarterTagFilterTest.php`: `NovaRequest::create('/', 'GET')` + `$this->actingAs($user)` prima di chiamare il metodo statico/il filtro direttamente).

Casi da coprire:
1. **`Task::indexQuery` per Admin** — crea 2 task di 2 utenti diversi (owner di quote diverse), `actingAs` un utente Admin, verifica che `Task::indexQuery($request, Task::query())->get()` ritorni entrambi.
2. **`Task::indexQuery` per Manager** — stesso scenario, ruolo Manager, stesso esito (tutti i task).
3. **`Task::indexQuery` per Developer** — stesso scenario, ruolo Developer: verifica che ritorni solo i task dove l'utente è owner della quote o creatore (comportamento invariato, riusa le stesse asserzioni già presenti in `tests/Feature/TaskTest.php` per `scopeForUser` come riferimento, non duplicarle).
4. **`TaskAssigneeFilter::options`** — crea utenti con e senza quote/task collegati, verifica che solo chi ha almeno un task assegnato compaia nelle opzioni.
5. **`TaskAssigneeFilter::apply`** — crea task di 2 utenti diversi, verifica che filtrando per uno dei due `user_id` la query ritorni solo i suoi task.
6. **Campo Assegnatario con quote senza owner** — un Task su una Quote con `user_id` null: verifica (a livello di model, `$task->assignee` è null) che non generi errori; non serve un test HTTP sul rendering Nova per questo caso limite (già coperto a livello di accessor da oc:8327).

Commit: `test(oc:8402): cover Task indexQuery role branching and assignee filter`

## Task 6 — Verifica manuale

1. Avvia il container Docker (`docker compose -f local.compose.yml up -d`, se non già attivo).
2. `php artisan test --filter=TaskNovaResourceTest` e `php artisan test --filter=TaskTest` — verifica che nessun test esistente si rompa.
3. Da Nova UI (utente Admin/Manager): apri Resources → Tasks, verifica ordine colonne, colonna Scadenza senza orario, colonna Assegnatario popolata, filtro Assegnatario funzionante e con opzioni corrette.
4. Da Nova UI (utente Developer, se disponibile in ambiente di test): verifica che la vista resti scoped come oggi (nessuna regressione).
5. Apri il sub-panel Task dentro il dettaglio di una Quote: verifica che sia invariato (nessun riordino, nessuna nuova colonna/filtro).

Nessun commit associato a questo task (solo verifica).
