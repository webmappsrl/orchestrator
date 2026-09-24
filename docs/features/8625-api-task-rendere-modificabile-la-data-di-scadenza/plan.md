> Ticket: oc:8625

# Piano — API task: rendere modificabile la data di scadenza

Tutto nel repo principale (`orchestrator`), nessun submodule. Branch:
`feature/oc-8625-api-task-rendere-modificabile-la-data-di-scadenza`.

Test sempre con `docker exec php81_orchestrator php artisan test --filter=TaskApiTest`
(DB `orchestrator_test`, mai `orchestrator`).

## Task 1 — Test che falliscono

File: `tests/Feature/Api/TaskApiTest.php`, in coda ai test PATCH esistenti, con gli helper
`loginAs()`, `makeQuote()`, `makeTask()`.

1. `creator_puo_spostare_la_scadenza` — creator `Developer`, PATCH
   `['due_date' => '2026-10-15T10:30:00+02:00']` → 200; `due_date` nella risposta e
   `$task->fresh()->due_date` corrispondono alla nuova data.
2. `due_date_non_valida_ritorna_422` — PATCH con `'domani'` e poi con `null` → 422 in entrambi i
   casi; `$task->fresh()->due_date` invariata.
3. `non_creator_puo_spostare_la_scadenza` — task con `creator_id` di un altro utente, login
   `Admin`, PATCH con sola `due_date` → 200 e data salvata.
4. `payload_misto_status_e_due_date_da_non_creator_non_salva_niente` — come
   `payload_misto_da_non_creator_fallisce_tutto_o_niente`, con `status` + `due_date` → 403;
   `status` e `due_date` invariati.

Eseguire: i test 1 e 3 falliscono (la data non cambia), il test 2 fallisce (oggi risponde 200),
il test 4 passa già e protegge dal rischio che il controllo venga spostato dopo il salvataggio.

## Task 2 — Validazione

File: `app/Http/Requests/Api/TaskApiRequest.php`, ramo non-POST di `rules()`:

```php
'due_date' => ['sometimes', 'date'],
```

## Task 3 — Controller

File: `app/Http/Controllers/Api/TaskController.php`, metodo `update()`:

- autorizzazioni invariate e sempre prima di qualsiasi scrittura (`update`, poi `updateStatus`
  se c'è `status`);
- assegnare `status` e `due_date` se presenti e fare **un solo** `save()` se almeno uno dei due
  è stato assegnato;
- `notes` resta dopo, con `appendNote()`;
- docblock: da "limited to two fields" a tre campi; `due_date` aperta a chiunque abbia un ruolo
  abilitato, come `notes`.

Eseguire `TaskApiTest`: tutti verdi, compresi i 7 test PATCH esistenti.

## Task 4 — Pagina di conoscenza

File: `docs/knowledge/task-e-quote.md`, voce "Autorizzazione differenziata per campo sul
`PATCH /api/tasks/{task}`": aggiungere `due_date`, modificabile da chiunque abbia un ruolo
abilitato (`TaskPolicy::update()`), con la stessa regola di validazione del POST (oc:8625).

## Task 5 — Verifica finale

- `docker exec php81_orchestrator php artisan test --filter=TaskApiTest`
- `docker exec php81_orchestrator php artisan test --filter=TaskPolicyTest`

## Commit (da eseguire solo dopo l'approvazione della review)

- `feat(oc:8625): la PATCH dei task accetta due_date`
- `docs(oc:8625): overview, piano, note e pagina di conoscenza`
