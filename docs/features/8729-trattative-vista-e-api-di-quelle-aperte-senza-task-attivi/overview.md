> Ticket: oc:8729

# Trattative: vista e API di quelle aperte senza task attivi

## Cosa cambia

Una trattativa aperta deve sempre avere un task attivo con una scadenza: se non lo ha, nessuno la sta
seguendo. Dopo questa feature:

- **API, trattative scoperte** — `GET /api/quotes` accetta il parametro opzionale
  `without_active_tasks`: con `1` restituisce solo le trattative aperte che non hanno nessun task
  attivo, di qualunque utente. Si combina con i filtri e la paginazione che ci sono già; senza
  `customer_id` vale per tutti i clienti. È la strada da usare per questa domanda.
- **API dei task** — `GET /api/tasks` accetta filtri per stato, scadenza e trattativa, e ogni task
  riporta lo stato della trattativa a cui appartiene. Era la strada decisa nella call del 30/09 (il
  client confronta trattative e task); resta per gli altri usi dell'API dei task, salvo esito della
  domanda aperta per il reviewer «Modifiche a `/api/tasks`: tenerle o toglierle».
- **Nova** — una Lens sulle Quote, «Trattative senza task attivi», elenca le trattative aperte che non
  hanno nessun task in stato `todo`. Restituisce le stesse trattative del parametro dell'API.
- Nessuna trattativa viene chiusa in automatico: la chiusura resta manuale.

Definizioni, ricavate dal codice:

- **trattativa aperta**: ogni stato tranne `closed won` e `closed lost` — quindi `new`, `to present`,
  `presented`, `on hold` («In attesa di esito», oc:8723), `waiting for order`, `cold`
  (`app/Enums/QuoteStatus.php:7-14`). È la stessa esclusione che l'elenco Nova delle Quote applica già
  in `Nova\Quote::indexQuery`.
- **task attivo**: task in stato `todo` (`Task::STATUS_TODO`), di chiunque sia — assegnatario e
  creatore non contano. La scadenza c'è sempre, perché `due_date` è obbligatoria
  (`create_tasks_table.php:16`): un task `todo` con scadenza passata conta come attivo.

### Perché la Lens e non un Filter

**Scelta: Lens**, confermata allo scrum del 20/10/2026 (Alessandro Peci: «optiamo per la lens»). Il
ticket lascia aperte entrambe le strade («Lens o Filter Nova sulle Quote aperte senza task attivi»);
la scelta segue la richiesta del cliente: «Ci serve una **vista**, e un'API, con le trattative aperte
che non hanno task attivi» (customer_request di oc:8729). In Nova la vista è la Lens.

| | Lens (scelta) | Filter |
|---|---|---|
| Pro | È una pagina dedicata, nel menu a tendina in cima all'elenco delle Quote; corrisponde alla «vista» chiesta | Meno codice; si combina col filtro per stato già presente; eredita l'esclusione delle chiuse da `indexQuery` |
| Contro | Classe nuova con colonne e query proprie; la Lens non passa da `indexQuery`, quindi l'esclusione delle chiuse le arriva dalla definizione sul model condivisa con l'API | Meno visibile: bisogna ricordarsi di attivarlo |

### Perché un parametro su `/api/quotes`

`GET /api/tasks` restituisce solo i task di cui chi chiede è assegnatario o creatore
(`TaskController.php:30`, `Task::scopeForUser`), quindi il confronto lato client fra trattative e
task sbaglia quando il task è di un altro. Esempio: su «Parco Alpi» Alessio crea un task attivo per
Marco; Giuseppe confronta `/api/quotes` con `/api/tasks?status=todo`, il task non gli arriva e «Parco
Alpi» gli risulta non seguita. `GET /api/quotes` invece non filtra per utente (`QuoteController.php:66`)
e il controllo sui task passa dalla relazione `Quote::tasks()` (`Quote.php:82-85`), che li contiene
tutti: la risposta è la stessa per chiunque chieda, ed è quella della Lens.

## Perché

Il commerciale vuole accorgersi delle trattative che nessuno sta seguendo senza scorrerle tutte a mano.

