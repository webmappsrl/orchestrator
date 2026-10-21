> Ticket: oc:8729

# Piano — Trattative: vista e API di quelle aperte senza task attivi

Tutto nel repo principale `orchestrator`, sul branch già esistente
`feature/oc-8729-trattative-vista-e-api-di-quelle-aperte-senza-task-attivi` (nessun submodule).
Requisiti e motivazioni sono in [overview.md](overview.md).

**Commit:** i commit indicati sotto sono istruzioni per la dev, non azioni da eseguire in autonomia.
Nessun `git add`/`git commit`/`git push` durante l'esecuzione: il commit si fa dopo il review gate,
con conferma esplicita.

**Test:** sempre sul DB `orchestrator_test`, dentro il container
(`docker exec php81_orchestrator php artisan test --filter=<Classe>`), mai con
`DB_DATABASE=orchestrator` — vedi [docs/howto/eseguire-i-test.md](../../howto/eseguire-i-test.md).

---

## Task 1 — Definizione unica sul model `Quote`

**File:** `app/Models/Quote.php`, nuovo `tests/Feature/QuoteWithoutActiveTasksScopeTest.php`

1. Scrivere il test (fallisce finché lo scope non esiste), con `DatabaseTransactions` e trattative
   create come in `QuoteNovaResourceTest::makeQuote()`:
   - trattativa `new` senza task → inclusa;
   - trattativa `presented` con soli task `completed` → inclusa;
   - trattativa `on hold` con un task `todo` assegnato e creato da un altro utente → esclusa
     (i task contano chiunque ne sia assegnatario o creatore);
   - trattative `closed won` e `closed lost` senza task → escluse;
   - un task `todo` con `due_date` passata conta come attivo → trattativa esclusa.
2. Aggiungere a `Quote` lo scope `scopeOpenWithoutActiveTasks($query)`, accanto a
   `scopeWithNextTodoTask` e con un docblock nello stesso stile:
   ```php
   return $query
       ->whereNotIn('status', [QuoteStatus::Closed_Won->value, QuoteStatus::Closed_Lost->value])
       ->whereDoesntHave('tasks', fn ($tasks) => $tasks->where('status', Task::STATUS_TODO));
   ```
   Nel docblock: è la definizione di oc:8729, usata da `QuoteController::index()` e dalla Lens
   `QuotesWithoutActiveTasks`; passa da `tasks()`, che non filtra per utente.
3. Eseguire `--filter=QuoteWithoutActiveTasksScopeTest`: verde.

## Task 2 — Parametro `without_active_tasks` su `GET /api/quotes`

> ⚠️ L'implementazione ha deviato da questo task: [notes.md](notes.md#task-2--parametro-without_active_tasks-su-get-apiquotes)

**File:** `app/Http/Controllers/Api/QuoteController.php`, `tests/Feature/Api/QuoteApiTest.php`,
`tests/Feature/Api/QuoteApiDocsTest.php`

1. Test in `QuoteApiTest` (utente Admin, come `actingAsAdmin()`):
   - `?without_active_tasks=1` restituisce la trattativa aperta senza task e non quella con un task
     `todo` di un altro utente né quella `closed won`;
   - `?without_active_tasks=0` e nessun parametro restituiscono le stesse trattative di oggi;
   - `?without_active_tasks=true` è accettato e filtra;
   - `?without_active_tasks=si` risponde 422;
   - combinazione con `customer_id`: solo le trattative scoperte di quel cliente;
   - combinazione con `status=closed won`: elenco vuoto, risposta 200;
   - combinazione con `per_page`: `meta.total` conta solo le trattative scoperte.
2. In `QuoteApiDocsTest::test_quotes_index_documents_all_filter_and_pagination_parameters` aggiungere
   `without_active_tasks` all'elenco dei parametri attesi.
3. In `QuoteController::index()`:
   - aggiungere `'without_active_tasks' => ['sometimes', 'boolean']` alle regole **passate da
     variabile** (aggiungerla a `$statusRules`, o a una seconda variabile unita prima di `validate()`),
     mai scritte dentro `validate([...])`: il commento alle righe 58-60 spiega perché;
   - dopo i filtri `customer_id`/`status`: `if ($request->boolean('without_active_tasks'))
     { $query->openWithoutActiveTasks(); }` — non `filled()`, che attiverebbe il filtro con `0`;
   - nuovo attributo `#[QueryParameter('without_active_tasks', …, type: 'bool')]` dopo `status`, con
     una descrizione che dica: trattative aperte (non `closed won`/`closed lost`) senza nessun task
     `todo` di qualunque utente; si combina con gli altri filtri e la paginazione; è la strada per
     trovare le trattative non seguite; valori non booleani → 422.
4. Eseguire `--filter=QuoteApiTest` e `--filter=QuoteApiDocsTest`: verdi.

## Task 3 — Filtri e `quote_status` su `/api/tasks`

> ⚠️ L'implementazione ha deviato da questo task: [notes.md](notes.md#task-3--filtri-e-quote_status-su-apitasks)

**File:** `app/Http/Controllers/Api/TaskController.php`, `tests/Feature/Api/TaskApiTest.php`

