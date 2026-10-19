> Ticket: oc:8729

# Trattative: vista e API di quelle aperte senza task attivi

## Cosa cambia

Una trattativa aperta deve sempre avere un task attivo con una scadenza: se non lo ha, nessuno la sta
seguendo. Dopo questa feature:

- **API** — `GET /api/tasks` accetta filtri per stato, scadenza e trattativa, e ogni task riporta lo
  stato della trattativa a cui appartiene. Il client (la skill Claude) ricava da sé le trattative aperte
  senza task attivi, confrontando le trattative aperte (`GET /api/quotes`, già esistente) con i task
  attivi (`GET /api/tasks?status=todo`): una trattativa senza nessun task non compare fra i task (vedi
  Rischi).
- **Nova** — una Lens sulle Quote, «Trattative senza task attivi», elenca le trattative aperte che non
  hanno nessun task in stato `todo`.
- Nessuna trattativa viene chiusa in automatico: la chiusura resta manuale.

Definizioni, ricavate dal codice:

- **trattativa aperta**: ogni stato tranne `closed won` e `closed lost` — quindi `new`, `to present`,
  `presented`, `on hold` («In attesa di esito», oc:8723), `waiting for order`, `cold`
  (`app/Enums/QuoteStatus.php:7-14`). È la stessa esclusione che l'elenco Nova delle Quote applica già
  in `Nova\Quote::indexQuery`.
- **task attivo**: task in stato `todo` (`Task::STATUS_TODO`). La scadenza c'è sempre, perché
  `due_date` è obbligatoria (`create_tasks_table.php:16`): un task `todo` con scadenza passata conta
  come attivo.

### Perché la Lens e non un Filter

**Scelta: Lens.** Il ticket lascia aperte entrambe le strade («Lens o Filter Nova sulle Quote aperte
senza task attivi»); la scelta segue la richiesta del cliente: «Ci serve una **vista**, e un'API, con le trattative aperte
che non hanno task attivi» (customer_request di oc:8729). In Nova la vista è la Lens.

| | Lens (scelta) | Filter |
|---|---|---|
| Pro | È una pagina dedicata, si trova subito dal menu delle Quote; corrisponde alla «vista» chiesta | Meno codice; si combina col filtro per stato già presente; eredita l'esclusione delle chiuse da `indexQuery` |
| Contro | Classe nuova con colonne e query proprie; l'esclusione delle trattative chiuse va riscritta nella query della Lens, perché la Lens non passa da `indexQuery` | Meno visibile: bisogna ricordarsi di attivarlo |

## Perché

Il commerciale vuole accorgersi delle trattative che nessuno sta seguendo senza scorrerle tutte a mano.
Sull'API la decisione è della call del 30/09/2026 (proposta di Giuseppe, riportata nella description del
ticket): niente endpoint dedicato, ma un'API generica dei task con stato e trattativa di appartenenza, e
il filtro lo fa il client — «io ti torno tutte le task con l'informazione se aperta e chiusa e poi è
cloud che perché così ci puoi puoi fare più cose». Sulla chiusura: «non deve essere assolutamente chiusa
automaticamente» (call del 30/09/2026, citata nel ticket).

## Requisiti

- [ ] `GET /api/tasks` accetta il filtro `status` (`todo` o `completed`); un valore diverso risponde 422.
- [ ] `GET /api/tasks` accetta il filtro `quote_id`: restituisce solo i task di quella trattativa. Un
      valore non intero (es. `?quote_id=abc`) risponde 422: senza controllo, Postgres rifiuta il
      confronto con la colonna `bigint` e l'API risponderebbe 500.
- [ ] `GET /api/tasks` accetta i filtri di scadenza `due_from` e `due_to` (data `AAAA-MM-GG`, estremi
      inclusi, usabili anche uno solo). Il confronto è sulla sola data, perché `due_date` è un
      `dateTime`: `due_to=2026-10-20` include un task in scadenza il 20/10 alle 18:00. Una data non
      nel formato `AAAA-MM-GG` risponde 422, come per i filtri data di `GET /api/stories`
      (`StoryController::validatedDateFilter`); un intervallo con `due_from` successiva a `due_to`
      non è un errore e restituisce un elenco vuoto.
- [ ] I filtri sono opzionali e si combinano fra loro; senza filtri l'elenco dei task restituiti è
      identico a oggi (cambia solo il campo in più descritto sotto).