Sull'API, nella call del 30/09/2026 (proposta di Giuseppe, riportata nella description del ticket) si
era deciso per un'API generica dei task con stato e trattativa di appartenenza, e il filtro fatto dal
client — «io ti torno tutte le task con l'informazione se aperta e chiusa e poi è cloud che perché così
ci puoi puoi fare più cose». Allo scrum del 20/10/2026, rivedendo l'overview, il reviewer ha proposto di
aggiungere il filtro direttamente su `/api/quotes` — Alessandro Peci: «metterei semplicemente un altro
parametro in più per tornarmi quelli che non hanno task attivi. Lo metterei direttamente a sto punto
dentro le pie di quot» — perché `/api/quotes` senza `customer_id` restituisce già le trattative di tutti
(«se non ce lo metti ti torna già quello di tutti»). Le modifiche a `/api/tasks` chieste da Giuseppe
restano, perché il riepilogo dello scrum non le toglie esplicitamente: la conferma è chiesta al
reviewer.

Sulla chiusura: «non deve essere assolutamente chiusa automaticamente» (call del 30/09/2026, citata
nel ticket).

## Requisiti

### `GET /api/quotes`

- [ ] Accetta il parametro opzionale `without_active_tasks` con i valori della regola `boolean` di
      Laravel (`1`, `0`, `true`, `false`); un valore diverso (es. `si`) risponde 422. Il valore si legge
      come booleano, non con `filled()`: `?without_active_tasks=0` non attiva il filtro. La regola
      passa da una variabile, come `$statusRules`, perché Scramble non scarti i parametri dichiarati
      negli attributi (`QuoteController.php:58-64`).
- [ ] Con `without_active_tasks=1` restituisce solo le trattative con stato diverso da `closed won`/
      `closed lost` che non hanno alcun task `todo`, considerando i task di tutti gli utenti.
- [ ] Il parametro si combina con `customer_id`, `status`, `sort` e con la paginazione opzionale
      (`per_page`/`page`). Con `status` che indica solo stati chiusi l'elenco è vuoto, non un errore.
- [ ] Senza il parametro, o con `0`, la risposta è identica a oggi. La forma di ogni trattativa
      restituita non cambia.
- [ ] La documentazione Scramble descrive il parametro e dice che è la strada per trovare le
      trattative aperte senza task attivi; `QuoteApiDocsTest` verifica che il parametro sia
      documentato.

### `GET /api/tasks` (salvo esito della domanda aperta per il reviewer)

- [ ] Accetta il filtro `status` (`todo` o `completed`); un valore diverso risponde 422.
- [ ] Accetta il filtro `quote_id`: restituisce solo i task di quella trattativa. Un valore non intero
      (es. `?quote_id=abc`) risponde 422: senza controllo, Postgres rifiuta il confronto con la colonna
      `bigint` e l'API risponderebbe 500.
- [ ] Accetta i filtri di scadenza `due_from` e `due_to` (data `AAAA-MM-GG`, estremi inclusi, usabili
      anche uno solo). Il confronto è sulla sola data, perché `due_date` è un `dateTime`:
      `due_to=2026-10-20` include un task in scadenza il 20/10 alle 18:00. Una data non nel formato
      `AAAA-MM-GG` risponde 422, come per i filtri data di `GET /api/stories`
      (`StoryController::validatedDateFilter`); un intervallo con `due_from` successiva a `due_to` non
      è un errore e restituisce un elenco vuoto.
- [ ] I filtri sono opzionali e si combinano fra loro; senza filtri l'elenco dei task restituiti è
      identico a oggi (cambia solo il campo in più descritto sotto).
- [ ] Ogni task restituito ha in più il campo `quote_status` (`string|null`, come `quote_title` in
      `TaskController.php:144`), lo stato della trattativa: vale per `index`, `show`, `store` e
      `update`, che passano tutti da `formatTask()`, e va aggiunto anche nei quattro `@response`.
      `quote_id` c'è già. Nessun altro campo cambia.
- [ ] Restano invariati: nessuna paginazione, nessun `?include=`, ordinamento e `sort` come oggi, lo
      scoping `forUser` (task di cui l'utente è assegnatario o creatore).
- [ ] La documentazione Scramble descrive i nuovi parametri e il campo `quote_status` in tutti e
      quattro i metodi, e avverte che tutti i filtri valgono solo sui task dell'utente (assegnatario
      o creatore): `?quote_id=42` non restituisce i task dei colleghi su quella trattativa, e per le
      trattative senza task attivi va usato `GET /api/quotes?without_active_tasks=1`.

### Nova

- [ ] Nuova Lens Nova sulle Quote, «Trattative senza task attivi»: trattative con stato diverso da
      `closed won`/`closed lost` e senza alcun task `todo`. Colonne: ID, titolo, cliente, stato.
- [ ] La Lens e il parametro `without_active_tasks` restituiscono le stesse trattative: la definizione
      è scritta una volta sola e usata da entrambi.
