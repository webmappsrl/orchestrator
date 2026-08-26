> Ticket: oc:8403

# API Task per follow-up e automazioni

## Cosa cambia
Vengono aggiunte nuove API REST per la risorsa `Task`, seguendo il pattern già consolidato per Quote/Tag (`app/Http/Controllers/Api/`, `app/Http/Requests/Api/`, sotto `auth:sanctum`, documentazione automatica via `dedoc/scramble`):

- `GET /api/tasks` — lista dei task dell'utente autenticato (`Task::scopeForUser()`: owner della Quote **o** creatore del Task), ordinamento default `due_date asc`, opt-in `?sort=created_at`/`?sort=-created_at` (stessa sintassi di `QuoteController::index()`). Nessuna paginazione (lista naturalmente piccola, scope per-utente). Ogni Task include sempre nel payload `assignee` (derivato da `quote->user`) e `quote_id`/`quote_title` — nessun `?include=` opt-in, per evitare round-trip aggiuntive al flusso Cowork.
- `GET /api/tasks/{task}` — dettaglio singolo, autorizzazione **ruolo-only** (qualsiasi Admin/Manager/Developer, nessuno scoping ownership — stesso pattern di `QuotePolicy::view()`).
- `POST /api/tasks` — creazione nuovo Task (usato anche per i follow-up, come task ordinario sulla stessa Quote, senza riferimento esplicito al task di origine). Autorizzazione ruolo-only (`TaskPolicy::create()`, stesso perimetro di `QuotePolicy::create()` — nessun vincolo di ownership sulla Quote, mirror della regola Nova già in vigore), **con l'eccezione esplicita**: nega la creazione se la Quote referenziata è `closed_won`/`closed_lost` (stesso blocco già applicato da `QuotePolicy::update()` sulla Quote stessa — introduce una restrizione non presente oggi in Nova per il Task, decisione presa in Fase: challenge per evitare follow-up infiniti su trattative chiuse). Validazione (whitelist esplicita in `TaskApiRequest`, **`creator_id` mai accettato in input** — resta gestito solo dall'hook `Task::booted()`): `quote_id` deve esistere, `title` e `due_date` obbligatori, nessun vincolo che `due_date` non possa essere nel passato.
- `PATCH /api/tasks/{task}` — limitato a due campi (whitelist esplicita in `TaskApiRequest`: `status`, `notes` — **`creator_id` mai accettato in input**), con **autorizzazione differenziata per campo** (documentata esplicitamente nel docblock della Policy):
  - `status` (`todo`/`completed`, validato con `in:todo,completed`) — autorizzato **solo se `creator_id === utente loggato`**, mirror esatto di `App\Nova\Actions\ToggleTaskCompleted::authorizedToRun()`. `completed_at` si aggiorna automaticamente via l'hook `Task::booted()` già esistente, nessuna logica aggiuntiva nel controller.
  - `notes` — autorizzato a **qualsiasi Admin/Manager/Developer** (mirror di `Story::addDevNote()`). Nuovo metodo `Task::appendNote(string $note)`: **prepend** in cima al campo `notes` esistente con formattazione HTML e timestamp (mirror del comportamento reale di `addDevNote()`, non del suo nome — il metodo storico "prepende", non accoda), mai sovrascrittura.
  - **Comportamento "tutto o niente"**: se il payload contiene `status` e l'utente non è il creator del Task, l'intera richiesta fallisce con `403` — nessun aggiornamento parziale silenzioso, anche se `notes` nello stesso payload sarebbe stato autorizzato.
- Nuova `TaskPolicy` con lo stesso perimetro ruoli di `QuotePolicy` (`before()`: nega se non Admin/Manager/Developer, altrimenti prosegue). Il docblock di `update()` documenta esplicitamente l'autorizzazione differenziata per campo, dato che si discosta dal pattern "un verdetto per endpoint" usato da `QuotePolicy`.
- Nessun `DELETE` in questo ciclo.
- Documentazione automatica via `dedoc/scramble` (già configurato, nessuna azione aggiuntiva oltre ai docblock standard, stile identico a `QuoteController`).

## Perché
Abilitare un flusso end-to-end gestito dalla skill Cowork: leggere task + quote collegata + eventuali email correlate (via Gmail), generare una bozza email in Gmail, e dopo l'invio da parte dell'utente permettere di segnare il task come completato e creare automaticamente un nuovo task di follow-up — oggi impossibile perché i Task non sono esposti da nessuna API.

