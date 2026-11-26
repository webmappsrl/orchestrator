> Ticket: oc:8917

# Notes — API della singola trattativa: ultimo task aperto, con assegnatario e scadenza

## Deviazioni dal piano

Nessuna deviazione nel codice. Due differenze rispetto al testo del piano:

- **Task 1, caso 7 (assegnatario `null`)**: il task si crea con un assegnatario e poi si aggiorna
  con `assignee_id = null`. Creandolo direttamente con `null`, l'hook `creating` di `Task` lo
  riempie con `defaultAssigneeId()` e il caso non verrebbe coperto.
- **Task 6 (pagina di conoscenza)**: `docs/knowledge/quote-api-e-pdf.md` non ha le sezioni «Come
  funziona oggi» e «Perché così» citate nel piano, ma «Stato attuale» e «Come ci siamo arrivati».
  Il testo è in una sottosezione nuova di «Stato attuale», «Task di riferimento della singola
  trattativa (oc:8917)», con la scelta `include` contro sempre presente fra i punti; il criterio
  «creato per ultimo» scartato è in «Come ci siamo arrivati».

## Bug trovati

Nessuno.

## Decisioni

- **Overview corretta dopo la review del CTO sulla PR #276** (10/11/2026): chiusa la domanda
  `include` o sempre presente con `include=nextTask`, motivato il criterio della scadenza più
  vicina con i dati di produzione, sostituiti i «non verificato» dei Rischi con i numeri.
- **Stima: 2h**, valore scelto dal dev al posto delle 1,68h proposte (0,33h misurate in questa
  sessione + 1,35h stimate). Il campo `hours` del ticket era già a 5,08h prima del codice: misura
  tutto il tempo in `progress` nei giorni lavorativi, comprese attese e pause, e non è stato
  aggiunto alla stima.
- **Controprova sul test dei task completati**: spostando il filtro `status` fuori dalla closure
  di `ofMany`, fallisce solo `next_task_ignora_i_task_completati_anche_se_scadono_prima`. Il test
  copre quindi davvero la costruzione sbagliata descritta nei Rischi.
- **Test rafforzati dopo la review** (10/11/2026): il test senza `include` verifica che
  `DB::listen` abbia registrato almeno una query, il test sulla quote chiusa copre `closed won` e
  `closed lost`, il parametro di `makeTask()` si chiama `$overrides` come in `TaskApiTest`.
- **`docker compose up -d` non riparte in locale**: prova a ricostruire l'immagine
  `postgis/postgis:17-3.5` e fallisce con «no match for platform in manifest». I container già
  esistenti si avviano con `docker start postgres_orchestrator redis_orchestrator php81_orchestrator`.

## Follow-up

- Aggiornare la skill commerciale perché chiami `GET /api/quotes/{id}?include=nextTask` e tratti la
  chiave assente come «`include` non applicato», non come «nessun task» (fuori scope, overview).
- `include=nextTask` anche in `GET /api/quotes` (`index`), se una chiamata per trattativa diventa un
  problema: aggiunta compatibile che riusa `Quote::nextTask()`. In quel caso va caricato
  `nextTask.assignee` anche in `index`: `formatQuote()` legge `nextTask` senza controllare che sia
  già caricato, e senza eager load farebbe due query per quote.
- **Criterio scritto in due modi** (review del 10/11/2026): `Quote::nextTask()` per l'API e
  `scopeWithNextTodoTask()` per la colonna `Due date` di Nova (`app/Nova/Quote.php:188`), che non
  ha lo spareggio sull'`id`. Far leggere a Nova `$this->nextTask` e togliere lo scope li
  unificherebbe; è fuori scope perché tocca le Resource Nova.
- **`formatNextTask()` ricopia la forma di `TaskController::formatTask()`** (review del
  10/11/2026): restano allineati solo per il commento. Un formatter condiviso del Task li
  terrebbe insieme; è fuori scope perché tocca `TaskController`.
