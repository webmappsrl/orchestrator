> Ticket: oc:8722

# Task: assegnatario indipendente dall'owner della trattativa

## Cosa cambia

Ogni Task collegato a una Quote può avere un **proprio assegnatario**, scelto in Nova o via API.
Se alla creazione nessuno lo sceglie, l'assegnatario è **chi crea il task**; si può cambiare in
ogni momento. I task senza assegnatario seguono l'owner della trattativa: i task vecchi non
toccati dalla migrazione (domanda aperta 4), quelli il cui assegnatario è stato cancellato o
rimosso, quelli senza creatore.
Il cambio di stato di un task (completa / riapri) non è più riservato a chi l'ha creato: lo può
fare chiunque abbia accesso ai task. La lista "i miei task" e il filtro assegnatario di Nova
seguono l'assegnatario effettivo.

## Perché

Sulle trattative grandi lavorano due persone: una tiene i contatti via mail, l'altra fa le
presentazioni. Oggi tutti i task di una trattativa sono per forza dell'owner
(`Task::getAssigneeAttribute()`, `app/Models/Task.php:46-49`), e solo chi ha creato un task lo
può chiudere (`app/Policies/TaskPolicy.php:98-101`, `app/Nova/Actions/ToggleTaskCompleted.php:20-23`).

Fonti:
- ticket, `customer_request`: «ogni task deve poter avere un proprio assegnatario. Se non viene
  indicato, vale l'owner della trattativa. Chiunque può creare e chiudere i task di chiunque,
  senza controlli.»
- call del 30/09/2026 (citata nel ticket e nella descrizione del tag; trascrizione non
  verificabile, vedi Rischi): «l'assegnatario del task svincolato da l'assegnatario della
  trattativa»; «di default se non c'è messo niente prenda quello della trattativa».
- scrum del 13/10/2026 08:36, Giuseppe Bonfanti (verificata su Drive): «chiunque può creare e
  chiudere i task di chiunque. Prima non era così»; «quindi uno glielo chiude, glielo può aprire
  e via dicendo»; «ogni volta che crei un task di default va assegnata al creatore, ma lo puoi
  cambiare».

## Requisiti

**Dati**
- [ ] Nuova colonna `tasks.assignee_id`, nullable, FK verso `users` con `nullOnDelete`
  (stesso schema di `creator_id`).
- [ ] **Default al creatore:** alla creazione, se `assignee_id` non è indicato, vale
  `creator_id` (in Nova l'utente loggato, via API l'utente del token). *Fonte: scrum del
  13/10/2026 08:36, Giuseppe Bonfanti: «ogni volta che crei un task di default va assegnata al
  creatore, ma lo puoi cambiare». Il ticket diceva invece «Se non viene indicato, vale l'owner
  della trattativa»: decisione della dev di seguire lo scrum, da confermare in review (domanda
  aperta 2).*
- [ ] **Migrazione dei task esistenti:** in una migration a sé (separata da quella che aggiunge la
  colonna), così la scelta del reviewer (domanda aperta 4) cambia solo la condizione `WHERE`.
  Punto di partenza implementato: **solo i task delle trattative senza owner**
  (`assignee_id = creator_id` dove `creator_id` non è nullo e `quotes.user_id` è nullo). I task
  vecchi delle trattative con owner restano senza `assignee_id` e seguono l'owner come oggi.
  *Scelta come punto di partenza perché si può estendere a tutti i task senza perdere nulla; la
  decisione finale è del reviewer.*
- [ ] L'assegnatario effettivo è `assignee_id` se valorizzato, altrimenti `quote->user`.
  L'accessor `assignee` restituisce l'effettivo. Il ripiego sull'owner vale per i task senza
  `assignee_id`: task vecchi non toccati dalla migrazione, task senza creatore, assegnatario
  cancellato (`nullOnDelete`), o reset esplicito a `null`.
- [ ] Il default al creatore si applica nell'hook `creating` di `Task::booted()`, dopo
  l'assegnazione di `creator_id` (`app/Models/Task.php:107-110`), così vale per Nova, API e
  `Task::create()`.
  *Motivo per non fermarsi all'owner: una Quote creata via API non ha mai owner
  (`QuoteController::store()`, `app/Http/Controllers/Api/QuoteController.php:201-211`, nessun
  `user_id` in `QuoteApiRequest`), e in Nova l'owner è facoltativo
  (`app/Nova/Quote.php:166-169`).*

**Chi può essere assegnatario**
- [ ] Si possono scegliere solo utenti con ruolo Admin, Manager o Developer, cioè chi può vedere
  i task. *Decisione della dev in pianificazione; ticket e call non lo specificano.*
