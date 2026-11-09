> Ticket: oc:8917

# API della singola trattativa: restituire anche l'ultimo task aperto, con assegnatario e scadenza

## Cosa cambia

`GET /api/quotes/{id}` può restituire, insieme ai dati della quote, il task aperto (`status =
'todo'`) di riferimento della trattativa: quello con la `due_date` più vicina, scaduto compreso,
qualunque sia il suo assegnatario o creatore. Del task arrivano `id`, `title`, `due_date`,
`status` e `assignee` (`{id, name, email}` oppure `null`).

Il dato si chiede con `?include=nextTask`, accanto a `customer`, `products` e
`recurringProducts`. Quando la quote non ha task `todo`, la chiave c'è e vale `null`.

## Perché

La skill con cui il team commerciale fa il punto sulla pipeline deve sapere, per ogni trattativa,
se qualcuno la sta seguendo, chi, ed entro quando (ticket, `customer_request`). Oggi la risposta di
`GET /api/quotes/{id}` non ha alcun riferimento ai task, e l'unica alternativa, `GET /api/tasks`,
restituisce solo i task di cui l'utente autenticato è assegnatario o creatore
(`TaskController.php:49`, `Task::scopeForUser`). Il 3 novembre, su 27 trattative attive, cinque
sono risultate senza nessuno che le seguisse mentre erano seguite da un collega, fra cui la
seconda per valore, da 44.200 euro (ticket, `customer_request`).

`GET /api/quotes?without_active_tasks=1` dice già **se** una trattativa ha un task aperto: manca
**quale**, **di chi** e **per quando**.

## Requisiti

- [ ] `GET /api/quotes/{id}?include=nextTask` aggiunge alla risposta la chiave `nextTask`.
- [ ] Il task restituito è quello con `status = 'todo'` e `due_date` più vicina, cioè il primo in
      ordine crescente di `due_date`, scaduto compreso: è lo stesso criterio di
      `Quote::scopeWithNextTodoTask`, usato dalle Resource Nova `Quote` e `QuoteNoFilter`
      (decisione del dev, domanda 1). A parità di `due_date` vince l'`id` più basso, così la
      risposta è deterministica.
      **Questo si discosta dalla lettera del ticket**, che parla di «ultimo task aperto» e di «il
      più recente», cioè del task creato per ultimo. Si è scelta la scadenza più vicina perché porta
      in evidenza i task scaduti, che il ticket dice essere «proprio quello che si ha bisogno di
      vedere», e perché la data restituita coincide con quella della colonna `Due date`
      dell'`index` Nova delle quote (`app/Nova/Quote.php:187-190`). Nova mostra solo la data, non
      il titolo né l'assegnatario, e solo nell'`index`, che esclude le quote `closed won` e
      `closed lost`. Le due letture danno lo stesso risultato quando la quote ha un solo task
      `todo`; divergono solo quando ne ha più d'uno. Il nome `nextTask` segue lo scope esistente,
      non il lessico del ticket.