## Requisiti
- [ ] Nuova `TaskPolicy` (`before()` replica `QuotePolicy`: solo Admin, Manager, Developer)
- [ ] `GET /api/tasks` — lista scoped via `Task::scopeForUser()`, ordinamento default `due_date asc`, opt-in `sort=created_at`/`sort=-created_at`, nessuna paginazione, payload con `assignee` + `quote_id`/`quote_title` sempre presenti
- [ ] `GET /api/tasks/{task}` — dettaglio, autorizzazione ruolo-only (no ownership scoping)
- [ ] `POST /api/tasks` — creazione (stesso endpoint per i follow-up), ruolo-only, blocco esplicito su Quote `closed_won`/`closed_lost`, `quote_id` esistente, `title`/`due_date` obbligatori, `creator_id` mai accettato in input (whitelist)
- [ ] `PATCH /api/tasks/{task}` — whitelist `status`/`notes` (mai `creator_id`), autorizzazione per-campo: `status` solo creator (mirror `ToggleTaskCompleted`, validato `in:todo,completed`), `notes` qualsiasi ruolo abilitato (mirror `Story::addDevNote()`), comportamento tutto-o-niente se `status` è presente e l'utente non è creator
- [ ] `Task::appendNote()` — prepend con formattazione HTML e timestamp, mai sovrascrittura, mirror del comportamento reale di `Story::addDevNote()`
- [ ] Nessun `DELETE` in questo ciclo
- [ ] Documentazione automatica via `dedoc/scramble` (docblock `@response` + `#[QueryParameter]` dove serve, stile `QuoteController`)

## Rischi
- **Autorizzazione per-campo nello stesso metodo `update()` della Policy**: non standard rispetto al resto del progetto (Quote/Tag hanno un solo verdetto per endpoint). Mitigato documentando esplicitamente nel docblock di `TaskPolicy` e nel controller quale campo richiede quale check, con comportamento tutto-o-niente sul PATCH misto, e con test dedicati per tutte le combinazioni (creator che cambia status, non-creator che aggiunge nota, non-creator che tenta di cambiare status → 403, payload misto status+notes da non-creator → 403 sull'intera richiesta).
- **`Task::appendNote()` duplica la logica HTML/timestamp di `Story::addDevNote()`** senza un'astrazione condivisa (nessun trait/metodo comune tra i due modelli oggi) — accettato per questo ciclo, stesso pattern già usato altrove nel progetto (duplicazione intenzionale su modelli non direttamente correlati).
- **Nessuna paginazione su `GET /api/tasks`**: se il numero di task per utente crescerà molto, la lista intera verrà sempre restituita in un'unica risposta. Accettato consapevolmente: lo scope è per-utente (owner Quote o creatore), volumi attesi bassi.
- **`due_date` senza vincolo su date passate**: un client malformato potrebbe creare task già "scaduti" alla nascita. Accettato per abilitare il caso d'uso legittimo di task retroattivi.
- **`creator_id` nullable (`nullOnDelete()`) → Task "orfani" permanenti**: se l'utente creatore viene eliminato, `creator_id` diventa `NULL` e il confronto `creator_id === utente loggato` non è mai vero per nessuno — il task non è più completabile/riapribile né via API né via Nova. Limite **ereditato** da `ToggleTaskCompleted` (già presente identico in Nova prima di questo ticket, non introdotto da questa feature). Nessun admin-override in questo ciclo: aggiungerne uno cambierebbe anche il comportamento Nova esistente, fuori scope. Recuperabile solo via `tinker`/DB diretto.
- **`assignee` spesso `null` nel payload**: il 72% delle Quote attuali non ha `user_id` valorizzato (dato noto da oc:8327) — la maggior parte dei Task avrà `assignee: null`. Il flusso Cowork (generazione bozze email) deve gestire questo caso lato client; nessun fallback lato Orchestrator in questo ciclo.
- **Contratto payload "sempre incluso, mai opt-in" (`assignee`, `quote_id`, `quote_title`, nessuna paginazione) consumato da un client esterno (skill Cowork) fuori da questo repo**: un futuro cambio (paginazione, `?include=` opt-in) sarebbe un breaking change che richiede coordinamento cross-repo con l'aggiornamento della skill, non un semplice rollback lato backend — a differenza di `QuoteController::index()` che ha adottato il pattern opt-in fin dall'inizio proprio per evitare questo.

## Out of scope
Endpoint per invio email (resta responsabilità della skill Claude, stessa decisione già presa per le Quote in oc:8291). Riferimento esplicito tracciabile tra task di origine e task di follow-up (nessuna nuova colonna/migrazione). `DELETE /api/tasks/{task}`. Filtri aggiuntivi sull'index (es. per `quote_id`, per `status`) oltre al parametro `sort`. Paginazione su `GET /api/tasks`. `?include=` opt-in sul payload (quote/assignee sono sempre inclusi di default, non condizionali).

## Moduli toccati
- `app/Http/Controllers/Api/TaskController.php` (nuovo)
- `app/Http/Requests/Api/TaskApiRequest.php` (nuovo)
- `app/Policies/TaskPolicy.php` (nuovo)
- `app/Models/Task.php` — nuovo metodo `appendNote()`
- `routes/api.php` — nuove route sotto il gruppo `auth:sanctum` esistente
