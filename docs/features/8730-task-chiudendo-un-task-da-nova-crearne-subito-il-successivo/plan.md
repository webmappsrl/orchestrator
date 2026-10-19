> Ticket: oc:8730

# Piano — Task: chiudendo un task da Nova, crearne subito il successivo

Riferimento: [overview.md](overview.md). Tutto nel repo principale `orchestrator`, nessun submodule.
Il branch contiene già oc:8722 (rebase su `develop` del 14/10/2026).

Le chiavi di traduzione seguono la convenzione del repo: chiave in inglese, traduzione in
`lang/it.json` (lingua di default, `config/app.php:98`) e in `lang/en.json`.

I test girano nel container, sul DB `orchestrator_test` (vedi
[docs/howto/eseguire-i-test.md](../../howto/eseguire-i-test.md)):

```bash
docker exec php81_orchestrator php artisan test --filter=ToggleTaskCompletedActionTest
```

---

## Task 1 — Test dell'action, scritti prima del codice

**File:** `tests/Feature/ToggleTaskCompletedActionTest.php` (nuovo), `use DatabaseTransactions`.

Scenari (Requisiti dell'overview, sezione «Test»):

1. chiusura con task successivo: il task passa a `completed`; nasce un Task sulla stessa Quote con
   titolo, scadenza, note e assegnatario inviati; `creator_id` = utente che esegue l'action;
2. assegnatario diverso dal default: il nuovo task ha l'assegnatario scelto, non l'owner;
3. campo Assegnatario precompilato:
   - con una trattativa che ha un owner con ruolo che accede ai task → default = owner;
   - con una trattativa senza owner → default = utente che chiude;
   (si legge il `value` del campo `assignee` in `fields()` con `$action->resource` = task aperto)
4. chiusura senza titolo, con scadenza, assegnatario e note compilati: task chiuso, nessun task nuovo;
5. titolo senza scadenza, e titolo senza assegnatario: errore di validazione, task ancora aperto,
   nessun task nuovo;
6. riapertura di un task chiuso: `fields()` vuoto, task in `todo`, `completed_at` null, nessun task
   nuovo;
7. più task inviati insieme all'endpoint: risposta di errore, nessun task modificato;
8. **esecuzione come la fa Nova:** `POST /nova-api/tasks/action?action=<uriKey>` con utente
   autenticato (`actingAs`), `resources=<id>`, titolo, scadenza, assegnatario. Verifica che il task
   si chiuda e il successivo nasca. È il test che intercetta `uriKey` dinamico e campi spariti
   all'esecuzione (Rischi dell'overview).

Per il test 8 l'utente deve passare il gate di accesso a Nova: usare un utente con ruolo Admin o
Developer, come gli altri test Nova del repo (`tests/Feature/TaskNovaResourceTest.php`).

