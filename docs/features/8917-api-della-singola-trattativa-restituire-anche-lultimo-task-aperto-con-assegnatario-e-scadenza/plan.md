> Ticket: oc:8917

# Piano — API della singola trattativa: ultimo task aperto, con assegnatario e scadenza

Tutto nel repo principale `orchestrator`, nessun submodule. Riferimento: [overview.md](overview.md).

Test sempre sul DB `orchestrator_test` (vedi `docs/howto/eseguire-i-test.md`), dentro il container
`php81_orchestrator`. Durante l'esecuzione nessun commit: i commit sono indicati al punto 7 e si
fanno solo dopo il review-gate.

## Task 1 — Test che falliscono, in `tests/Feature/Api/QuoteApiTest.php`

Si scrivono prima del codice, così il caso della relazione costruita male (Rischi dell'overview)
è coperto fin dall'inizio.

Helper privato `makeTask(Quote $quote, array $attributes = []): Task` con `Task::create()`, come
fa già `makeQuotesForActiveTasksFilter()` (le Task non hanno factory). Default: `title`,
`due_date = now()->addDay()`, `creator_id` e `assignee_id` di un utente creato al momento.

Casi, tutti su `GET /api/quotes/{id}?include=nextTask` salvo dove indicato:

1. **Task di un altro utente**: il task è creato e assegnato a un collega, la chiamata è fatta da
   un admin diverso → `nextTask.id` è quello del task, `assignee` è `{id, name, email}` del collega.
2. **Task scaduto**: `due_date` ieri → restituito, `due_date` in ISO 8601.
3. **Più task `todo`**: scadenze fra 5 giorni, fra 1 giorno e fra 10 giorni, creati in quest'ordine
   → vince quello fra 1 giorno, anche se non è né il primo né l'ultimo creato.
4. **Task `completed` con `due_date` precedente al `todo`**: `completed` scaduto 10 giorni fa,
   `todo` fra 2 giorni → vince il `todo`. È il test che fallisce se il filtro `status` sta fuori
   dalla closure di `ofMany`.
5. **Pareggio sulla `due_date`**: due `todo` con la stessa `due_date` → vince l'`id` più basso.
6. **Nessun task `todo`** (solo `completed`, oppure nessun task) → `assertJsonPath('nextTask', null)`
   e la chiave è presente (`assertArrayHasKey('nextTask', $response->json())`).
7. **Assegnatario `null`**: `assignee_id = null` → task restituito, `assignee: null`.
8. **Quote chiusa**: quote in `closed won` con un task `todo` → task restituito.
9. **Senza `include=nextTask`**: chiamata semplice e con `?include=customer` → la chiave
   `nextTask` non c'è, e nessuna query tocca la tabella `tasks` (`DB::listen` e controllo che
   nessuna SQL contenga `"tasks"`).
10. **Forma esatta**: le chiavi di `nextTask` sono esattamente `id, title, due_date, status,
    assignee` (`assertSame` sulle chiavi ordinate).

Esecuzione: i test devono fallire (chiave `nextTask` assente).

## Task 2 — Relazione `nextTask` in `app/Models/Quote.php`

Subito sotto `tasks()`:

```php
/**
 * Task `todo` di riferimento della quote (oc:8917): quello con la `due_date`
 * più vicina, scaduto compreso; a parità di `due_date` l'`id` più basso.
 * Stesso criterio di scopeWithNextTodoTask() (colonna `Due date` di Nova).
 * Non filtrata per utente: conta qualsiasi assegnatario o creatore.
 * Il filtro su `status` deve restare dentro la closure di ofMany: fuori,
 * il MIN(due_date) si calcolerebbe anche sui task completati.
 */
public function nextTask(): HasOne
{
    return $this->hasOne(Task::class)->ofMany(
        ['due_date' => 'min', 'id' => 'min'],
        fn ($query) => $query->where('status', Task::STATUS_TODO)
    );
}
```