- [ ] La lista dei ruoli che accedono ai task è definita **in un solo punto** e usata da
  `TaskPolicy::before()`, dalle opzioni del campo Nova e dalla validazione API: per estenderla a
  un altro ruolo si cambia solo lì. *Decisione della dev in pianificazione.*

**Permessi**
- [ ] Completare e riaprire un task (`TaskPolicy::updateStatus`, `ToggleTaskCompleted`) è
  permesso a ogni utente che accede ai task (Admin, Manager, Developer), non più solo al creatore.
  Editor e Customer restano esclusi come oggi da `TaskPolicy::before()`. *Fonte: ticket «aperti a
  ogni utente commerciale invece del solo creator»; scrum 13/10 «chiunque può creare e chiudere i
  task di chiunque». Decisione della dev: "chiunque" = chi oggi accede ai task.*
- [ ] Cambiare l'assegnatario segue la stessa regola di `notes` e `due_date`
  (`TaskPolicy::update()`): qualsiasi Admin/Manager/Developer.

**"I miei task"**
- [ ] `Task::scopeForUser()` restituisce i task di cui l'utente è **assegnatario effettivo**
  (`assignee_id` = utente, oppure `assignee_id` vuoto e owner della Quote = utente) **oppure
  creatore**. L'owner della trattativa non vede più un task della sua trattativa assegnato
  esplicitamente a un altro. *Decisione della dev in pianificazione; il ticket non ne parla.*

**Nova**
- [ ] Campo Assegnatario nel form del Task: `BelongsTo` verso User, nullable, ricercabile, con le
  sole opzioni dei ruoli ammessi. Lasciato vuoto in creazione = chi crea il task; svuotato in
  modifica = segue l'owner. Il campo è in **entrambi i rami** di
  `App\Nova\Task::fields()`: sia nel form della vista globale sia in quello aperto dal tab Task
  della Quote (`viaResource === 'quotes'`), da cui i task si creano di solito. *Fonte: ticket,
  «Campo in Nova»; emerso in challenge.*
- [ ] La colonna Assegnatario nell'indice mostra l'assegnatario effettivo (comportamento attuale,
  invariato per i task senza `assignee_id`).
- [ ] `TaskAssigneeFilter` filtra sull'assegnatario effettivo; le opzioni sono gli utenti che sono
  assegnatari effettivi di almeno un task. *Fonte: ticket, «Filtro assegnatario in Nova basato sul
  nuovo campo»; filtrare sull'effettivo (col ripiego sull'owner) invece che sulla sola colonna è
  decisione della dev, coerente con la colonna dell'indice.*
- [ ] La creazione dei task resta invariata: chiunque acceda ai task li crea su qualsiasi Quote,
  tranne quelle chiuse (`TaskPolicy::create()`), coerente con «Chiunque crea i task».
- [ ] Nella **lista** del tab Task della Quote non cambia nulla: niente colonna né filtro
  Assegnatario, come oggi (decisione di oc:8402, `docs/knowledge/task-e-quote.md`).

**API**
- [ ] `POST /api/tasks` e `PATCH /api/tasks/{task}` accettano `assignee_id` (nullable,
  `exists:users,id`, ruolo ammesso; altrimenti 422). In POST, senza `assignee_id` o con
  `assignee_id: null` vale l'utente del token. `assignee_id: null` in PATCH fa seguire al task
  l'owner della trattativa. *Fonte: ticket, «Campo in Nova e nell'API (store / update / index)».*
