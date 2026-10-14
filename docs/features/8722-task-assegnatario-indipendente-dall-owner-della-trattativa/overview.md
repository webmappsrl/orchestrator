> Ticket: oc:8722

# Task: assegnatario indipendente dall'owner della trattativa

## Cosa cambia

Ogni Task collegato a una Quote ha un **proprio assegnatario**, salvato nel task e scelto in Nova
o via API. Se alla creazione nessuno lo sceglie, l'assegnatario è l'**owner della trattativa**;
se la trattativa non ha owner, è **chi crea il task**. Nel form «Crea Task» di Nova il campo
compare già compilato con questo default e si può cambiare prima di salvare. L'assegnatario resta
modificabile ma non si può svuotare; se l'utente assegnatario viene cancellato, i suoi task si
riassegnano con la stessa regola del default.

Il cambio di stato di un task (completa / riapri) non è più riservato a chi l'ha creato: lo può
fare chiunque abbia accesso ai task. Una nuova vista **«I miei task»** mostra i task assegnati
all'utente loggato e quelli creati da lui, con un filtro «Assegnati a me» / «Creati da me»
(default «Assegnati a me»). Il filtro «Assegnatario» dell'elenco Task filtra sull'assegnatario
del task e non più sull'owner della trattativa.

## Perché

Sulle trattative grandi lavorano due persone: una tiene i contatti via mail, l'altra fa le
presentazioni. Oggi tutti i task di una trattativa sono per forza dell'owner
(`Task::getAssigneeAttribute()`, `app/Models/Task.php:46-49`: `return $this->quote?->user;`), e
solo chi ha creato un task lo può chiudere (`app/Policies/TaskPolicy.php:98-101`,
`app/Nova/Actions/ToggleTaskCompleted.php:20-23`).

Fonti:
- ticket, `customer_request`: «ogni task deve poter avere un proprio assegnatario. Se non viene
  indicato, vale l'owner della trattativa. Chiunque può creare e chiudere i task di chiunque,
  senza controlli.»
- call del 30/09/2026 (citata nel ticket e nella descrizione del tag; trascrizione non
  verificabile, vedi Rischi): «l'assegnatario del task svincolato da l'assegnatario della
  trattativa»; «di default se non c'è messo niente prenda quello della trattativa».
- review della PR #266 (peppedeka, 14/10/2026) e description del ticket, sezione «Risposte del
  reviewer alle domande aperte — da implementare (review del 14/10/2026)»: ruoli confermati
  (punto 1), default owner → creatore (punto 2), vista «I miei task» e filtro Assegnatario sul
  task (punto 3), migrazione con la stessa regola del default (punto 4).
- aggiunta del reviewer al punto 2 (ticket, 14/10/2026; messaggio delle 10:17): «in Nova il form
  «Crea Task» mostra il campo Assegnatario già compilato con questo default, modificabile prima di
  salvare; lo stesso campo precompilato compare nel popup di chiusura di oc:8730».
- decisione della dev in pianificazione (14/10/2026): assegnatario obbligatorio, senza ripiego
  calcolato alla lettura; alla cancellazione dell'utente i task si riassegnano col default.

## Requisiti

**Dati**
- [ ] Nuova colonna `tasks.assignee_id`, FK verso `users` con `nullOnDelete`. Nullable a livello
  di database solo per i casi residui (vedi «Cancellazione di un utente» e «Migrazione»); in Nova
  e nell'API il campo è obbligatorio.
- [ ] L'assegnatario è **solo** `assignee_id`: nessun ripiego sull'owner calcolato alla lettura.
  `Task::getAssigneeAttribute()` viene sostituito da una relazione `assignee()` verso `User`, così
  `$task->assignee` continua a funzionare per l'API e per l'indice Nova.
- [ ] **Default in un solo punto**, riusabile da oc:8730 (popup di chiusura con task successivo):
  un metodo che, data una Quote e l'utente che agisce, restituisce l'owner della Quote oppure,
  se manca, l'utente che agisce. Un owner senza ruolo ammesso conta come mancante (il campo Owner
  della Quote non filtra i ruoli, `app/Nova/Quote.php:166-169`). Lo usano l'hook di creazione, il campo Nova e la riassegnazione
  alla cancellazione.
- [ ] **Default alla creazione:** nell'hook `creating` di `Task::booted()`, dopo l'assegnazione di
  `creator_id` (`app/Models/Task.php:105-111`), se `assignee_id` è vuoto si applica il default.
  Vale per Nova, API e `Task::create()`.