Eseguire: i test devono fallire (l'action non ha ancora campi né `uriKey`).

## Task 2 — Action `ToggleTaskCompleted`

> ⚠️ L'implementazione ha deviato da questo task: [notes.md](notes.md#task-2-campo-note-riusato-dalla-risorsa)

**File:** `app/Nova/Actions/ToggleTaskCompleted.php`.

- `uriKey()` fisso: `'toggle-task-completed'`.
- `name()` dinamico: se `$this->resource` è un Task chiuso → `__('Reopen')`; altrimenti
  `__('Mark as completed')`. Senza task (esecuzione) il nome non conta, perché l'action si ritrova
  per `uriKey`.
- `fields(NovaRequest $request)`:
  - se `$this->resource` è un Task **chiuso** → `[]`;
  - altrimenti (task aperto, o nessun task all'esecuzione) →
    - `Heading` con `__('Next task (optional)')`;
    - `Text::make(__('Task'), 'title')->rules('nullable', 'max:255')`;
    - `DateTime::make(__('Due date'), 'due_date')->rules('nullable', 'required_with:title')`;
    - `BelongsTo::make(__('Assignee'), 'assignee', \App\Nova\User::class)->searchable()->nullable()`
      `->rules('nullable', 'required_with:title')`
      `->relatableQueryUsing(fn ($request, $query) => $query->withTaskAccess())`
      `->default(...)`: solo se `$this->resource` è un Task, `Task::defaultAssigneeId($task->quote, $request->user())`;
      senza task `null`;
    - `Tiptap::make(__('Notes'), 'notes')`, con la stessa configurazione di `notesField()` in
      `app/Nova/Task.php:161`.
- `handle(ActionFields $fields, Collection $models)`:
  - se `$models->count() !== 1` → `return Action::danger(__('Only one task at a time can be closed or reopened.'))`,
    nessuna modifica;
  - `$task = $models->first()`;
  - task chiuso → riapre come oggi (`status = todo`, `save()`), campi ignorati;
  - task aperto → `DB::transaction`: `status = completed`, `save()`; se `$fields->title` è
    compilato, crea `Task` con `quote_id` del task chiuso, `title`, `due_date`, `assignee_id`
    (id dal campo BelongsTo), `notes`. `creator_id` lo imposta l'hook `creating`.
- `authorizedToRun()` invariato.

Eseguire i test del Task 1: devono passare tutti. `sole()` (Task 3) agisce solo sull'interfaccia e
nessun test lo controlla; il rifiuto di più task è in `handle()`.

## Task 3 — Registrazione con `sole()`

**File:** `app/Nova/Task.php`, metodo `actions()` (riga 249 su `develop`).

`(new ToggleTaskCompleted())->showInline()` → `(new ToggleTaskCompleted())->sole()`.
`sole()` imposta già `showInline()` e `showOnDetail()` (`vendor/laravel/nova/src/Actions/Action.php:490-496`).

Eseguire tutti i test del Task 1, poi la suite completa:

```bash
docker exec php81_orchestrator php artisan test
```

## Task 4 — Traduzioni

**File:** `lang/it.json`, `lang/en.json`.

| Chiave | it | en |
|---|---|---|
| `Mark as completed` | Segna come completato | Mark as completed |
| `Reopen` | Riapri | Reopen |
| `Next task (optional)` | Task successivo (facoltativo) | Next task (optional) |
| `Only one task at a time can be closed or reopened.` | Si può chiudere o riaprire un solo task per volta. | Only one task at a time can be closed or reopened. |

`Task`, `Due date`, `Assignee` esistono già in entrambi i file; `Notes` esiste solo in `it.json` e in
inglese la chiave coincide con il testo. Verificare che il JSON resti valido (`php -r 'json_decode(file_get_contents("lang/it.json"), flags: JSON_THROW_ON_ERROR);'`).

## Task 5 — Verifica manuale in Nova (it e en)

Dalla riga della lista Task, dal dettaglio del task e dalla tab «Task» della trattativa:

- task aperto → «Segna come completato», campi Titolo, Scadenza, Assegnatario (già compilato con
  l'owner o con chi chiude), Note;
- task chiuso → «Riapri», nessun campo;
- con più task selezionati, l'action non compare fra le azioni in blocco;
- chiusura con titolo → nuovo task nella tab «Task» della trattativa; chiusura senza titolo → solo
  chiusura.

## Task 6 — Note e pagina di conoscenza

- `docs/features/8730-…/notes.md`: deviazioni, decisioni, follow-up (fra questi: le query in più
  sulla lista per il default dell'Assegnatario, lasciate fuori dal ticket dalla dev il 14/10/2026).
- `docs/knowledge/task-e-quote.md`: chiusura con task successivo da Nova, un task per volta, `uriKey`
  fisso e campi restituiti senza task (il motivo è nei Rischi dell'overview).

## Commit (istruzioni per la dev, nessun commit automatico)

1. `docs(oc:8730): overview aggiornata dopo la review e piano` — overview, plan
2. `feat(oc:8730): chiusura task con task successivo da Nova` — action, `app/Nova/Task.php`, lang,
   test
3. `docs(oc:8730): note e pagina di conoscenza sui task` — notes, `docs/knowledge/task-e-quote.md`

Dopo il rebase del 14/10/2026 il push del branch va fatto con `--force-with-lease`.

---

# Correzione dopo la review del 19/10/2026

Riferimento: sezione «Correzione dopo la review del 19/10/2026» dell'overview e ticket, sezione
«Primo ciclo — da fare». Perimetro: solo quello che chiede la review. Tutto nel repo principale.

```bash
docker exec php81_orchestrator php artisan test --filter=ToggleTaskCompleted
```

## Task C1 — Test, prima del codice

**File:** `tests/Feature/ToggleTaskCompletedClosedQuoteTest.php` (del reviewer, commit `3977d68`).

I due test esistenti **non si modificano**. Si aggiungono nello stesso file, riusando
`makeOpenTaskOnClosedQuote()`:

1. `test_su_trattativa_chiusa_il_popup_non_ha_campi`: `$action = new ToggleTaskCompleted()`,
   `$action->resource = $task`, `assertSame([], $action->fields(NovaRequest::create('/')))` (stesso
   modello di `ToggleTaskCompletedActionTest.php:166-176`). Il nome resta «Segna come completato».
2. `test_su_trattativa_chiusa_il_titolo_lascia_il_task_aperto`: stessa richiesta del primo test del
   reviewer; verifica che il task sia ancora `todo` e che nessun task sia nato.

Eseguire: falliscono il primo test del reviewer e i due nuovi; il secondo del reviewer passa.

## Task C2 — Action

**File:** `app/Nova/Actions/ToggleTaskCompleted.php`.

- `handle()`: dopo il ramo della riapertura e **prima** di `DB::transaction`, se `$fields->title`
  è compilato e `Gate::denies('create', [Task::class, $task->quote])` →
  `return Action::danger(__('The quote is closed: the next task cannot be created.'))`. Nessuna
  modifica: il task resta aperto.
- `fields()`: restituisce `[]` anche quando `$this->resource` è un Task aperto la cui Quote non
  ammette nuovi task (`Gate::denies('create', [Task::class, $this->resource->quote])`). Senza task
  (esecuzione) i campi restano quelli di oggi. Nessun confronto con gli stati della Quote
  nell'action: la regola resta in `TaskPolicy::create()`.
- Docblock della classe: una riga sul blocco per le trattative chiuse (oc:8730, review del
  19/10/2026).

`TaskPolicy::before()` restituisce solo `false` per i ruoli senza accesso ai task
(`app/Policies/TaskPolicy.php:25-30`), quindi per un Admin decide `create()`.

Eseguire i test dell'action: tutti verdi, compresi i 9 di `ToggleTaskCompletedActionTest`.

## Task C3 — Traduzioni

| Chiave | it | en |
|---|---|---|
| `The quote is closed: the next task cannot be created.` | Il preventivo è chiuso: non si può creare il task successivo. | The quote is closed: the next task cannot be created. |

Verificare che i due JSON restino validi, poi la suite completa:

```bash
docker exec php81_orchestrator php artisan test
```

## Task C4 — Note e pagina di conoscenza

- `notes.md`: decisioni del ciclo (blocco totale con il titolo; popup vuoto senza riga di
  spiegazione, per restare nel perimetro della review).
- `docs/knowledge/task-e-quote.md`: «Il task successivo nasce anche su una Quote chiusa» passa fra
  le scelte superate, con il motivo; lo stato attuale dice che l'action applica
  `TaskPolicy::create()` con la Quote.

## Task C5 — Verifica manuale in Nova

Su un task aperto di una trattativa «Chiuso Vinto»: «Segna come completato» senza campi, il task si
chiude. Su una trattativa aperta: i campi ci sono come prima.

## Commit (istruzioni per la dev, nessun commit automatico)

1. `fix(oc:8730): nessun task successivo su una trattativa chiusa` — action, lang, test
2. `docs(oc:8730): overview, piano e note della correzione dopo la review` — overview, plan, notes,
   `docs/knowledge/task-e-quote.md`
