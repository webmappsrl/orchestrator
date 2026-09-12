# Task collegati alle Quote

Replica della feature Task di HubSpot: promemoria (`title`, `notes` Tiptap, `due_date`,
`status`) legati a una Quote, in Nova e via API.

## Stato attuale

### Modello e autorizzazioni (oc:8327, oc:8403)
- **L'assegnatario non è mai persistito**: è sempre derivato da `quote->user` tramite
  l'accessor `Task::assignee`. Nessun campo `user_id`/`assigned_to` sul Task. Conseguenza: una
  riassegnazione della Quote riscrive retroattivamente la paternità percepita di tutti i suoi
  Task, anche quelli già completati — nessun audit trail storico. Tutti i task della stessa
  quote condividono necessariamente lo stesso assegnatario.
- **Chiunque abbia accesso a Nova può creare un Task su qualsiasi Quote**, anche non propria e
  anche senza `user_id` valorizzato (135 Quote su 190, il 71% — locale, 2026-09-12). Diverge dal blocco
  inizialmente pianificato, deciso in corso d'opera.
- **`Task::scopeForUser()` filtra su owner della Quote OPPURE `creator_id`**: senza la seconda
  condizione un Task creato su una Quote altrui o senza owner sarebbe invisibile per sempre
  anche a chi lo ha creato.
- **`creator_id`** è FK nullable verso `users` con `nullOnDelete`, valorizzata in
  `Task::booted()` e **mai accettata in input** dall'API (whitelist in `TaskApiRequest`).
  Se l'utente creatore viene eliminato il task resta orfano permanente: non è più
  completabile/riapribile da nessuno. Limite ereditato da `ToggleTaskCompleted`.
- **`onDelete('cascade')` su `quote_id`**: eliminare una Quote elimina silenziosamente tutti i
  suoi Task. Accettato, non hanno valore autonomo.
- **`TaskPolicy::create(User $user, ?Quote $quote = null)` ha `$quote` opzionale**: Nova chiama
  `Gate::authorize('create', Task::class)` con un solo argomento per decidere se mostrare
  l'azione "crea". Una firma a due parametri obbligatori rompe **l'intera creazione di Task via
  Nova** con `ArgumentCountError`. Quando `$quote` è assente il blocco su quote chiuse non si
  applica; il controller API passa sempre la Quote esplicitamente.
- **Autorizzazione differenziata per campo sul `PATCH /api/tasks/{task}`**: `status` solo se
  `creator_id === utente loggato` (via `TaskPolicy::updateStatus()`, mirror di
  `ToggleTaskCompleted::authorizedToRun()`), `notes` per qualsiasi Admin/Manager/Developer
  (`TaskPolicy::update()`, mirror di `Story::addDevNote()`). Il controller verifica
  `updateStatus` **prima** di applicare qualsiasi modifica: un payload `{status, notes}` da un
  non-creator fallisce con 403 sull'intera richiesta. Pattern non standard rispetto al resto del
  progetto (Quote e Tag hanno un solo verdetto per endpoint), documentato nei docblock.
- **`GET /api/tasks/{task}` è ruolo-only, `GET /api/tasks` è scoped** via `scopeForUser()`:
  asimmetria intenzionale, coerente col resto del progetto — la lista filtra "i miei task", il
  dettaglio (raggiungibile solo conoscendo l'id) resta visibile a chi ha un ruolo abilitato.
- **Nessuna paginazione e nessun `?include=` opt-in** sull'API Task: ogni Task include sempre
  `assignee` (spesso `null`), `quote_id` e `quote_title`. Il contratto è consumato da un client
  esterno (skill Cowork) fuori da questo repo: introdurre paginazione o opt-in sarebbe un
  breaking change cross-repo. Nessun `DELETE`.
- **`Task::appendNote()` prepende, non accoda**, mirror del comportamento reale di
  `Story::addDevNote()` (che nonostante il nome storico prepende sempre).

### Viste Nova (oc:8327, oc:8402)
- **Vista globale come Nova Filter, non Kanban né Lens**: le colonne
  scaduto/oggi/imminente/completato sono proiezioni calcolate da una data, non stati persistiti,
  quindi un drag&drop fra loro non avrebbe un'azione di scrittura naturale. Confermato dal
  comportamento reale di HubSpot (fonte del requisito): tab di filtro più tabella.
- **`fields()`, `filters()` e `indexQuery()` vanno tutti scoped su `$request->viaResource === 'quotes'`**:
  Nova richiama gli stessi metodi per la vista globale e per il sub-panel Task dentro il
  dettaglio Quote. Scopare solo `indexQuery()` fa trapelare riordino colonne e colonna/filtro
  Assegnatario anche nel sub-panel. I campi condivisi fra i due rami stanno in tre metodi privati
  (`statusBadgeField()`, `completedField()`, `notesField()`).
- Nel sub-panel lo scoping "solo i miei/creati da me" va **bypassato**, altrimenti il sub-panel
  di una Quote non propria risulta vuoto pur avendo Task.
- **`indexQuery()` bypassa `forUser()` solo per Admin/Manager**: la vista globale è nel menu solo
  per loro; rimuovere `forUser()` per tutti avrebbe esposto l'intero backlog task (note interne
  comprese) a Customer ed Editor con accesso Nova generico. Il vero argine per quei ruoli è però
  `TaskPolicy` (403 prima ancora di `indexQuery()`), non `forUser()`.
- **Il badge di urgenza confronta solo la data, mai l'ora** (`now()->startOfDay()` fresco, senza
  mutare l'attributo `due_date`): un confronto su datetime completo classificava come "scaduto"
  un task in scadenza oggi non appena l'orario corrente superava quello di scadenza,
  disallineandosi dalla semantica a livello di giorno usata da scope e filtro.
- **Azione "Segna come completato/Riapri" solo per il creatore**, e sostituisce l'azione
  "Replica" di default (`authorizedToReplicate() => false`).
- **`App\Nova\Quote::title()` ha il fallback `$this->name ?: $this->title`**: la Resource usa
  `$title = 'name'`, ma `name` è un fillable morto (vedi [Quote: API, PDF e viste Nova](quote-api-e-pdf.md)) —
  senza fallback il `BelongsTo` verso Quote nel form Task mostra un'etichetta vuota.

## Come ci siamo arrivati

- **Bug di scoping Task via `QuoteNoFilter`, noto e non risolto**: il bypass di `indexQuery()`
  scatta su `viaResource === 'quotes'`, ma l'uriKey di `QuoteNoFilter` è `quote-no-filters`,
  quindi il sub-panel Task raggiunto da Customer → tab Preventivi non ne beneficia. Preesistente,
  reso più probabile da scoprire da oc:8407 (Task è ora un tab di primo piano). Da ticket
  dedicato.
- **`docs/features/8402-.../overview.md` è scritto prima del merge di oc:8403** e non riflette
  il fatto che `TaskPolicy` sia il vero argine: il dettaglio è nel `notes.md` di quella feature.