- [ ] Ogni task restituito dall'API ha in più il campo `quote_status`, lo stato della trattativa:
      vale per `index`, `show`, `store` e `update`, che passano tutti da `formatTask()`. `quote_id` c'è
      già. Nessun altro campo cambia.
- [ ] Restano invariati: nessuna paginazione, nessun `?include=`, ordinamento e `sort` come oggi, lo
      scoping `forUser` (task di cui l'utente è assegnatario o creatore) — quest'ultimo salvo esito
      della domanda aperta per il reviewer «Task di tutti o solo dell'utente».
- [ ] La documentazione Scramble descrive i nuovi parametri e il campo `quote_status` in tutti e
      quattro i metodi.
- [ ] Nuova Lens Nova sulle Quote, «Trattative senza task attivi»: trattative con stato diverso da
      `closed won`/`closed lost` e senza alcun task `todo`. Colonne: ID, titolo, cliente, stato.
- [ ] La Lens compare solo nella risorsa Quote: `ArchivedQuotes` (che estende `Nova\Quote` e
      altrimenti la erediterebbe) restituisce `[]` in `lenses()`, così l'archivio delle trattative
      chiuse non mostra una vista di trattative aperte.
- [ ] Il nome della Lens è tradotto in `lang/it.json` e `lang/en.json` (lingua di default del repo:
      `it`, `config/app.php:98`).
- [ ] Nessuna trattativa cambia stato da sola: né l'API né la Lens scrivono nulla.
- [ ] Test feature per i filtri e il nuovo campo in `tests/Feature/Api/TaskApiTest.php`, e per la query
      della Lens.

## Rischi

- **Trattative con zero task.** Una trattativa che non ha nessun task non compare in `GET /api/tasks`.
  Per trovarla il client deve leggere anche `GET /api/quotes?status[]=…` (gli stati aperti), che esiste
  già, e confrontare i due elenchi. È la conseguenza della decisione della call; qui si segnala soltanto.
- **Contratto esterno.** `/api/tasks` è consumato dalla skill Cowork e dal server MCP `orchestrator` di
  `claude-marketplace` (`docs/knowledge/task-e-quote.md:59-62`). Le modifiche sono solo aggiuntive
  (parametri opzionali, un campo in più), quindi non rompono chi lo usa oggi.

## Domande aperte per il reviewer

- **Task di tutti o solo dell'utente.** `GET /api/tasks` restituisce solo i task di cui chi chiede è
  assegnatario o creatore (`TaskController.php:30`, `Task::scopeForUser`, `Task.php:105-106`), mentre
  in call Giuseppe ha detto «io ti torno tutte le task». Esempio: su «Parco Alpi» Alessio crea un task
  per Marco; Giuseppe chiede alla skill le trattative senza task attivi, il task non gli arriva e
  «Parco Alpi» risulta non seguita, mentre la Lens (che guarda tutti i task) non la mostra. Proposta:
  lo scoping resta invariato in questo ticket, perché toglierlo cambia cosa vede chi usa già l'API.
  Al reviewer: confermare, oppure indicare se «tutte le task» va inteso anche come «di tutti gli
  utenti».

## Out of scope

- Chiusura automatica delle trattative senza task (esclusa esplicitamente dal ticket).
- Notifiche o avvisi sulle trattative senza task.
- Un endpoint dedicato alle «trattative senza task» (scartato in call a favore dell'API generica).
- Modifiche a `GET /api/quotes`.
- Modifiche allo scoping `forUser` di `/api/tasks`, salvo esito della domanda aperta per il reviewer
  «Task di tutti o solo dell'utente».
- Il Kanban delle trattative.

## Moduli toccati

Tutto nel repo principale `orchestrator` (nessun submodule):

- `app/Http/Controllers/Api/TaskController.php` — filtri in `index()`, `quote_status` in `formatTask()`,
  attributi Scramble
- `app/Nova/Lenses/QuotesWithoutActiveTasks.php` — nuova Lens
- `app/Nova/Quote.php` — registrazione della Lens in `lenses()`
- `app/Nova/ArchivedQuotes.php` — `lenses()` vuoto, per non ereditare la Lens
- `lang/it.json`, `lang/en.json` — nome della Lens
- `tests/Feature/Api/TaskApiTest.php` — test dei filtri e del nuovo campo
- `tests/Feature/` — test della Lens
- `docs/knowledge/task-e-quote.md` — aggiornamento a fine lavoro