Import `Illuminate\Database\Eloquent\Relations\HasOne`. `due_date` è `NOT NULL` nella migration
`2026_08_19_113137_create_tasks_table.php`, quindi il `MIN` non incontra valori nulli.

## Task 3 — `app/Http/Controllers/Api/QuoteController.php`

1. `ALLOWED_INCLUDES`: aggiungere `'nextTask'`.
2. `show()`: quando `nextTask` è in `$include`, caricare `nextTask.assignee` al posto di
   `nextTask`, per non fare una query in più sull'assegnatario. Le altre relazioni restano come oggi.
3. `formatQuote()`: dopo il blocco di `recurringProducts`,

   ```php
   if (in_array('nextTask', $include, true)) {
       $data['nextTask'] = $this->formatNextTask($quote->nextTask);
   }
   ```

   con un metodo privato `formatNextTask(?Task $task): ?array` che restituisce `null` oppure
   `{id, title, due_date, status, assignee}`, con `due_date` via
   `optional(...)->toIso8601String()` e `assignee` come `{id, name, email}` o `null`: stessi nomi
   e stessa forma di `TaskController::formatTask()`. La chiave si scrive anche quando il valore è
   `null` (a differenza di `customer`, vedi Requisiti).
4. Docblock di `show()`: aggiungere `nextTask` all'elenco dei nomi accettati da `?include=` e
   la frase «`nextTask` is present with value null when the quote has no todo task»; nel
   `@response` aggiungere
   `nextTask?: array{id: int, title: string, due_date: string, status: string, assignee: array{id: int, name: string, email: string}|null}|null`.
5. `#[QueryParameter('include')]`: aggiungere `nextTask` all'elenco, con la stessa precisazione
   sul `null`.

Esecuzione: i test del Task 1 passano, e passa tutto `QuoteApiTest`.

## Task 4 — `tests/Feature/Api/QuoteApiDocsTest.php`

Nuovo test `test_quotes_show_response_schema_documents_next_task`: nello schema 200 di
`GET /quotes/{quote}` esiste `properties.nextTask`, e le sue proprietà contengono `id`, `title`,
`due_date`, `status`, `assignee`. Se Scramble non legge il tipo annidato `|null`, si aggiusta il
docblock del Task 3, non il test.

Esecuzione: `QuoteApiDocsTest` passa.

## Task 5 — Suite completa

`php artisan test` sul DB `orchestrator_test`. Nessuna regressione su `TaskApiTest`, sulle Lens e
sulle Resource Nova delle quote (lo scope `scopeWithNextTodoTask` non viene toccato).

## Task 6 — `docs/knowledge/quote-api-e-pdf.md`

Nella sezione «API Quote» di `Come funziona oggi`: `include=nextTask`, criterio della scadenza più
vicina e perché (quando sbaglia l'errore si vede), chiave `null` presente quando non c'è un task
`todo` a differenza di `customer`, nessun filtro per utente. In `Perché così` la scelta
`include` contro sempre presente, con il motivo di `formatQuote()` condivisa. Il testo passa da
`wm-context-guard` prima della scrittura. La riga nell'indice del `CLAUDE.md` non cambia.

## Task 7 — Commit (solo dopo il review-gate e il sì esplicito del dev)

Istruzioni testuali, non da eseguire in autonomia:

- `feat(oc:8917): ultimo task aperto nella singola quote con include=nextTask` — modello,
  controller, test.
- `docs(oc:8917): piano, note e pagina di conoscenza` — `plan.md`, `notes.md`,
  `docs/knowledge/quote-api-e-pdf.md`.

La PR #276 esiste già, verso `develop`: i commit si aggiungono a quella.

## Per la chiusura del ticket

Nella risposta al cliente: criterio scelto e perché, `include=nextTask` scritto esattamente così,
chiave assente = `include` non applicato, non «nessun task».
