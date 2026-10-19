# Task collegati alle Quote

Replica della feature Task di HubSpot: promemoria (`title`, `notes` Tiptap, `due_date`,
`status`) legati a una Quote, in Nova e via API.

## Stato attuale

### Assegnatario (oc:8722)
- **L'assegnatario è salvato nel task**, in `tasks.assignee_id` (FK verso `users`,
  `nullOnDelete`), e non dipende più dall'owner della Quote: cambiare owner a una trattativa non
  sposta i suoi task.
- **Il default è in un solo punto, `Task::defaultAssigneeId(?Quote, ?User)`**: l'owner della
  Quote; se manca, o non ha un ruolo che accede ai task, l'utente che agisce. Lo usano l'hook
  `creating` di `Task::booted()`, il campo Nova precompilato e la riassegnazione alla
  cancellazione di un utente; oc:8730 lo riusa per il popup di chiusura.
- **Nullable nel DB, ma non si svuota.** Nova lo richiede; il `POST` dell'API lo accetta assente
  o `null` e applica il default; il `PATCH` con `assignee_id: null` risponde 422. Resta vuoto solo
  per un task creato senza utente autenticato su una Quote senza owner ammesso, o se l'ultimo
  candidato viene cancellato.
- **Si assegna solo a chi ha un ruolo in `TaskPolicy::ROLES`**, l'unico punto in cui sono
  elencati i ruoli che accedono ai task.
- **Cancellare un utente riassegna i suoi task** con la regola del default (owner, altrimenti
  creatore) nell'hook `deleting` di `User`. `User::delete()` gira in una transazione: Nova non ne
  apre (`Laravel\Nova\Jobs\DeleteResources`), e senza la DELETE di un owner di trattativa
  fallirebbe sulla FK di `quotes.user_id` dopo aver già riassegnato i task.
- **`users.roles` può essere NULL**: `User::hasRole()` lo tratta come «nessun ruolo». Il default
  chiama `canAccessTasks()` sull'owner della Quote, che nel campo Owner si sceglie fra tutti gli
  utenti.

### Modello e autorizzazioni (oc:8327, oc:8403, oc:8722)
- **Chiunque abbia accesso a Nova può creare un Task su qualsiasi Quote**, anche non propria e
  anche senza `user_id` valorizzato (135 Quote su 190, il 71% — locale, 2026-09-12). Diverge dal blocco
  inizialmente pianificato, deciso in corso d'opera.
- **`Task::scopeForUser()` filtra su `assignee_id` OPPURE `creator_id`** (oc:8722): l'owner della
  Quote non vede più nella lista i task della sua trattativa assegnati e creati da altri; li
  trova nel tab Task della Quote.
- **`creator_id`** è FK nullable verso `users` con `nullOnDelete`, valorizzata in
  `Task::booted()` e **mai accettata in input** dall'API (whitelist in `TaskApiRequest`).
- **`onDelete('cascade')` su `quote_id`**: eliminare una Quote elimina silenziosamente tutti i
  suoi Task. Accettato, non hanno valore autonomo.
- **`TaskPolicy::create(User $user, ?Quote $quote = null)` ha `$quote` opzionale**: Nova chiama
  `Gate::authorize('create', Task::class)` con un solo argomento per decidere se mostrare
  l'azione "crea". Una firma a due parametri obbligatori rompe **l'intera creazione di Task via
  Nova** con `ArgumentCountError`. Quando `$quote` è assente il blocco su quote chiuse non si
  applica; il controller API e `ToggleTaskCompleted` (oc:8730) passano sempre la Quote
  esplicitamente. Il form «Crea Task» di Nova resta l'unico senza blocco.
- **Chiunque acceda ai task li completa e li riapre** (oc:8722): `TaskPolicy::updateStatus()`
  restituisce `true` e il filtro sui ruoli è in `before()`; `ToggleTaskCompleted` usa la stessa
  policy. Resta un check a sé, invocato da `TaskController::update()` solo se il payload contiene
  `status` e prima di qualsiasi modifica: è il punto in cui restringere di nuovo il cambio di
  stato senza toccare `notes` e `due_date`.
- **`notes`, `due_date` e `assignee_id` nel `PATCH`** sono aperti a qualsiasi
  Admin/Manager/Developer (`TaskPolicy::update()`, mirror di `Story::addDevNote()`). `due_date`
  usa la stessa validazione del POST (`date`: sola data o data e ora, date passate ammesse)
  (oc:8625) e passa da `TaskApiRequest::dueDate()`, che la converte nel fuso dell'applicazione:
  il cast `datetime` da solo scarterebbe l'offset di un input ISO 8601.
