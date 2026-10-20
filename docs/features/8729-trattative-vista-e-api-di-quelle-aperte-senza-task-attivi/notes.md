> Ticket: oc:8729

# Notes — Trattative: vista e API di quelle aperte senza task attivi

## Divergenze dal piano, task per task

### Task 2 — Parametro without_active_tasks su GET /api/quotes

Piano e overview chiedevano la regola `boolean` di Laravel, presentandola come quella che accetta
`1`, `0`, `true`, `false`. Non è così: `validateBoolean` accetta solo `true`, `false`, `0`, `1`, `'0'`,
`'1'` (`vendor/laravel/framework/src/Illuminate/Validation/Concerns/ValidatesAttributes.php:494-502`),
quindi la stringa `true` di una query string risponderebbe 422. I valori promessi dal requisito sono
quelli che contano (un client JS serializza un booleano come `true`): usata la regola
`in:0,1,true,false`, letta con `$request->boolean()`. Il test `without_active_tasks_accetta_true` lo
copre.

### Task 3 — Filtri e quote_status su /api/tasks

Il piano chiedeva le regole di validazione passate da variabile, come in `QuoteController::index()`.
Lì la variabile serve per la chiave `status.*`, che farebbe scartare a Scramble il parametro dichiarato
nell'attributo; in `TaskController` non c'è nessuna chiave `.*`, quindi dopo la review le regole sono
scritte direttamente in `validate()`. Verificato su `/docs/api.json`: i quattro filtri restano
documentati con tipo e descrizione degli attributi `QueryParameter`. Aggiunto `nullable` a tutte le
regole: un filtro vuoto (`?status=`) non filtra invece di rispondere 422, come `?status=` su
`/api/quotes`.

## Bug trovati

- `QuoteFactory` assegna `customer_id` con `Customer::inRandomOrder()->first()`
  (`database/factories/QuoteFactory.php:39`): un test che crea una trattativa «di un altro cliente»
  senza `customer_id` esplicito può pescare il cliente del test stesso. È successo a
  `without_active_tasks_si_combina_con_customer_id`, verde da solo e rosso nella suite completa;
  corretto passando un cliente creato apposta.

## Decisioni

- Ricerca dei tag di ambiente (`orchestrator`, `backend`) saltata su indicazione della dev, in
  pianificazione: nessun tag associato.
- Proposta dei tag di contenuto (`trattative`, `api`) saltata su indicazione della dev, dopo
  l'approvazione dell'overview: nessun tag associato né creato.
- Stima rinviata dalla dev: `wm-estimate` ha proposto Misurato 7,9h (tempo trascorso, pause
  comprese) + Stimato 2,7h = 10,6h, non approvata né scritta su Orchestrator.
- Review dell'overview con `wm-review-ticket` prima del commit (esito tenuto in conversazione, non
  scritto sul ticket). Corretti tre bloccanti: «Cosa cambia» allineato a «Rischi» (il client confronta
  `/api/quotes` e `/api/tasks`); scoping `forUser` dichiarato «salvo esito della domanda aperta»;
  `ArchivedQuotes::lenses()` vuoto per non ereditare la Lens. Cleanup applicati: tolto l'elenco unico
  degli stati chiusi nell'enum (non chiesto dal ticket, avrebbe coperto 2 dei 6 punti che duplicano la
  lista); tolto il 422 su `due_from` > `due_to` (convenzione di `StoryController::validatedDateFilter`);
  422 su `quote_id` non intero. Cleanup non applicati, su scelta della dev: Out of scope (endpoint
  dedicato, tool MCP `list_tasks` che accetta solo `sort`), test su `/docs/api.json` e casi della Lens,
  riformulazione della riga «task attivo con una scadenza», scope sul modello ed eager load di
  `customer` nella Lens (da riprendere nel piano).
  **⚠️ Superati in parte dallo scrum del 20/10 (voce sotto):** il confronto lato client fra
  `/api/quotes` e `/api/tasks` non è più la strada per le trattative scoperte; lo scoping `forUser`
  resta invariato senza condizioni e la domanda «Task di tutti o solo dell'utente» è tolta; lo scope sul
  modello è ora un requisito (definizione unica per Lens e API). Restano validi `ArchivedQuotes::lenses()`
  vuoto, i 422 su `quote_id` e il no al 422 su `due_from` > `due_to`, l'eager load di `customer` da
  riprendere nel piano.
- Scrum del 20/10/2026, review dell'overview con Alessandro Peci e Rubens Garofalo (trascrizione
  incollata dalla dev): Lens confermata («optiamo per la lens»); per l'API, parametro su
  `GET /api/quotes` che restituisce solo le trattative senza task attivi («metterei semplicemente un
  altro parametro in più per tornarmi quelli che non hanno task attivi. Lo metterei direttamente a sto
  punto dentro le pie di quot»), perché `/api/quotes` non filtra per utente e il confronto con
  `/api/tasks` sbaglia sui task dei colleghi. Le modifiche a `/api/tasks` della call del 30/09 restano
  su scelta della dev, perché le aveva chieste Giuseppe (assente allo scrum): la conferma è chiesta al
  reviewer nell'overview.