- [ ] **Migrazione dei task esistenti** (migration a sé, separata da quella della colonna):
  `assignee_id` = owner della trattativa; se manca, `creator_id`. I task senza owner e senza
  creatore restano con `assignee_id` vuoto. Prima del deploy rifare in produzione il conteggio
  (sola lettura), per sapere quanti task finiscono a chi:
  ```sql
  SELECT count(*) FILTER (WHERE q.user_id IS NOT NULL)                         AS all_owner,
         count(*) FILTER (WHERE q.user_id IS NULL AND t.creator_id IS NOT NULL) AS al_creatore,
         count(*) FILTER (WHERE q.user_id IS NULL AND t.creator_id IS NULL)     AS vuoti
  FROM tasks t JOIN quotes q ON q.id = t.quote_id;
  ```
- [ ] **Cancellazione di un utente:** prima della cancellazione (evento `deleting` di `User`), i
  task di cui è assegnatario si riassegnano col default: owner della trattativa, altrimenti
  creatore del task. Se anche il creatore è l'utente cancellato e la trattativa non ha owner, il
  campo resta vuoto (`nullOnDelete`). Una cancellazione che non passa da Eloquent salta l'hook e
  lascia il campo vuoto.

**Chi può essere assegnatario**
- [ ] Solo utenti con ruolo Admin, Manager o Developer, cioè chi accede ai task. Editor e Customer
  esclusi. *Fonte: review, punto 1.*
- [ ] La lista dei ruoli che accedono ai task è definita **in un solo punto** e usata da
  `TaskPolicy::before()`, dalle opzioni del campo Nova e dalla validazione API.

**Permessi**
- [ ] Completare e riaprire un task (`TaskPolicy::updateStatus`, `ToggleTaskCompleted`) è
  permesso a ogni Admin, Manager e Developer, non più solo al creatore. *Fonte: ticket «Chiunque
  può creare e chiudere i task di chiunque»; review, punto 1.*
- [ ] Cambiare l'assegnatario segue la regola di `notes` e `due_date` (`TaskPolicy::update()`):
  qualsiasi Admin, Manager o Developer.

**Lista dei task dell'utente (`Task::scopeForUser()`)**
- [ ] `scopeForUser()` restituisce i task con `assignee_id` = utente **oppure** `creator_id` =
  utente. L'owner della trattativa non vede più un task della sua trattativa assegnato a un altro
  e creato da un altro. *Fonte: review, punto 3.*
- [ ] Conseguenza (nessuna scelta nuova): l'elenco Task generale di un Developer, che oggi usa
  `scopeForUser()` (`app/Nova/Task.php:246`), mostra gli stessi task. Admin e Manager continuano a
  vedere tutto.

**Nova**
- [ ] Campo Assegnatario nel form del Task: `BelongsTo` verso User, obbligatorio, ricercabile, con
  le sole opzioni dei ruoli ammessi, in **entrambi i rami** di `App\Nova\Task::fields()`
  (`app/Nova/Task.php:34`): form della vista globale e form aperto dal tab Task della Quote.
- [ ] **Precompilato in creazione** con il default. Dal tab Task della Quote la trattativa è già
  nota. Nella vista globale la trattativa si sceglie nello stesso form
  (`app/Nova/Task.php:63-65`): l'Assegnatario si calcola dopo la scelta e si ricalcola se la
  trattativa cambia (`dependsOn`, già usato in `app/Nova/Quote.php:190`). Il ricalcolo vale **solo
  in creazione**: nel form di modifica non tocca mai l'assegnatario salvato. Cambiare trattativa
  durante la creazione rimette il default della nuova trattativa anche se l'assegnatario era stato
  scelto a mano. *Fonte: aggiunta del reviewer al punto 2.*
- [ ] La colonna Assegnatario nell'indice mostra `assignee`.
- [ ] `TaskAssigneeFilter` filtra su `tasks.assignee_id`; le opzioni sono gli utenti assegnatari
  di almeno un task. *Fonte: review, punto 3.*