- [ ] La Lens compare solo nella risorsa Quote: `ArchivedQuotes` e `QuoteNoFilter`, che estendono
      `Nova\Quote` e altrimenti la erediterebbero, restituiscono `[]` in `lenses()`. Così l'archivio
      delle trattative chiuse non mostra una vista di trattative aperte, e `QuoteNoFilter` (usata nella
      scheda Customer e raggiungibile da URL) resta senza Lens.
- [ ] Il nome della Lens è tradotto in `lang/it.json` e `lang/en.json` (lingua di default del repo:
      `it`, `config/app.php:98`).

### Generali

- [ ] Nessuna trattativa cambia stato da sola: né l'API né la Lens scrivono nulla.
- [ ] Test feature per il parametro in `tests/Feature/Api/QuoteApiTest.php`, per i filtri e il nuovo
      campo in `tests/Feature/Api/TaskApiTest.php`, e per la query della Lens.

## Rischi

- **Due strade per la stessa domanda.** Se restano le modifiche a `/api/tasks`, le trattative scoperte
  si possono cercare anche confrontando `/api/quotes` e `/api/tasks`, e quel confronto sbaglia quando il
  task è di un altro utente (esempio «Parco Alpi» sopra). Mitigazione: la documentazione Scramble di
  entrambi gli endpoint indica il parametro su `/api/quotes` come la strada per questa domanda.
- **Contratto esterno.** `/api/tasks` è consumato dalla skill Cowork e dal server MCP `orchestrator` di
  `claude-marketplace` (`docs/knowledge/task-e-quote.md:59-62`), e `/api/quotes` dalla skill
  commerciale. Le modifiche sono solo aggiuntive (parametri opzionali, un campo in più), quindi non
  rompono chi li usa oggi.

## Domande aperte per il reviewer

- **Modifiche a `/api/tasks`: tenerle o toglierle.** Con il parametro su `/api/quotes` deciso allo scrum
  del 20/10 l'obiettivo del ticket è raggiunto anche senza toccare `/api/tasks`. Le modifiche descritte
  sopra vengono dalla call del 30/09 (proposta di Giuseppe, assente allo scrum) e servono ad altre
  domande sui propri task — «quali miei task scadono questa settimana?». Non servono invece a domande
  su tutti i task di una trattativa, perché lo scoping `forUser` resta (`TaskController.php:30`).
  Proposta:
  tenerle, perché il riepilogo dello scrum non le toglie esplicitamente. Al reviewer: confermare, oppure
  indicare di toglierle da questo ticket.

## Out of scope

- Chiusura automatica delle trattative senza task (esclusa esplicitamente dal ticket).
- Notifiche o avvisi sulle trattative senza task.
- Un endpoint nuovo dedicato alle «trattative senza task»: basta il parametro su `/api/quotes`.
- Modifiche allo scoping `forUser` di `/api/tasks`.
- Il Kanban delle trattative.

## Moduli toccati

Tutto nel repo principale `orchestrator` (nessun submodule):

- `app/Http/Controllers/Api/QuoteController.php` — parametro `without_active_tasks` in `index()`,
  attributo Scramble
- `app/Models/Quote.php` — definizione unica di «trattativa aperta senza task attivi», usata da API e
  Lens
- `app/Http/Controllers/Api/TaskController.php` — filtri in `index()`, `quote_status` in `formatTask()`,
  attributi Scramble (salvo esito della domanda aperta per il reviewer)
- `app/Nova/Lenses/QuotesWithoutActiveTasks.php` — nuova Lens
- `app/Nova/Quote.php` — registrazione della Lens in `lenses()`
- `app/Nova/ArchivedQuotes.php`, `app/Nova/QuoteNoFilter.php` — `lenses()` vuoto, per non ereditare
  la Lens
- `lang/it.json`, `lang/en.json` — nome della Lens
- `tests/Feature/Api/QuoteApiTest.php` — test del parametro
- `tests/Feature/Api/QuoteApiDocsTest.php` — `without_active_tasks` fra i parametri documentati
- `tests/Feature/Api/TaskApiTest.php` — test dei filtri e del nuovo campo (salvo esito della domanda
  aperta per il reviewer)
- `tests/Feature/` — test della Lens
- `docs/knowledge/quote-api-e-pdf.md` (parametro e Lens) e `docs/knowledge/task-e-quote.md`
  (modifiche a `/api/tasks`) — aggiornamento a fine lavoro
