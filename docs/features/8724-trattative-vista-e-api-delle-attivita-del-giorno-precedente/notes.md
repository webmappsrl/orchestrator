> Ticket: oc:8724

# Notes — Trattative: vista e API delle attività del giorno precedente

## Decisioni

- Tag associato al ticket in `environment-setup: tag-ambiente`: `orchestrator` (id 590). Nessun tag
  nuovo creato, su indicazione della dev.
- Tag di contenuto (`overview: tag-contenuto`): cercati fra i tag esistenti i termini «trattative»,
  «crm», «quote», «preventivi», «api». Nessun tag pertinente (per «api» solo due tag Documentation
  di altri progetti, id 311 e 318). Nessun tag associato e nessuno creato, su indicazione della dev.

- 11/11/2026, approvazione del piano: la dev ha chiesto se la migration fosse indispensabile.
  Valutata l'alternativa senza tabella (attività ricavate da `tasks.created_at`/`completed_at`, dal
  testo di `appendNote` e da `quotes.updated_at`): non dice quale campo della trattativa è cambiato
  né chi l'ha cambiato, che è quanto il ticket chiede. Tenuta la tabella `quote_activities`. La
  scelta della tabella dedicata non risulta approvata esplicitamente da Alessandro Peci (PR #275
  senza review; nello scrum dell'11/11 non se ne parla): resta da vedere in review.

- 16/11/2026, review-gate: su richiesta della dev il termine «diario», coniato in sessione e
  assente dal ticket, è stato sostituito in documenti e commenti con «attività» (`QuoteActivity`,
  `quote_activities`), il nome del codice e del titolo del ticket. Nella stessa occasione overview e
  piano sono stati riallineati al codice: numeri di riga spostati dall'implementazione,
  `quote_title` nullable, firma di `record()` e `recordProduct()`, lingua da
  `app()->getLocale()`, `creator_id` fra i campi ignorati dei task.

- 16/11/2026, review formale (`wm-review-ticket`, verdetto «approvato con riserve», nessun
  bloccante). Corretti due difetti reali, verificati con un test temporaneo:
  - `task_note_added` salvava anche l'intestazione di `Task::appendNote` («<nome> ha aggiunto una
    nota il: <data>»), cioè autore e ora già presenti in `user_name` e `created_at`. Succedeva a
    ogni nota aggiunta via `PATCH /api/tasks/{id}` (`TaskController.php:158`, unico chiamante di
    `appendNote`). Ora `TaskObserver::addedNote()` toglie l'intestazione.
  - Spostando una Card di colonna nel Kanban Sales, `KanbanController::updateStatus()` ricalcola
    `priority` insieme allo stato, e l'attività registrava anche `priority`. Ora `priority` è fra
    gli `IGNORED_FIELDS` di `QuoteObserver`.

  Scartati come ipotetici: la trattativa duplicata da una chiusa e poi riaperta (in Nova
  `DuplicateQuote` non è disponibile sulle trattative chiuse: `Quote::indexQuery()` le esclude e
  `ArchivedQuotes::actions()` non la offre); `days` senza massimo (il 500 arriva solo con valori
  assurdi); la transazione PostgreSQL bloccata da un INSERT fallito (nessun valore oggi supera le
  colonne copia, tutte `varchar(255)` come gli originali).

- 17/11/2026, review formale sul commit `2f6eb5c` (esito «da correggere»), Task 8 del piano. Il
  primo salvataggio da Nova di una trattativa creata via API registrava come modificati i campi
  tradotti anche se nessun valore era cambiato, perché Nova aggiunge al JSON le lingue vuote. Nei
  dati locali (17/11/2026) nessuna delle 123 trattative ha `en` nel titolo, e 41 hanno
  `additional_services` uguale a `{"it":[]}`. Il test
  `empty_locales_added_by_nova_are_not_recorded_as_changes` l'ha riprodotto prima della
  correzione (`title`, `status` e `notes` registrati invece del solo `status`). Ora
  `QuoteObserver::sameTranslations()` confronta i campi tradotti senza le lingue vuote. Suite
  completa: 820 test passati su `orchestrator_test`.

## Domande aperte

Le domande lasciate al reviewer il 04/11/2026 sono state chiuse nello scrum dell'11/11/2026
(Giuseppe Bonfanti, con Alessandro Peci e Carla Cupani). Le decisioni sono nei requisiti
dell'overview, con la loro citazione; la sezione «Domande aperte per il reviewer» è stata tolta.
La view Nova è uscita dal ticket: va chiesto ad Alessio Piccioli allo scrum di lunedì 16/11/2026
dove la vuole, poi si apre un ticket a sé.

## Riferimenti

- Nello scrum del 04/11/2026 Giuseppe Bonfanti dice: «Alessio addirittura se l'è fatta da solo su un
  branch di orchestrator». Il lavoro è la tabella sperimentale «Che cosa ho fatto ieri» nella
  dashboard Kanban-2 (commit `e24bdc82` e `3115e863`, Alessio Piccioli, 23/11/2025, file
  `app/Nova/Dashboards/Kanban2.php` e `story-viewer-experimental-activities.blade.php`). È sui
  branch `montagna-servizi`, `ms-fundraising-202602` e `ms-tag-price`, non su `develop`. Riguarda le
  Story (`story_logs`), non le trattative: da leggere come riferimento per il piano.