- [ ] **Nuova vista «I miei task»**: resource che estende `App\Nova\Task`, sul modello di
  `AssignedToMeStory` (`app/Nova/AssignedToMeStory.php`), con `indexQuery` su `scopeForUser()`
  dell'utente loggato per ogni ruolo, ordinata per `due_date`. Filtro a due valori «Assegnati a
  me» / «Creati da me», default «Assegnati a me» (select con `default()`, come
  `ShardStatusFilter`). Voce di menu nella sezione CRM, subito dopo Task
  (`app/Providers/NovaServiceProvider.php:130-133`). *Fonte: review, punto 3.*
- [ ] La creazione dei task resta invariata: chiunque acceda ai task li crea su qualsiasi Quote,
  tranne quelle chiuse (`TaskPolicy::create()`).
- [ ] Nella **lista** del tab Task della Quote non cambia nulla: niente colonna né filtro
  Assegnatario, come oggi (decisione di oc:8402, `docs/knowledge/task-e-quote.md`).

**API**
- [ ] `POST /api/tasks` accetta `assignee_id` facoltativo (`exists:users,id`, ruolo ammesso;
  altrimenti 422). Se assente o `null` si applica il default.
- [ ] `PATCH /api/tasks/{task}` accetta `assignee_id` (`required` se presente, `exists`, ruolo
  ammesso): non si può svuotare.
- [ ] `GET /api/tasks` restituisce `scopeForUser()`: task assegnati all'utente del token oppure
  creati da lui. Nessun parametro nuovo. *Fonte: review, «Requisiti … per l'API `GET /api/tasks`,
  che oggi restituisce owner + creatore».*
- [ ] La risposta JSON **non cambia forma**: `assignee` = `{id, name, email}` oppure `null`. Il
  contratto è consumato fuori da questo repo (skill Cowork; server MCP `orchestrator` di
  `claude-marketplace`).