- [ ] Il task si cerca fra **tutti** i task della quote, senza `scopeForUser`: conta qualsiasi
      assegnatario o creatore (ticket, «a prescindere da chi sia l'assegnatario»).
- [ ] Forma di `nextTask`: `{id, title, due_date, status, assignee}`. `due_date` è in ISO 8601,
      `assignee` è `{id, name, email}` oppure `null`: stessi nomi e stessa forma di
      `TaskController::formatTask()` (decisione del dev, domanda 3).
- [ ] Un task `todo` senza assegnatario viene restituito con `assignee: null`, non saltato: segnala
      un task che qualcuno deve prendere in carico (decisione del dev, domanda 4).
- [ ] Il task si restituisce qualunque sia lo `status` della quote, anche `closed won` e
      `closed lost` (decisione del dev, domanda 5).
- [ ] Quando la quote non ha task `todo`, con `include=nextTask` la risposta contiene
      `"nextTask": null` (ticket, «la risposta lo rende evidente senza ambiguità»). È una
      differenza voluta rispetto a `customer`, la cui chiave sparisce quando il cliente manca
      (`QuoteController.php:418`): con la chiave assente, chi legge non distinguerebbe «nessun
      task aperto» da «`include` non applicato».
- [ ] Senza `include=nextTask` la risposta è identica a oggi: la chiave `nextTask` non c'è, e non
      viene eseguita nessuna query sui task.
- [ ] Il docblock `@response` e il `#[QueryParameter('include')]` di `QuoteController::show`
      documentano `nextTask` come chiave opzionale, e `QuoteApiDocsTest` verifica che `/docs/api`
      la riporti.
- [ ] **[DA DECIDERE con il CTO]** esposizione tramite `include` oppure sempre presente. Default
      adottato: `include`, coerente con gli altri dati collegati dell'endpoint. Va risolto prima di
      scrivere `plan.md`; il passaggio all'altra forma tocca solo il caricamento in `show`, il
      docblock e un caso di test.
- [ ] Test in `tests/Feature/Api/QuoteApiTest.php` per: task di un altro utente, task scaduto,
      più task `todo` (vince la `due_date` più vicina), task `completed` ignorati anche quando
      hanno una `due_date` **precedente** a quella del `todo` (deve vincere il `todo`), nessun task
      `todo` (`null`), assegnatario `null`, quote chiusa, assenza della chiave senza `include`.

Nessun testo traducibile: la feature aggiunge solo chiavi JSON.

## Rischi

- **Contratto consumato fuori dal repo.** La forma della risposta è usata dalle skill Claude
  esterne (regola del `CLAUDE.md`). Con `include` la risposta di chi non chiede `nextTask` non
  cambia, quindi nessun client esistente si rompe.
- **Il problema del 3 novembre resta finché la skill non viene aggiornata.** Il dato arriva solo
  con `?include=nextTask`; la risposta di chiusura del ticket indica la riga da aggiungere alla
  chiamata.
- **Criterio diverso dalla lettera del ticket.** Chi ha scritto il ticket potrebbe aspettarsi il
  task creato per ultimo: la scelta e il motivo vanno scritti nella risposta di chiusura.
- **Un task vecchio non chiuso nasconde quello attuale.** L'ordine crescente di `due_date` fa
  vincere il task scaduto da più tempo: se la quote ha un follow-up di marzo rimasto `todo` e un
  task di domani di chi la segue davvero, l'API restituisce quello di marzo, con il suo
  assegnatario. Si accetta: il task restituito è proprio quello da chiudere, e chiuso quello
  compare il successivo. Va scritto nella risposta di chiusura. Quanto sia frequente non è
  verificato: il DB locale non ha task `todo`.
- **Una chiamata per ogni trattativa.** Il dato arriva solo da `GET /api/quotes/{id}`: per fare
  il punto su tutta la pipeline la skill fa `GET /api/quotes` e poi una
  `GET /api/quotes/{id}?include=nextTask` per ogni quote (27 il 3 novembre). È il perimetro che
  chiede il ticket; se servirà nell'`index`, `include=nextTask` lì sarà un'aggiunta compatibile in
  un ticket a parte, che riusa la stessa relazione del modello.
- **Un nome sbagliato in `include` è ignorato in silenzio.** `show` scarta senza errore i nomi
  fuori da `ALLOWED_INCLUDES` (`QuoteController.php:144-147`): con `include=next_task`, o su un
  ambiente dove la modifica non è ancora deployata, la chiave `nextTask` manca. Una skill che legge
  il campo con `quote.get("nextTask")` confonde la chiave assente con `null` e vede tutte le
  trattative senza task. Il comportamento di `include` non cambia; la risposta di chiusura indica
  a chi aggiorna la skill di usare esattamente `include=nextTask` e di trattare la chiave assente
  come «include non applicato», non come «nessun task».
- **Relazione che restituisce `null` per errore.** Con `hasOne()->ofMany()`, se il filtro
  `status = 'todo'` sta fuori dalla closure di `ofMany`, il `MIN(due_date)` si calcola anche sui
  task `completed`: quando il task con la data minima è completato, la relazione restituisce `null`
  anche se c'è un `todo`. Mitigato dal test con un task `completed` precedente al `todo`, che
  fallisce con questa costruzione qualunque strada scelga il piano.
- **Pareggio sulla `due_date` diverso da Nova.** `scopeWithNextTodoTask` ordina solo per
  `due_date`: con due task `todo` alla stessa scadenza Nova può mostrarne uno diverso da quello
  dell'API. Suppongo che il caso sia raro, non l'ho verificato: il DB locale non ha task `todo`.

## Out of scope

- Aggiornare la skill che legge le quote per chiedere `include=nextTask` (decisione del dev,
  domanda 6).
- Lo storico dei task della trattativa, i permessi di creazione e modifica dei task, e
  `GET /api/tasks`, che continua a restituire solo i task dell'utente autenticato (ticket, «Non
  richiesto»).
- `nextTask` in `GET /api/quotes` (`index`) e nella detail view Nova della Quote.

## Moduli toccati

Tutto nel repo principale `orchestrator`, nessun submodule.

- `app/Models/Quote.php` — relazione che restituisce il task `todo` con la `due_date` più vicina,
  caricabile con `load()` come le altre relazioni di `include`.
- `app/Http/Controllers/Api/QuoteController.php` — `nextTask` in `ALLOWED_INCLUDES`, serializzazione
  in `formatQuote()`, docblock `@response` e `#[QueryParameter('include')]` di `show`.
- `tests/Feature/Api/QuoteApiTest.php` — casi elencati nei Requisiti.
- `tests/Feature/Api/QuoteApiDocsTest.php` — `nextTask` presente nello schema di `/docs/api`.
- `docs/knowledge/quote-api-e-pdf.md` — a fine lavoro.