- Challenge del 03-04/11/2026, rischi giudicati ipotetici e non portati a domanda: fuso orario dei
  timestamp (già fra i rischi dell'overview); un errore nella registrazione delle attività che blocca il salvataggio di
  trattative e task (da coprire nel piano: la registrazione delle attività non deve mai far fallire il salvataggio);
  crescita della tabella senza pulizia periodica; task creati su trattative già chiuse.

## Divergenze dal piano, task per task

### Task 4 — test dei task nello stesso file

I test delle attività dei task sono in `tests/Feature/QuoteActivityLogTest.php` insieme a quelli di
trattative e prodotti, non in un `TaskActivityLogTest.php` separato: condividono setup e helper.

### Task 5 — trattative cancellate sempre incluse

Il filtro «chiusa prima del periodo» si applica solo alle trattative che esistono ancora. Quelle
cancellate hanno `quote_id` vuoto in tutte le righe di `quote_activities` (FK `nullOnDelete`), quindi lo
stato all'inizio del periodo non si può ricostruire: compaiono sempre, raggruppate per titolo
copiato. Due trattative cancellate con lo stesso titolo nello stesso periodo finiscono nello
stesso gruppo.

### Task 6 — nessuna chiave nuova

La chiave `System` → «Sistema» / «System» esiste già in `lang/it.json:295` e `lang/en.json:450`:
nessuna modifica ai file di lingua.

### Task 7 — prova manuale non eseguita

La prova manuale da Nova con un token non è stata fatta in sessione per non scrivere dati sul DB
locale `orchestrator`; il comportamento è coperto dai 22 test nuovi. Da fare a mano in review.

### Task 8 — lingue vuote anche come array e ordine delle lingue

Il piano chiedeva di togliere le lingue con valore `null` o `''`. L'implementazione fa due cose in
più:

- toglie anche le lingue con valore `[]`. `additional_services` è un `KeyValue` dentro
  `NovaTabTranslatable` (`app/Nova/Quote.php:284`): una lingua vuota arriva come `"{}"` e si salva
  come `[]` (`vendor/kongulov/nova-tab-translatable/src/NovaTabTranslatable.php:171-173`). Emerso
  nella review formale del 18/11/2026 sul diff del Task 8, riprodotto con
  `empty_key_value_locales_added_by_nova_are_not_recorded_as_changes`, che fallisce con
  l'observer del commit `2f6eb5c`;
- ignora l'ordine delle lingue nel JSON (`ksort`): Nova le salva nel proprio ordine, e una
  trattativa creata via API con due lingue le può avere in un ordine diverso.

Aggiunto anche `real_change_on_translated_field_is_still_recorded`: una modifica vera di un campo
tradotto continua a registrarsi, e in `changes` finiscono solo le lingue con un valore. Prima
entravano anche le lingue vuote aggiunte da Nova (`"de": null`), che chi legge l'API poteva
scambiare per lingue aggiunte: emerso nella stessa review del 18/11/2026, come cleanup. Suite completa dopo la correzione, anche dopo questa modifica: 822 test passati su
`orchestrator_test`.

## Follow-up

- Da aggiungere al `CLAUDE.md` da chi lo gestisce (la dev non lo modifica):
  - la riga sotto `## Conoscenza` per la pagina
    [docs/knowledge/8724-trattative-vista-e-api-delle-attivita-del-giorno-precedente.md](../../knowledge/8724-trattative-vista-e-api-delle-attivita-del-giorno-precedente.md):
    «Attività delle trattative | tabella `quote_activities`, observer di Quote e Task, pivot custom
    dei prodotti, `GET /api/quote-activities`, cosa non viene registrato»;
  - eventualmente una regola in `## Regole del repo`: quando si aggiunge un punto di scrittura su
    Quote, Task o prodotti, passare da Eloquent (niente `saveQuietly()`, update in massa, SQL
    diretto, `detach()` senza id), altrimenti l'attività non viene registrata.

- Cleanup non bloccanti dalla review del 16/11/2026, da valutare in un ciclo successivo:
  - documentare nella `@response` di Scramble le tre forme di `changes` (`{campo:{old,new}}`,
    `{note}`, `{product_id, product_name}`);
  - `DuplicateQuote` registra un `quote_updated` su `user_id` subito dopo la creazione
    (`associate()` + `save()` dopo il `create`);
  - i cambi di quantità di un prodotto già presente non si registrano (i pivot ascoltano solo
    `created`/`deleted`);
  - un Task spostato su un'altra trattativa compare solo sotto quella di destinazione;
  - una trattativa chiusa prima del periodo e riaperta dentro il periodo viene esclusa: è la
    lettera del requisito, va confermato che sia il comportamento voluto;
  - `QuoteObserver::fieldChanges()` usata da `TaskObserver`; `ProductQuote` e
    `QuoteRecurringProduct` quasi identici; N+1 in `wasClosedAt()` e `recordProduct()`;
    `CLOSED_STATUSES` duplicata rispetto a `ArchivedQuotes` e `app/Nova/Quote.php`; commento
    fuorviante in `routes/api.php` sulla collisione con `/quotes/{quote}`;
  - test mancanti rispetto al piano: modifica del cliente, `DuplicateQuote` con prodotti
    ricorrenti, più eventi dallo stesso `save()`.

- `QuoteGetTotalPriceTest` e `QuoteGetTotalRecurringPriceTest` non usano né `DatabaseTransactions` né
  `RefreshDatabase`: lasciano Quote e prodotti in `orchestrator_test` e, con gli observer di questo
  ticket, 18 righe di `quote_activities` a ogni esecuzione. Scoperto in review-gate: lanciato da
  solo, `QuoteActivityLogTest` falliva in 5 test (nella suite completa passava solo per l'ordine di
  esecuzione). Corretto qui svuotando la tabella in `setUp()`, come già faceva
  `QuoteActivityApiTest`; l'isolamento dei due test vecchi resta da fare in un ticket a sé.

- Aprire un ticket a sé per la view Nova delle attività, dopo aver chiesto ad Alessio Piccioli allo
  scrum di lunedì 16/11/2026 se è una view nuova o la modifica di una esistente, e in quale `MenuSection`.
