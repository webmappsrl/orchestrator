> Ticket: oc:8722

# Piano — Task: assegnatario indipendente dall'owner della trattativa

Spec: [overview.md](overview.md). Tutto nel repo principale `orchestrator`, branch
`feature/oc-8722-task-assegnatario-indipendente-dall-owner-della-trattativa` (già esistente).

**Regole per l'esecuzione**
- Nessun `git commit`, `git add` o `git push` durante l'esecuzione: i commit indicati a fine task
  sono istruzioni per la dev, eseguiti solo dopo il review-gate e la sua conferma esplicita.
- Test sempre su `orchestrator_test` (vedi `docs/howto/eseguire-i-test.md`):
  `docker exec php81_orchestrator php artisan test --filter=<Classe>`. Prima del Task 1, allineare il
  DB di test: `docker exec php81_orchestrator bash -c "DB_DATABASE=orchestrator_test php artisan migrate"`.
- `UserFactory` assegna 2 ruoli a caso (`database/factories/UserFactory.php:27`): nei test nuovi e
  riscritti impostare sempre `roles` in modo esplicito.
- Per ogni task: prima il test che fallisce, poi il codice, poi il test che passa.

---

## Task 1 — Lista unica dei ruoli che accedono ai task

**File:** `app/Policies/TaskPolicy.php`, `app/Models/User.php`, `tests/Feature/Api/TaskPolicyTest.php`

1. Test: `User::canAccessTasks()` è vero per Admin, Manager, Developer e falso per Editor, Customer,
   ruoli vuoti.
2. Costante `TaskPolicy::ROLES = [UserRole::Admin, UserRole::Manager, UserRole::Developer]` e metodo
   `User::canAccessTasks(): bool` che la usa.
3. Scope `User::scopeWithTaskAccess($query)`: `whereJsonContains('roles', …)` in OR per ogni ruolo
   della costante (stesso idioma di `app/Nova/Customer.php:202`). Test dello scope.
4. `TaskPolicy::before()` usa `canAccessTasks()`. I test esistenti di `TaskPolicyTest` passano.

Commit: `refactor(oc:8722): lista unica dei ruoli che accedono ai task`

## Task 2 — Colonna `assignee_id` e relazione

**File:** `database/migrations/<data>_add_assignee_id_to_tasks_table.php` (nuovo), `app/Models/Task.php`,
`app/Models/User.php`, `tests/Feature/TaskTest.php`

1. Migration: `foreignId('assignee_id')->nullable()->after('creator_id')->constrained('users')->nullOnDelete()`;
   `down()` con `dropConstrainedForeignId('assignee_id')` (stesso schema di
   `2026_08_19_123457_add_creator_id_to_tasks_table.php`).
2. `Task`: `assignee_id` in `$fillable`; relazione `assignee(): BelongsTo` verso `User` su
   `assignee_id`; **rimuovere** `getAssigneeAttribute()`.
3. `User`: relazione `assignedTasks(): HasMany` verso `Task` su `assignee_id`.
4. Riscrivere `test_assignee_accessor_resolves_via_quote_user` → la relazione restituisce l'utente
   di `assignee_id`, non l'owner.

Commit: `feat(oc:8722): colonna assignee_id sui task`

## Task 3 — Regola del default in un punto solo

**File:** `app/Models/Task.php`, `tests/Feature/TaskTest.php`

1. Test di `Task::defaultAssigneeId(?Quote $quote, ?User $actor): ?int`:
   - Quote con owner ammesso → owner;
   - Quote senza owner → `$actor`;
   - owner con soli ruoli Editor/Customer → `$actor`;
   - Quote senza owner e `$actor` nullo → `null`.
2. Implementazione statica (riusabile da oc:8730).
3. Hook `creating` di `Task::booted()`, dopo `creator_id`: se `assignee_id` è nullo,
   `defaultAssigneeId($task->quote, auth()->user())`.
4. Test: `Task::create()` autenticato su Quote con owner → owner; su Quote senza owner → utente
   autenticato; con `assignee_id` esplicito → resta quello. Riscrivere
   `test_can_create_task_on_quote_without_owner_with_null_assignee` secondo la regola nuova.

Commit: `feat(oc:8722): assegnatario di default alla creazione del task`

## Task 4 — Migrazione dei task esistenti

**File:** `database/migrations/<data>_backfill_tasks_assignee_id.php` (nuovo), `tests/Feature/TaskTest.php`