- [ ] Aggiornare i docblock Scramble di `TaskController` che descrivono la regola vecchia
  (`index`, `show`: «owns the linked quote OR is the task creator»; `update`: «`status` requires
  the authenticated user to be the task's creator») e il docblock di `TaskPolicy::updateStatus()`.

**Traduzioni**
- [ ] La chiave `Assignee` esiste già (`lang/it.json`, `lang/en.json`). Le stringhe nuove (titolo
  della vista, nome e valori del filtro) vanno in entrambi i file.

**Test**
- [ ] Aggiornare i test delle regole vecchie: «solo il creatore cambia lo stato»
  (`TaskApiTest::non_creator_non_puo_cambiare_status`, `TaskPolicyTest`,
  `TaskTest::test_toggle_action_authorized_only_for_creator`) e «assegnatario = owner»
  (`TaskTest::test_assignee_accessor_resolves_via_quote_user`,
  `TaskTest::test_for_user_scope_filters_by_quote_owner`,
  `TaskNovaResourceTest::test_assignee_filter_apply_filters_by_quote_owner`,
  `TaskNovaResourceTest::test_assignee_filter_options_only_include_users_with_assigned_tasks`,
  `TaskApiTest::index_assignee_null_se_quote_senza_owner`).
- [ ] Aggiungere: default all'owner e al creatore (Nova, API, anche con `null` in POST),
  migrazione, riassegnazione alla cancellazione dell'utente, 422 su ruolo non ammesso e su
  `assignee_id: null` in PATCH, `scopeForUser` su assegnatario e creatore, vista «I miei task» con
  il filtro e il suo default, filtro Assegnatario su `assignee_id`, campo presente e precompilato
  in entrambi i rami del form.

## Rischi

- **Documento della call del 30/09 non verificato.** La fonte del tag e il suo notebook non sono
  condivisi con l'account della dev: le citazioni del 30/09 vengono dalla descrizione del tag e
  dal ticket. Le decisioni del reviewer del 14/10 le confermano.
- **Conteggio della migrazione solo in produzione.** La copia locale oggi ha 1 task: i numeri
  della review (29 task al 28/08) non sono più riproducibili in locale. Il conteggio in produzione
  prima del deploy è l'unica verifica.
- **Il cambio di owner della trattativa non sposta i task** (emerso in challenge). Oggi
  l'assegnatario si calcola dalla trattativa, quindi cambiare owner sposta tutti i task; dopo
  questo ticket i task restano a chi è scritto. È la conseguenza della decisione del reviewer
  (valore scritto alla creazione, «svincolato» dalla trattativa): chi cambia owner riassegna a mano
  i task aperti.
- **Riassegnazione alla cancellazione: caso quasi teorico** (emerso in challenge). Un utente non
  si può cancellare se è owner di una trattativa o creatore di una Story: `quotes.user_id` e
  `stories.creator_id` hanno FK senza regola di cancellazione
  (`database/migrations/2023_09_25_123252_add_user_id_to_quotes_table.php:15`,
  `database/migrations/2023_10_06_071507_add_creator_id_to_stories_table.php:23`). La logica resta
  per decisione della dev, coperta da test, e gira nella stessa transazione della cancellazione.
- **Owner senza ruolo ammesso** (emerso in challenge): il campo Owner della Quote accetta
  qualsiasi utente. Nei dati locali 0 owner su 4 sono fuori dai ruoli ammessi; il default li
  tratta come owner mancante.
- **Precompilazione nella vista globale.** Il ricalcolo con `dependsOn` va limitato alla creazione
  e verificato nel form reale, non solo nei test.
- **Toggle concorrente:** l'azione «Segna come completato / Riapri» inverte lo stato che trova nel
  DB. Aperta a tutti, due persone con la pagina non aggiornata possono annullarsi a vicenda. Caso
  ipotetico, lasciato com'è (stessa decisione del reviewer su oc:8730).
- **Server MCP `orchestrator` (repo `claude-marketplace`) da allineare:** il tool sui task descrive
  `status` come «modificabile solo da chi lo ha creato» e non espone `assignee_id`. Follow-up in
  quel repo.
- **Il comportamento della skill Cowork cambia senza che la risposta cambi forma:** `GET
  /api/tasks` restituisce task diversi (assegnati + creati invece di owner + creati) e un `PATCH`
  di `status` da un non creatore non dà più 403.
- **Rollback:** il `down()` della migration della colonna cancella tutte le assegnazioni; un
  rollback del solo codice, lasciando la colonna, è sicuro. I task chiusi da non creatori nel
  periodo restano chiusi.

## Out of scope

- Storico delle assegnazioni (chi ha assegnato cosa e quando).
- Notifiche all'assegnatario.
- Creazione del task successivo alla chiusura e campo Assegnatario nel popup di chiusura
  (oc:8730, che dipende da questo ticket).
- Allineamento del server MCP `orchestrator` in `claude-marketplace`: altro repo.
- Il bug di scoping del sub-panel Task raggiunto via `QuoteNoFilter`
  (`docs/knowledge/task-e-quote.md`, «Come ci siamo arrivati»).

## Moduli toccati

Tutto nel repo principale `orchestrator` (nessun submodule):

- `database/migrations/<nuova>_add_assignee_id_to_tasks_table.php` (nuovo): colonna e FK
- `database/migrations/<nuova>_backfill_tasks_assignee_id.php` (nuovo): migrazione dei task
  esistenti
- `app/Models/Task.php`: `$fillable`, relazione `assignee()` al posto dell'accessor, metodo del
  default, `scopeForUser()`, default in `booted()`
- `app/Models/User.php`: relazione verso i task assegnati, riassegnazione in `deleting`, punto
  unico della lista dei ruoli che accedono ai task (o costante su `TaskPolicy`)
- `app/Policies/TaskPolicy.php`: `before()` sulla lista unica dei ruoli, `updateStatus()`
- `app/Nova/Actions/ToggleTaskCompleted.php`: `authorizedToRun()`
- `app/Nova/Task.php`: campo Assegnatario precompilato in entrambi i rami, eager load di
  `assignee` in `indexQuery()`
- `app/Nova/MyTask.php` (nuovo): vista «I miei task»
- `app/Nova/Filters/MyTaskScopeFilter.php` (nuovo): filtro «Assegnati a me» / «Creati da me»
- `app/Nova/Filters/TaskAssigneeFilter.php`: query e opzioni su `assignee_id`
- `app/Providers/NovaServiceProvider.php`: voce di menu
- `app/Http/Requests/Api/TaskApiRequest.php`: regola `assignee_id`
- `app/Http/Controllers/Api/TaskController.php`: store/update di `assignee_id`, eager load, docblock
- `lang/it.json`, `lang/en.json`: stringhe della vista e del filtro
- test in `tests/Feature/Api/TaskApiTest.php`, `tests/Feature/Api/TaskPolicyTest.php`,
  `tests/Feature/TaskTest.php`, `tests/Feature/TaskNovaResourceTest.php`
- `docs/knowledge/task-e-quote.md`: aggiornamento a fine lavoro