- Seconda review dell'overview con `wm-review-ticket`, dopo lo scrum (esito tenuto in conversazione).
  Corretti due bloccanti: l'esempio «quali task ha la trattativa 42?» per `/api/tasks`, falso per lo
  scoping `forUser`, sostituito, e l'avviso Scramble esteso a tutti i filtri di `/api/tasks`; queste
  note aggiornate. Cleanup applicati: valori ammessi e lettura booleana di `without_active_tasks`,
  regola passata da variabile per Scramble; `QuoteApiDocsTest` fra i moduli; `QuoteNoFilter` con
  `lenses()` vuoto; tabella Lens/Filter riformulata (menu a tendina, esclusione delle chiuse dallo
  scope); `quote_status` `string|null`; moduli di `/api/tasks` marcati «salvo esito»; pagina
  `quote-api-e-pdf.md` per il parametro e la Lens. Non applicati: elenco unico degli stati chiusi
  (già scartato), eager load di `customer` (nel piano). Da chiarire: le Quote `template` in stato
  aperto e senza task comparirebbero fra le trattative scoperte; non verificato, perché il DB locale
  non ha template.
- Stima del 20/10/2026: `wm-estimate` non ha stimato senza `plan.md`; stima fatta nel context
  principale, quindi non indipendente (Misurato 9,5h + Stimato 4,5h = 14h, di cui circa 1h per le
  modifiche a `/api/tasks`). Sovrastimata: le 7,9h «misurate» delle sessioni precedenti erano tempo
  trascorso con le pause, mentre la pianificazione effettiva è sotto le 3h, e l'overview lascia poche
  decisioni aperte all'implementazione. Stima corretta dalla dev a 4h, scritta su Orchestrator in
  `estimated_hours`.
- Review dell'overview di Alessandro Peci (Slack, 20/10/2026 11:52, riportato dalla dev): overview
  approvata («va bene così»); le modifiche a `/api/tasks` restano nel ticket perché chieste da Giuseppe
  e utili ad altri usi. Tolta la domanda aperta per il reviewer e le condizioni «salvo esito» da
  requisiti e moduli.
- Verifica manuale della Lens in Nova locale (20/10/2026), fatta dalla dev e ripetuta da Claude via
  browser: la voce «Trattative senza task attivi» compare nel menu a tendina in cima all'elenco dei
  Preventivi; la Lens mostra ID, titolo, cliente (link) e stato tradotto; «Preventivi archiviati» non
  ha il menu delle Lens. Sul DB locale la richiesta della Lens fa una sola query sui clienti.
- Come si usa la Lens, avanti e indietro. **Entrare:** in CRM → Preventivi, menu a tendina delle
  Lens in cima all'elenco (icona accanto ai filtri) → «Trattative senza task attivi»; l'indirizzo
  diventa `/resources/quotes/lens/quotes-without-active-tasks`. **Uscire:** nella pagina della Lens
  non c'è il menu delle Lens né un pulsante per toglierla; si torna all'elenco completo cliccando
  «Preventivi» nella barra laterale, oppure con il pulsante indietro del browser. È il comportamento
  standard delle Lens Nova (uguale per `StoriesByQuarter` e `MyEpicLens`), non un difetto di questa.
- Review formale del codice con `wm-review-ticket` prima del commit (20/10/2026, esito tenuto in
  conversazione). Un bloccante, corretto: la Lens non aveva un ordinamento predefinito e la query
  arrivava a PostgreSQL con `LIMIT`/`OFFSET` senza `ORDER BY` (88 trattative scoperte in locale, 4
  pagine), quindi sfogliando le pagine una trattativa poteva saltare o ripetersi; ora ordina per `id`
  decrescente, come `GET /api/quotes`, con un test. Cleanup applicati: `nullable` sui filtri di
  `/api/tasks` e su `without_active_tasks` (vuoto = assente), requisito dell'overview allineato a
  `in:0,1,true,false`, commento sbagliato di `TaskController` tolto (vedi divergenza del Task 3), test
  della Lens limitato alle trattative create dal test. Non applicati: label di stato e stati chiusi
  ripetuti, docblock in inglese come nei file che li contengono, `'App\Nova\Customer'` come stringa
  (come in `Nova\Quote`), ricerca e ordinamento per cliente/stato nella Lens, action ereditate.
- Verifica della documentazione Scramble (Task 5.2) su `/docs/api.json` in locale: `GET /quotes` ha
  `without_active_tasks` (boolean); `GET /tasks` ha `status`, `quote_id`, `due_from`, `due_to` e
  `quote_status` nella risposta; `status` di `/quotes` conserva la forma di oc:8723. Suite completa:
  729 test verdi.

## Follow-up
