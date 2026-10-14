> Ticket: oc:8722

# Notes — Task: assegnatario indipendente dall'owner della trattativa

## Deviazioni dal piano

Piccole, nessuna cambia il comportamento previsto dall'overview:
- Task 9: nel form aperto dal tab Task della Quote il campo Assegnatario è `onlyOnForms()`, perché
  la lista del tab resta senza colonna (oc:8402).
- Task 11: il nome del filtro usa la chiave `Which tasks` («Quali task»), non `Show` (vedi
  Decisioni).
- Task 11, dopo la review: `MyTask` ha `$globallySearchable = false` e il nome singolare «Task».
- Task 5, dopo la review: `User::delete()` gira in una transazione, così la riassegnazione dei task
  si annulla se la DELETE fallisce.

## Bug trovati

Dalla review del 14/10/2026 (`wm-review-ticket`), prima del commit:
- `User::hasRole()` andava in errore con `roles` NULL: il default dell'assegnatario lo chiama
  sull'owner della Quote, che può essere un utente senza ruoli. Ora un utente senza ruoli non ha
  nessun ruolo.
- La cancellazione di un utente owner di una trattativa falliva per la FK di `quotes.user_id`
  dopo che l'hook `deleting` aveva già riassegnato i suoi task. Nova non apre transazioni quando
  cancella (`Laravel\Nova\Jobs\DeleteResources`).

## Decisioni

**Pianificazione del 13/10/2026**
- Tag associato al ticket: `orchestrator` (id 590).
- Il Google Doc della call del 30/09 (fonte del tag `[CALL][orchestrator]proposte di evoluzione
  area commerciale`) e il suo notebook non sono condivisi con l'account della dev: le citazioni del
  30/09 vengono dal ticket e dalla descrizione del tag.
- Overview rivista con `wm-review-ticket` e mandata al reviewer con 4 domande aperte (PR #266).

**Review del 14/10/2026 (peppedeka, PR #266 e description del ticket)** — sostituisce le proposte
del 13/10 su default e migrazione:
- assegnatario di default = owner della trattativa, altrimenti chi crea il task (non più il
  creatore in ogni caso);
- migrazione dei task esistenti con la stessa regola (non più «parziale, solo trattative senza
  owner»);
- nuova vista «I miei task» con filtro «Assegnati a me» / «Creati da me», default «Assegnati a me»;
- campo Assegnatario precompilato nel form «Crea Task» (aggiunta del reviewer, ripresa da oc:8730).

**Ripresa della pianificazione del 14/10/2026 (decisioni della dev)**
- Assegnatario obbligatorio in Nova e nell'API, senza ripiego sull'owner calcolato alla lettura.
- Alla cancellazione di un utente i suoi task si riassegnano con la regola del default (owner,
  altrimenti creatore). Caso quasi teorico: oggi non si cancella chi è owner di una trattativa o
  creatore di una Story.
- `GET /api/tasks` senza parametri nuovi: restituisce assegnati + creati.
- Nessun tag di contenuto creato (`task`, `crm`, `trattative` non esistono; la dev ha scelto di
  non crearli).
- Il piano è stato scritto prima della stima, perché `wm-estimate` lo legge;
  `superpowers:writing-plans` non era disponibile.

**Esecuzione del 14/10/2026**
- Nel form aperto dal tab Task della Quote il campo Assegnatario è `onlyOnForms()`: la lista del
  tab resta senza colonna (oc:8402), come da overview.
- Il nome del filtro della vista «I miei task» usa la chiave `Which tasks` («Quali task») e non
  `Show`: una chiave così generica avrebbe tradotto anche altri testi di Nova.
- Nei test `makeQuoteWithOwner()` crea l'owner con ruolo Manager: `UserFactory` assegna 2 ruoli
  a caso, e un owner senza ruolo ammesso fa cadere il default sull'utente che agisce, rendendo i
  test instabili.
- I task creati nei test API durante una sessione autenticata prendono come default l'utente del
  token se la Quote non ha owner: alcuni test esistenti ora impostano `assignee_id` esplicito.

## Verifica manuale in Nova (14/10/2026, ambiente locale)

Eseguita nel browser dopo le migration sul DB locale; tutte superate.

| # | Prova | Esito |
|---|---|---|
| 1 | «Crea Task» dal tab Task di una trattativa con owner | Assegnatario precompilato con l'owner |
| 2 | «Crea Task» dall'elenco Task generale: scelta della trattativa, poi cambio con un'altra | L'Assegnatario si compila con l'owner della prima e si aggiorna con l'owner della seconda |
| 3 | Modifica di un task con assegnatario scelto a mano (diverso dall'owner): apertura del form e salvataggio di un altro campo | L'assegnatario resta quello scelto, nel form e nel DB |
| 4 | Vista «I miei task» | Parte con «Assegnati a me»; «Creati da me» mostra anche i task creati e assegnati ad altri; pulsante «Crea Task» |
| 5 | Lista del tab Task di una trattativa | Nessuna colonna Assegnatario (oc:8402 invariato) |

## Follow-up

- **Prima del deploy**, conteggio in produzione (sola lettura) di come si distribuisce la
  migrazione:
  ```sql
  SELECT count(*) FILTER (WHERE q.user_id IS NOT NULL)                         AS all_owner,
         count(*) FILTER (WHERE q.user_id IS NULL AND t.creator_id IS NOT NULL) AS al_creatore,
         count(*) FILTER (WHERE q.user_id IS NULL AND t.creator_id IS NULL)     AS vuoti
  FROM tasks t JOIN quotes q ON q.id = t.quote_id;
  ```
- Allineare il server MCP `orchestrator` in `claude-marketplace`
  (`plugins/wm-skills/mcp/internal/tools/tasks.go:25`): descrizione di `status` e parametro
  `assignee_id`.
- oc:8730 riusa `Task::defaultAssigneeId()` per il popup di chiusura.