1. `up()`: un solo `UPDATE` SQL:
   `assignee_id = COALESCE(<owner della quote se ha un ruolo ammesso>, creator_id)` per i task con
   `assignee_id` nullo. Il controllo del ruolo dell'owner in SQL su `users.roles` (JSON), coerente
   con `defaultAssigneeId()`.
2. `down()`: nessuna azione (la colonna la toglie la migration del Task 2).
3. Test: eseguire la logica della migration su tre task (owner ammesso, senza owner con creatore,
   senza owner né creatore) e verificare i tre esiti.
4. Annotare in `notes.md` la query di conteggio in produzione dell'overview, da eseguire prima del
   deploy.

Commit: `feat(oc:8722): migrazione dell'assegnatario dei task esistenti`

## Task 5 — Riassegnazione alla cancellazione dell'utente

**File:** `app/Models/User.php`, `tests/Feature/TaskTest.php`

1. Test: utente assegnatario (non owner, nessuna Story) cancellato → i suoi task passano all'owner
   della trattativa; trattativa senza owner → al creatore; creatore = utente cancellato e nessun
   owner → `assignee_id` nullo.
2. Evento `deleting` in `User::booted()`: per ogni task in `assignedTasks`, `assignee_id` =
   `defaultAssigneeId($task->quote, $task->creator)` escludendo l'utente che si sta cancellando,
   salvato con `saveQuietly()`.

Commit: `feat(oc:8722): riassegnazione dei task alla cancellazione dell'utente`

## Task 6 — Permessi: chiunque acceda ai task cambia lo stato

**File:** `app/Policies/TaskPolicy.php`, `app/Nova/Actions/ToggleTaskCompleted.php`,
`tests/Feature/Api/TaskPolicyTest.php`, `tests/Feature/Api/TaskApiTest.php`, `tests/Feature/TaskTest.php`

1. Riscrivere i test: `non_creator_non_puo_cambiare_status` → un Developer non creatore **può**;
   `test_toggle_action_authorized_only_for_creator` → autorizzata per Admin/Manager/Developer,
   negata a Editor/Customer.
2. `TaskPolicy::updateStatus()` → `return true;` (il filtro sui ruoli è in `before()`); docblock
   aggiornato.
3. `ToggleTaskCompleted::authorizedToRun()` → `$request->user()?->canAccessTasks() ?? false`.

Commit: `feat(oc:8722): cambio di stato dei task aperto a chi accede ai task`

## Task 7 — `scopeForUser()` su assegnatario e creatore

**File:** `app/Models/Task.php`, `tests/Feature/TaskTest.php`, `tests/Feature/TaskNovaResourceTest.php`

1. Riscrivere `test_for_user_scope_filters_by_quote_owner` → l'owner non vede il task assegnato e
   creato da altri; vede quelli assegnati a lui; `test_for_user_scope_also_includes_tasks_created_by_the_user`
   resta valido.
2. `scopeForUser()`: `where('assignee_id', $user->id)->orWhere('creator_id', $user->id)` dentro un
   `where` raggruppato.
3. `test_developer_still_sees_only_own_tasks_in_global_index` aggiornato alla regola nuova.

Commit: `feat(oc:8722): i task di un utente sono quelli assegnati o creati da lui`

## Task 8 — API

**File:** `app/Http/Requests/Api/TaskApiRequest.php`, `app/Http/Controllers/Api/TaskController.php`,
`tests/Feature/Api/TaskApiTest.php`

1. Test:
   - POST senza `assignee_id` → owner; con `assignee_id: null` → owner; Quote senza owner → utente
     del token; con `assignee_id` valido → quello;
   - POST/PATCH con `assignee_id` di un Customer o inesistente → 422;
   - PATCH `assignee_id: null` → 422; PATCH con `assignee_id` valido → cambiato;
   - GET restituisce assegnati + creati, non i task della propria trattativa assegnati e creati da
     altri; riscrivere `index_assignee_null_se_quote_senza_owner`;
   - la forma della risposta non cambia (`assignee` = `{id, name, email}`).
2. Regole: POST `assignee_id` → `['sometimes', 'nullable', 'integer', <exists fra utenti con
   withTaskAccess>]`; PATCH → `['sometimes', 'required', 'integer', <stessa regola>]`. Docblock
   della whitelist aggiornato.