1. Test in `TaskApiTest` (helper `loginAs`, `makeQuote`, `makeTask` già presenti):
   - `?status=todo` / `?status=completed` filtrano; `?status=altro` → 422;
   - `?quote_id=<id>` restituisce solo i task di quella trattativa; `?quote_id=abc` → 422;
   - `?due_from=` e `?due_to=` (estremi inclusi, anche uno solo): un task in scadenza il giorno di
     `due_to` alle 18:00 è incluso; `?due_to=20-10-2026` → 422; `due_from` successiva a `due_to` →
     200 con elenco vuoto;
   - filtri combinati;
   - senza filtri l'elenco è quello di oggi (stessi id, stesso ordine);
   - `quote_status` presente in `index`, `show`, `store` e `update`, uguale allo stato della
     trattativa;
   - lo scoping resta: con `?quote_id=` un task di un collega sulla stessa trattativa non compare.
2. In `index()`:
   - validazione con regole passate da variabile, come in `QuoteController`:
     `status` → `['sometimes', Rule::in([Task::STATUS_TODO, Task::STATUS_COMPLETED])]`,
     `quote_id` → `['sometimes', 'integer']`,
     `due_from`/`due_to` → `['sometimes', 'date_format:Y-m-d']` (stessa convenzione di
     `StoryController::validatedDateFilter`, senza `after_or_equal`);
   - filtri sulla query già costruita con `forUser`, prima dell'ordinamento:
     `where('status', …)`, `where('quote_id', …)`, `whereDate('due_date', '>=', due_from)`,
     `whereDate('due_date', '<=', due_to)`;
   - un attributo `#[QueryParameter]` per ciascuno dei quattro parametri.
3. In `formatTask()`: `'quote_status' => $task->quote?->status,` subito dopo `quote_title`.
4. Docblock: aggiungere `quote_status: string|null` ai quattro `@response` (`index`, `show`,
   `store`, `update`); nel docblock di `index()` aggiungere i filtri e l'avvertenza che tutti
   valgono solo sui task dell'utente (assegnatario o creatore) — `?quote_id=` non restituisce i task
   dei colleghi — e che le trattative senza task attivi si chiedono a
   `GET /api/quotes?without_active_tasks=1`.
5. Eseguire `--filter=TaskApiTest`: verde.

## Task 4 — Lens Nova «Trattative senza task attivi»

**File:** nuovo `app/Nova/Lenses/QuotesWithoutActiveTasks.php`, `app/Nova/Quote.php`,
`app/Nova/ArchivedQuotes.php`, `app/Nova/QuoteNoFilter.php`, `lang/it.json`, `lang/en.json`,
nuovo `tests/Feature/QuotesWithoutActiveTasksLensTest.php`

1. Test (con `Nova::resourcesIn(app_path('Nova'))` in `setUp()` se i campi `BelongsTo` lo
   richiedono, come in `StoryChildFieldTest`):
   - `Nova\Quote::lenses()` contiene una `QuotesWithoutActiveTasks`;
   - `ArchivedQuotes::lenses()` e `QuoteNoFilter::lenses()` restituiscono `[]`;
   - la query della Lens restituisce le stesse trattative di `Quote::openWithoutActiveTasks()` sugli
     stessi dati del Task 1.
2. Lens, sul modello di `StoriesByQuarter`:
   - `query()`: `$request->withOrdering($request->withFilters($query->openWithoutActiveTasks()->with('customer')))`;
   - `fields()`: `ID`, titolo (`Text`), cliente (`BelongsTo` verso `Customer`, o `Text` sul nome),
     stato mostrato con `QuoteStatus::tryFrom(...)->label()` come in `Nova\Quote` (righe 142-145);
   - `name()`: `__('Quotes without active tasks')`; `uriKey()`: `quotes-without-active-tasks`.
3. Registrazione: `Nova\Quote::lenses()` restituisce `[new QuotesWithoutActiveTasks]`;
   `ArchivedQuotes` e `QuoteNoFilter` ridefiniscono `lenses()` restituendo `[]`.
4. Traduzioni: `"Quotes without active tasks"` → `"Trattative senza task attivi"` in `lang/it.json`,
   e la stessa chiave in `lang/en.json`.
5. Eseguire `--filter=QuotesWithoutActiveTasksLensTest`: verde.
6. Verifica a mano in Nova (locale): la Lens compare nel menu a tendina dell'elenco Quote, non in
   «Preventivi archiviati»; la colonna cliente non fa una query per riga.

## Task 5 — Suite completa

1. `docker exec php81_orchestrator php artisan test` sul DB di test. `StoryChildFieldTest` è
   flaky noto (vedi `docs/howto/eseguire-i-test.md`): se fallisce, verificare che fallisca anche a
   codice invariato prima di considerarlo estraneo.
2. Controllare `/docs/api` (Scramble) in locale: `GET /quotes` mostra `without_active_tasks`,
   `GET /tasks` mostra i quattro filtri e `quote_status`.

## Task 6 — Chiusura

1. Review gate di `wm-plan` (riepilogo del diff da subagente isolato, poi dialogo).
2. `notes.md`: deviazioni, bug, decisioni.
3. Knowledge:
   - `docs/knowledge/quote-api-e-pdf.md`, sezione API Quote: il parametro `without_active_tasks` e
     la Lens, con la definizione unica sul model;
   - `docs/knowledge/task-e-quote.md`: filtri e `quote_status` di `/api/tasks`, avvertenza sullo
     scoping `forUser`; estendere la frase alle righe 59-60 sui campi di ogni Task.
4. Commit proposti (dopo il sì della dev):
   - `feat(oc:8729): trattative aperte senza task attivi in GET /api/quotes e Lens Nova`
   - `feat(oc:8729): filtri e quote_status su GET /api/tasks`
   - `docs(oc:8729): knowledge e note`
5. Aggiornamento del ticket su Orchestrator (stato, note dev in `prepend`, risposta al cliente).