- **`GET /api/tasks/{task}` è ruolo-only, `GET /api/tasks` è scoped** via `scopeForUser()`:
  asimmetria intenzionale, coerente col resto del progetto — la lista filtra "i miei task", il
  dettaglio (raggiungibile solo conoscendo l'id) resta visibile a chi ha un ruolo abilitato.
- **Nessuna paginazione e nessun `?include=` opt-in** sull'API Task: ogni Task include sempre
  `assignee` (`{id, name, email}` o `null`), `quote_id` e `quote_title`. Il contratto è consumato
  da un client esterno (skill Cowork, server MCP `orchestrator` di `claude-marketplace`) fuori da
  questo repo: introdurre paginazione o opt-in sarebbe un breaking change cross-repo. Nessun
  `DELETE`.
- **`Task::appendNote()` prepende, non accoda**, mirror del comportamento reale di
  `Story::addDevNote()` (che nonostante il nome storico prepende sempre).

### Viste Nova (oc:8327, oc:8402, oc:8722)
- **Vista globale come Nova Filter, non Kanban né Lens**: le colonne
  scaduto/oggi/imminente/completato sono proiezioni calcolate da una data, non stati persistiti,
  quindi un drag&drop fra loro non avrebbe un'azione di scrittura naturale. Confermato dal
  comportamento reale di HubSpot (fonte del requisito): tab di filtro più tabella.
- **`fields()`, `filters()` e `indexQuery()` vanno tutti scoped su `$request->viaResource === 'quotes'`**:
  Nova richiama gli stessi metodi per la vista globale e per il sub-panel Task dentro il
  dettaglio Quote. Scopare solo `indexQuery()` fa trapelare riordino colonne e colonna/filtro
  Assegnatario anche nel sub-panel. I campi condivisi fra i due rami stanno in metodi privati
  (`statusBadgeField()`, `completedField()`, `assigneeField()`) e in `notesField()`, che è
  `public static` perché lo riusa il popup di chiusura (oc:8730); nel sub-panel il campo
  Assegnatario è `onlyOnForms()`, così la lista resta senza colonna.
- **Campo Assegnatario precompilato in creazione** (oc:8722): dal tab Task della Quote con
  `default()`, nella vista globale con `dependsOn(['quote'])`, che lo ricalcola a ogni cambio di
  trattativa ma solo se `isCreateOrAttachRequest()`: in modifica il valore salvato non si tocca.
- Nel sub-panel lo scoping "solo i miei/creati da me" va **bypassato**, altrimenti il sub-panel
  di una Quote non propria risulta vuoto pur avendo Task.
- **`indexQuery()` bypassa `forUser()` solo per Admin/Manager**: la vista globale è nel menu solo
  per loro; rimuovere `forUser()` per tutti avrebbe esposto l'intero backlog task (note interne
  comprese) a Customer ed Editor con accesso Nova generico. Il vero argine per quei ruoli è però
  `TaskPolicy` (403 prima ancora di `indexQuery()`), non `forUser()`.
- **`TaskAssigneeFilter` filtra su `assignee_id`** (oc:8722), non più sull'owner della Quote.
- **Vista «I miei task»** (`App\Nova\MyTask`, uriKey `my-tasks`, oc:8722): estende `Task`, mostra
  `scopeForUser()` per ogni ruolo e ha il filtro `MyTaskScopeFilter` («Assegnati a me» di default,
  «Creati da me»). È `$globallySearchable = false`: Nova sceglie la resource di un model in ordine
  alfabetico (`resourceForModel(Task)` restituisce `MyTask`, come Quote → `ArchivedQuotes`), e
  senza quel flag la ricerca globale mostra ogni task due volte.
- **Il badge di urgenza confronta solo la data, mai l'ora** (`now()->startOfDay()` fresco, senza
  mutare l'attributo `due_date`): un confronto su datetime completo classificava come "scaduto"
  un task in scadenza oggi non appena l'orario corrente superava quello di scadenza,
  disallineandosi dalla semantica a livello di giorno usata da scope e filtro.
- **L'azione di chiusura** («Segna come completato» / «Riapri», `ToggleTaskCompleted`) sostituisce
  l'azione "Replica" di default (`authorizedToReplicate() => false`); come funziona è nella
  sezione sotto.
- **`App\Nova\Quote::title()` ha il fallback `$this->name ?: $this->title`**: la Resource usa
  `$title = 'name'`, ma `name` è un fillable morto (vedi [Quote: API, PDF e viste Nova](quote-api-e-pdf.md)) —
  senza fallback il `BelongsTo` verso Quote nel form Task mostra un'etichetta vuota.

### Chiusura con task successivo (oc:8730)
- **Un'azione sola per chiudere e riaprire**: su un task aperto si chiama «Segna come completato» e
  il popup ha i campi del task successivo (Titolo, Scadenza, Assegnatario, Note); su un task chiuso
  si chiama «Riapri» e non ha campi. Con il titolo nasce un task sulla stessa Quote, nella stessa
  transazione della chiusura; senza titolo il task si chiude e basta. Scadenza e Assegnatario sono
  obbligatori solo con il titolo (`required_with:title`).