3. Controller: `store` passa `assignee_id` solo se valorizzato (il resto lo fa l'hook); `update`
   lo scrive se presente; eager load `assignee` invece di `quote.user` dove serviva per
   l'assegnatario; docblock Scramble di `index`, `show`, `update` aggiornati.

Commit: `feat(oc:8722): assignee_id nell'API dei task`

## Task 9 — Nova: campo Assegnatario precompilato

**File:** `app/Nova/Task.php`, `tests/Feature/TaskNovaResourceTest.php`

1. Test: il campo `assignee` è presente nei form di entrambi i rami (`viaResource` vuoto e
   `quotes`); le opzioni contengono solo utenti con ruolo ammesso.
2. Campo `BelongsTo::make(__('Assignee'), 'assignee', User::class)->searchable()->rules('required')`
   con `relatableQueryUsing` (o `relatableUsers` nel resource) su `withTaskAccess()`, in entrambi i
   rami. La colonna calcolata `Text::make(__('Assignee'), …)` viene sostituita dal `BelongsTo`
   sull'indice.
3. Precompilazione:
   - ramo `quotes`: `->default(fn ($request) => Task::defaultAssigneeId(<quote da viaResourceId>, $request->user()))`;
   - ramo globale: `->dependsOn(['quote'], fn ($field, $request, $formData) => …)` che imposta il
     valore **solo se** `$request->isCreateOrAttachRequest()`.
4. `indexQuery()`: eager load `assignee`.
5. Verifica manuale nel browser (Nova reale): creazione dal tab della trattativa, creazione dalla
   vista globale con cambio di trattativa, modifica di un task con assegnatario scelto a mano (non
   deve cambiare).

Commit: `feat(oc:8722): campo Assegnatario precompilato nel form dei task`

## Task 10 — Nova: filtro Assegnatario sul task

**File:** `app/Nova/Filters/TaskAssigneeFilter.php`, `tests/Feature/TaskNovaResourceTest.php`

1. Riscrivere `test_assignee_filter_apply_filters_by_quote_owner` e
   `test_assignee_filter_options_only_include_users_with_assigned_tasks` su `assignee_id`.
2. `apply()` → `where('assignee_id', $value)`; `options()` → `User::whereHas('assignedTasks')`.

Commit: `feat(oc:8722): filtro Assegnatario sull'assegnatario del task`

## Task 11 — Nova: vista «I miei task»

**File:** `app/Nova/MyTask.php` (nuovo), `app/Nova/Filters/MyTaskScopeFilter.php` (nuovo),
`app/Providers/NovaServiceProvider.php`, `lang/it.json`, `lang/en.json`, `tests/Feature/TaskNovaResourceTest.php`

1. Test:
   - `MyTask::indexQuery()` restituisce assegnati + creati dell'utente loggato, anche per un Admin;
   - il filtro su `assigned` restituisce solo `assignee_id` = me, su `created` solo `creator_id` = me;
   - `default()` del filtro è `assigned`;
   - `MyTask::filters()` contiene `MyTaskScopeFilter` e `TaskDueDateFilter`, non `TaskAssigneeFilter`.
2. `MyTask extends Task`: `label()` = `__('My tasks')`, `uriKey()` = `my-tasks`,
   `indexQuery()` → `forUser($request->user())->reorder('due_date', 'asc')` con eager load,
   `filters()` → `[MyTaskScopeFilter, TaskDueDateFilter]`.
3. `MyTaskScopeFilter`: select con opzioni `__('Assigned to me') => 'assigned'`,
   `__('Created by me') => 'created'`, `default()` = `'assigned'`.
4. Menu: `MenuItem::resource(MyTask::class)` dopo `MenuItem::resource(Task::class)` nella sezione CRM
   (`app/Providers/NovaServiceProvider.php:130-133`).
5. Traduzioni in `lang/it.json` e `lang/en.json`: `My tasks`, `Assigned to me`, `Created by me`,
   nome del filtro.

Commit: `feat(oc:8722): vista I miei task`

## Task 12 — Suite completa e verifica

1. `docker exec php81_orchestrator php artisan test` (attenzione: `StoryChildFieldTest` è flaky,
   vedi `docs/howto/eseguire-i-test.md`).
2. Verifica manuale in Nova della vista «I miei task» e del filtro.
3. Aggiornare `notes.md` e `docs/knowledge/task-e-quote.md` (fase update-context).