- [ ] Aggiornare i docblock Scramble di `TaskController` che descrivono la regola vecchia
  (`index`: «owns the linked quote OR is the task creator»; `update`: «`status` requires the
  authenticated user to be the task's creator»).
- [ ] La risposta JSON **non cambia forma**: `assignee` = `{id, name, email}` dell'assegnatario
  effettivo, oppure `null`. Nessun campo nuovo. *Decisione della dev in pianificazione: il
  contratto è consumato fuori da questo repo (skill Cowork; server MCP `orchestrator` di
  `claude-marketplace`, che restituisce la risposta così com'è e non legge `assignee`). Un campo
  `assignee_id` nella risposta, per distinguere "assegnato a mano" da "segue l'owner", si può
  aggiungere in seguito senza rompere i client.*
- [ ] `GET /api/tasks` usa il nuovo `scopeForUser()`.

**Traduzioni**
- [ ] La chiave `Assignee` esiste già (`lang/it.json:544`, `lang/en.json:756`); ogni nuova stringa
  va in entrambi i file (lingua di default `it`, fallback `en`).

**Test**
- [ ] Aggiornare i test che verificano le regole vecchie:
  - "solo il creatore cambia lo stato": `tests/Feature/Api/TaskApiTest.php` (`non_creator_non_puo_cambiare_status`),
    `tests/Feature/Api/TaskPolicyTest.php`, `tests/Feature/TaskTest.php`
    (`test_toggle_action_authorized_only_for_creator`, da riscrivere con utenti con ruolo);
  - "assegnatario = owner": `index_assignee_null_se_quote_senza_owner` (`TaskApiTest.php`),
    `test_assignee_filter_apply_filters_by_quote_owner` e
    `test_assignee_filter_options_only_include_users_with_assigned_tasks`
    (`tests/Feature/TaskNovaResourceTest.php`), `test_for_user_scope_filters_by_quote_owner`
    (`TaskTest.php`).
- [ ] Aggiungere: default al creatore (Nova e API, anche con `null` esplicito in POST),
  migrazione dei task esistenti, fallback all'owner, assegnatario esplicito, reset a `null` in
  PATCH, 422 su utente con ruolo non ammesso, `scopeForUser` con assegnatario esplicito, filtro
  Nova sull'assegnatario effettivo, campo presente in entrambi i rami del form Nova.

## Rischi

- **Documento della call del 30/09 non verificato.** La fonte del tag
  (`1VA2MqI-ADkYRIbrQZ5xFvj9WxSe7Cr01nMPsK_glTow`) e il suo notebook non sono condivisi con
  l'account della dev: le citazioni del 30/09 vengono dalla descrizione del tag e dal ticket, non
  dalla trascrizione. Richiesto l'accesso a Giuseppe Bonfanti.
- **L'owner perde di vista i task assegnati ad altri** nella lista "i miei task" (API e Nova per i
  Developer). Il quadro completo resta nel tab Task dentro la Quote; chi lavora solo dalla skill
  non ha questo ripiego. Vedi domande aperte.
- **Campo mancante nel form del tab Quote** (emerso in challenge): `App\Nova\Task::fields()` ha due
  rami (`app/Nova/Task.php:34`); il campo Assegnatario va in entrambi. Recepito nei Requisiti.
- **Trattative senza owner** (emerso in challenge): le Quote create via API non hanno mai owner
  (`app/Http/Controllers/Api/QuoteController.php:201-211`), quindi un default all'owner
  lascerebbe i task senza assegnatario. Mitigato col default al creatore (domanda aperta 2).
- **Task vecchi e nuovi seguono regole diverse** (emerso nella review dell'overview): con la
  migrazione parziale, i task vecchi delle trattative con owner seguono l'owner, i nuovi il
  creatore. Con la migrazione completa, invece, l'owner perderebbe i task creati da altri.
  Scelta lasciata al reviewer (domanda aperta 4).
- **Toggle concorrente** (emerso in challenge): l'azione "Segna come completato / Riapri"
  inverte lo stato che trova nel DB (`ToggleTaskCompleted::handle()`). Aperta a tutti, due
  persone con la pagina non aggiornata possono annullarsi a vicenda (A completa, B clicca per
  completare e il task si riapre) senza traccia. Non mitigato per scelta della dev, per restare nel
  perimetro del ticket; la correzione possibile è dividerla in due azioni con stato esplicito.
- **Server MCP `orchestrator` (repo `claude-marketplace`) da allineare:** il tool sui task descrive
  `status` come «modificabile solo da chi lo ha creato»
  (`plugins/wm-skills/mcp/internal/tools/tasks.go:25`) e non espone `assignee_id`. Dopo questo
  ticket la descrizione è falsa e dal tool non si assegna un task. Fuori perimetro (altro repo):
  follow-up.
- **In Nova la spunta "Completato" del form cambiava già lo stato per chiunque potesse modificare
  il task** (`app/Nova/Task.php:116-126`): la restrizione al creatore valeva solo per l'azione e per
  l'API. Dopo questo ticket le tre strade si comportano allo stesso modo.
- **Rollback:** il `down()` della migration cancella le assegnazioni esplicite; un rollback del
  solo codice, lasciando la colonna, è sicuro. I task chiusi da non creatori nel periodo aperto
  restano chiusi.
- **Il comportamento della skill Cowork cambia senza che la risposta cambi**: `GET /api/tasks`
  mostrerà task diversi (scoping per assegnatario effettivo) e un `PATCH` di `status` da un non
  creatore non darà più 403.

## Domande aperte per il reviewer

1. **"Chiunque" e i ruoli.** Il cambio di stato è aperto ad Admin, Manager e Developer (chi già
   accede ai task). Va bene così, o va esteso anche ad altri ruoli (Editor)? In quel caso si
   cambia la lista unica dei ruoli. La stessa lista limita chi si può scegliere come
   assegnatario: l'API risponde 422 a un `assignee_id` con ruolo diverso. *Fonte: ticket «senza
   controlli»; scrum 13/10 «chiunque può creare e chiudere i task di chiunque».* Nel DB locale
   i commerciali noti hanno già questi ruoli (Alessio Piccioli e Giuseppe Bonfanti:
   `admin, developer, manager, editor`); Nicola Verdini non è nella copia locale: se in
   produzione è Admin, Manager o Developer, la domanda si chiude.
2. **Conferma: default al creatore invece che all'owner.** L'idea è presa dallo scrum di
   stamattina (13/10/2026 08:36), dove Giuseppe Bonfanti ha detto: «ogni volta che crei un task di
   default va assegnata al creatore, ma lo puoi cambiare». Il ticket (call del 30/09) diceva
   invece «se non c'è messo niente prenda quello della trattativa». Implementato: creatore per i
   task nuovi; per i task esistenti vedi domanda 4. Motivo in più: le trattative
   create dalla skill non hanno owner, quindi col default all'owner i loro task resterebbero
   senza assegnatario. Va confermato con chi ha chiesto la funzione?
3. **L'owner deve vedere tutti i task delle sue trattative** in "i miei task", anche quelli
   assegnati ad altri? Implementato: no. Né il ticket né le call ne parlano.
4. **Quali task esistenti migrare al creatore?** Emerso nella review dell'overview. Oggi ogni
   task risulta dell'owner della trattativa (`app/Models/Task.php:48`). Due strade:
   - **migrazione parziale** (implementata come punto di partenza): solo i task delle trattative
     senza owner, che oggi non sono di nessuno. Nessuno perde task che vede oggi; i task vecchi
     con owner seguono l'owner, i nuovi il creatore.
   - **migrazione completa:** tutti i task con un creatore. Una regola sola, ma ogni task creato da
     una persona diversa dall'owner passa al creatore, e l'owner lo perde da "i miei task" senza
     che nessuno l'abbia riassegnato. Prima di sceglierla, verificare quanti task sono coinvolti
     in produzione (sola lettura):
     ```sql
     SELECT count(*) FROM tasks t JOIN quotes q ON q.id = t.quote_id
     WHERE q.user_id IS NOT NULL AND t.creator_id IS NOT NULL AND t.creator_id <> q.user_id;
     ```
   Per passare dall'una all'altra si cambia solo la condizione della migration di backfill. Se la
   query restituisce 0 le due strade coincidono e la domanda si chiude da sola.

## Out of scope

- Storico delle assegnazioni (chi ha assegnato cosa e quando).
- Notifiche all'assegnatario.
- Creazione automatica di un nuovo task alla chiusura (oc:8730).
- Allineamento del server MCP `orchestrator` in `claude-marketplace` (descrizione di `status`,
  parametro `assignee_id`): altro repo, da ticket dedicato (vedi Rischi).
- Divisione dell'azione "Segna come completato / Riapri" in due azioni con stato esplicito (vedi
  Rischi, toggle concorrente).
- Il bug di scoping del sub-panel Task raggiunto via `QuoteNoFilter`
  (`docs/knowledge/task-e-quote.md`, «Come ci siamo arrivati»).

## Moduli toccati

Tutto nel repo principale `orchestrator` (nessun submodule):

- `database/migrations/<nuova>_add_assignee_id_to_tasks_table.php` (nuovo) — colonna e FK
- `database/migrations/<nuova>_backfill_tasks_assignee_id.php` (nuovo) — migrazione dei task
  esistenti, condizione secondo la domanda aperta 4
- `app/Models/Task.php` — `$fillable`, relazione `assigneeUser`, accessor `assignee`,
  `scopeForUser()`, default al creatore in `booted()`
- `app/Models/User.php` — relazione inversa verso i task assegnati (opzioni del filtro)
- `app/Policies/TaskPolicy.php` — `before()` sulla lista unica dei ruoli, `updateStatus()`
- `app/Nova/Actions/ToggleTaskCompleted.php` — `authorizedToRun()`
- `app/Nova/Task.php` — campo Assegnatario in entrambi i rami del form, eager load di
  `assigneeUser` in `indexQuery()`
- `app/Nova/Filters/TaskAssigneeFilter.php` — query e opzioni sull'assegnatario effettivo
- `app/Http/Requests/Api/TaskApiRequest.php` — regola `assignee_id`
- `app/Http/Controllers/Api/TaskController.php` — store/update di `assignee_id`, eager load, docblock
- punto unico della lista dei ruoli (es. costante su `TaskPolicy` o metodo su `User`)
- `lang/it.json`, `lang/en.json` — solo se servono stringhe nuove
- test in `tests/Feature/Api/TaskApiTest.php`, `tests/Feature/Api/TaskPolicyTest.php`,
  `tests/Feature/TaskTest.php`, `tests/Feature/TaskNovaResourceTest.php`
- `docs/knowledge/task-e-quote.md` — aggiornamento a fine lavoro