- **`uriKey` fisso e campi restituiti quando il task non c'è**: all'esecuzione Nova ricostruisce
  l'azione su un modello vuoto e la ritrova per `uriKey`. Un `uriKey` ricavato dal nome, che cambia
  con lo stato, darebbe 404; dei campi tolti «quando il task è chiuso» sparirebbero anche
  all'esecuzione, e il task successivo non nascerebbe senza errore. Il test passa dall'endpoint
  `POST /nova-api/tasks/action` per vedere proprio questo.
- **Un task per volta**: `sole()` nasconde l'azione con più task selezionati, ma agisce solo
  sull'interfaccia; `handle()` rifiuta più di un task.
- **L'Assegnatario del popup è precompilato con `Task::defaultAssigneeId()`**, calcolato solo
  all'apertura del popup. La ricerca degli utenti nel popup passa dal campo `assignee` della
  risorsa (`/nova-api/tasks/associatable/assignee`), non da quello dell'azione: i due campi devono
  restare con lo stesso attributo e lo stesso filtro `withTaskAccess()`.
- **Su una Quote chiusa il task successivo non nasce** (oc:8730, review del 19/10/2026): l'azione
  chiede a `TaskPolicy::create()` con la Quote del task, come l'API, senza ripetere gli stati
  chiusi. All'apertura il popup non ha campi e la conferma chiude il task e basta; se un titolo
  arriva lo stesso (pagina aperta da prima della chiusura della Quote) `handle()` risponde con un
  errore **prima** della transazione e il task resta aperto. Il controllo che conta è quello in
  `handle()` (vedi la voce sull'`uriKey` fisso, sopra).
- **Nello storico delle azioni Nova una riapertura risulta «Segna come completato»**: all'esecuzione
  `name()` non conosce il task. Accettato: il modello `Task` non mostra lo storico.

## Come ci siamo arrivati

- **Assegnatario derivato da `quote->user`** (oc:8327, superato da oc:8722): nessuna colonna, tutti
  i task di una Quote avevano lo stesso assegnatario e una riassegnazione della Quote riscriveva
  retroattivamente anche i task completati. Abbandonato perché sulle trattative grandi lavorano
  due persone e ogni task deve avere il suo assegnatario.
- **Ripiego sull'owner calcolato alla lettura** (proposto nella pianificazione di oc:8722,
  scartato): un `assignee_id` vuoto avrebbe seguito l'owner. Scartato perché il default viene
  già scritto alla creazione: un secondo calcolo alla lettura avrebbe obbligato ogni query («I miei
  task», filtro, API) a due condizioni e spostato i task al cambio di owner senza che nessuno lo
  decidesse.
- **Default al creatore invece che all'owner** (proposta della pianificazione del 13/10/2026, presa
  dallo scrum di quel giorno e citata nel ticket; scartata dal reviewer il 14/10/2026): vale la
  call del 30/09, citata nel ticket, cioè l'owner della trattativa; il creatore solo se l'owner
  manca.
- **Cambio di stato riservato al creatore** (oc:8403, superato da oc:8722): un task con creatore
  cancellato non era più completabile da nessuno, e un payload `{status, notes}` da un
  non-creatore falliva con 403 sull'intera richiesta. Superato da «chiunque crea i task e
  chiunque li può chiudere».
- **Task successivo creato anche su una Quote chiusa** (oc:8730, primo ciclo, superato dalla
  review del 19/10/2026): l'azione faceva come il form «Crea Task» di Nova, dove la policy non
  riceve la Quote. Abbandonato perché la stessa operazione dava esiti opposti da Nova e
  dall'API/skill Claude, e perché è lo scenario normale del cliente: chiudere una Quote non chiude
  i suoi task, quindi l'ultimo task di una trattativa vinta si chiude dopo. Il form «Crea Task» di
  Nova resta senza blocco.
- **Bug di scoping Task via `QuoteNoFilter`, noto e non risolto**: il bypass di `indexQuery()`
  scatta su `viaResource === 'quotes'`, ma l'uriKey di `QuoteNoFilter` è `quote-no-filters`,
  quindi il sub-panel Task raggiunto da Customer → tab Preventivi non ne beneficia. Preesistente,
  reso più probabile da scoprire da oc:8407 (Task è ora un tab di primo piano). Da ticket
  dedicato.
- **`docs/features/8402-.../overview.md` è scritto prima del merge di oc:8403** e non riflette
  il fatto che `TaskPolicy` sia il vero argine: il dettaglio è nel `notes.md` di quella feature.
