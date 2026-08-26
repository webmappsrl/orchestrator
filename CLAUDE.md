# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Orchestrator is a Laravel 10 project-management tool built on **Laravel Nova** (admin panel). It manages Stories, Epics, Milestones, Customers, Apps, Quotes, and Deadlines. The app integrates with external tools and exposes a Nova-based dashboard for internal project management.

## Development Commands

All commands run **inside the Docker container** (`php81_orchestrator`):

```bash
# Enter container
docker exec -it php81_orchestrator bash

# Run migrations
php artisan migrate

# Run tests (uses the orchestrator_test support DB, see ## Testing)
php artisan test
php artisan test --filter=TestClassName

# Clear caches
php artisan config:clear && php artisan optimize

# Queue worker (local dev)
php artisan queue:work

# Horizon (production queue management)
bash scripts/launch_horizon.sh
```

Frontend assets:
```bash
npm run dev    # Vite dev server
npm run build  # Production build
```

Deploy scripts: `scripts/deploy_dev.sh`, `scripts/deploy_prod.sh`

## Architecture

### Core Stack
- **Laravel 10** + **Laravel Nova** (primary UI — all admin views are Nova Resources/Actions/Lenses)
- **Laravel Horizon** + **Redis** for queue management (dedicated `reports` queue for PDF generation)
- **PostgreSQL + PostGIS** as the database
- **Spatie Media Library** for file handling

### Key Domain Models
- **Story** — central entity; has status lifecycle, belongs to Epic/Milestone/Customer; triggers email notifications on status change via `SendStatusUpdateMailJob`
- **Epic** → **Milestone** → **Story** — project hierarchy
- **App** — mobile app configurations (iOS bundle_id / Android package); used for PDF report generation via Python scripts
- **Quote / Deadline** — commercial/sales management

### Nova Layer (`app/Nova/`)
Each model has a corresponding Nova Resource. Nova is the primary interface. Custom components include:
- **Kanban board** — custom Nova component in `nova-components/kanban-card/`
- **Lenses** — filtered views for Backlog, Developer stories, Customer stories, etc.
- **Actions** — bulk operations on resources
- **Metrics / Dashboards** — in `app/Nova/Metrics/` and `app/Nova/Dashboards/`

### Submodules
- `wm-package/` — shared Webmapp Laravel package (models/helpers)
- `nova-components/kanban-card/` — Vue-based Kanban Nova component
- `wm-reports/` — Python scripts for PDF app report generation (`genera_report_app.py`, `store_api.py`)

### Background Jobs
- `GenerateAppReportJob` — dispatches to `reports` queue, runs Python PDF generation
- `SendStatusUpdateMailJob` — sends email on Story status change
- `SendDigestEmail` — periodic digest emails

### Routes
- `routes/web.php` — Nova + app report download endpoint
- `routes/api.php` — REST API for external consumers (Apps)

## Feature disponibili

| Feature | Ticket | Moduli toccati | Note |
|---|---|---|---|
| Task collegati alle Quote (replica feature HubSpot) | oc:8327 | `app/Models/Task.php`, `app/Nova/Task.php`, `app/Nova/Filters/TaskDueDateFilter.php`, `app/Nova/Actions/ToggleTaskCompleted.php`, `app/Nova/Quote.php`, `app/Models/Quote.php`, `app/Providers/NovaServiceProvider.php`, `database/migrations/2026_08_19_113137_create_tasks_table.php`, `database/migrations/2026_08_19_123457_add_creator_id_to_tasks_table.php` | Promemoria (`title`/`notes` Tiptap/`due_date`/`status`) legati a una Quote, assegnatario sempre derivato da `quote->user` (mai persistito); vista globale Nova Filter (non Kanban/Lens) con scoping su proprietario **o** creatore del Task; azione "Segna come completato/Riapri" limitata al creatore; sub-panel in Quote sempre non filtrato |
| Sync distribuita App multi-shard + report store | oc:8242 | `config/shards.php`, `app/Services/Shards/`, `app/Console/Commands/SyncShardApps.php`, `app/Console/Commands/GenerateAppReports.php`, `app/Jobs/SyncShardAppJob.php`, `app/Nova/App.php`, `app/Nova/Filters/Shard*.php`, `app/Http/Controllers/AppReportController.php`, `wm-package/src/Http/*Export*` | Apps sincronizzate da tutti gli shard (geohub/maphub/camminiditalia/osm2cai) con identità `(shard, app_id)`, upsert non distruttivo, sync on-demand sul detail; report PDF pre-generati di notte, bottone solo per app pubblicate sugli store |
| Metrica "Todo >1g" nella card team performance | oc:8192 | `app/Services/Metrics/StoryMetricsCalculator.php`, `app/Http/Controllers/Nova/TeamPerformanceController.php`, `nova-components/team-performance/dist/js/card.js` | Nuovo metodo `todoStagnationTotalDays()` (somma tutti gli intervalli todo); esposto come colonna per-ticket e KPI aggregato; etichetta "Todo >1g" perché `workingDaysBetween` conta giorni interi; 0 giorni mostrato come `—` |
| Fix download allegati (path generator ibrido) | oc:8028 | `app/Services/MediaLibrary/OrchestratorPathGenerator.php`, `app/Providers/AppServiceProvider.php` | Generator C→B→A ripristina accesso ai 605/631 media legacy; wm-package sovrascriveva path_generator con WmfePathGenerator |
| PDF preventivo — logo visibile | oc:8047 | `resources/views/quote-pdf.blade.php`, `public/images/logo.png` | Usa `file://` path invece di data URI base64; DomPDF non renderizza data URI in questo setup |
| Sync calendario asincrona con debounce | oc:8044 | `app/Jobs/SyncDeveloperCalendarJob.php`, `app/Observers/StoryObserver.php`, `app/Console/Commands/SyncStoriesWithGoogleCalendar.php`, `tests/Feature/SyncDeveloperCalendarJobTest.php` | La sync Google Calendar al save di una Story è un job in coda (debounce 60s, unique per email); save Nova < 2s, bulk edit senza timeout |
| Hetzner Monitoring | oc:7944 | `config/hetzner.php`, `app/Services/HetznerApiService.php`, `app/Http/Controllers/HetznerMonitoringController.php`, `app/Exports/HetznerExport.php`, `nova-components/hetzner-monitoring/`, `app/Nova/Dashboards/HetznerMonitoring.php` | Dashboard Nova con tabella per progetto Hetzner: server, floating IP, volumes, LB, snapshot. Cache Redis 15 min. Export CSV. |
| Auto-revert ticket in progress quando dev è offline su Slack | oc:8136 | `app/Console/Commands/SlackRevertProgressCommand.php`, `app/Services/SlackService.php`, `database/migrations/2026_06_25_120000_add_slack_user_id_to_users_table.php`, `app/Models/User.php`, `app/Nova/User.php`, `config/services.php`, `app/Console/Kernel.php` | Comando schedulato ogni 20 min (12-18) che verifica presenza Slack dei dev con ticket in progress; se offline → saveQuietly() + StoryLog manuale |
| API CRUD per Tag con attach/detach stories | oc:8155 | `app/Http/Controllers/Api/TagController.php`, `app/Http/Requests/Api/TagApiRequest.php`, `routes/api.php`, `tests/Feature/Api/TagApiTest.php` | GET/POST/PATCH /api/tags, GET /api/tags/{tag}, POST/DELETE /api/tags/{tag}/stories/{story}; solo Developer e Admin; StoryLog su attach/detach |
| Override Orchestrator accesso Nova | oc:8161 | `app/Models/User.php`, `tests/Feature/UserAccessNovaOverrideTest.php` | Il wm-package blocca il login web senza `access-nova`; in Orchestrator `User::can('access-nova')` ritorna sempre true per consentire accesso Nova a tutti gli utenti del progetto |
| Fix tag automatici su update Nova | oc:8051 | `app/Nova/Story.php`, `app/Observers/StoryObserver.php` | Ripristinati `afterCreate`/`afterUpdate` in Nova; try/catch isolati in observer |
| Fix email creazione ticket Scrum | oc:8091 | `app/Models/Story.php`, `tests/Feature/StoryEmailTriggersTest.php` | Alla creazione di un ticket di tipo Scrum nessuna mail viene inviata ai developer; tutti gli altri tipi inviano normalmente |
| Invio email alla creazione ticket | oc:8040 | `app/Mail/DevNewStoryCreated.php`, `resources/views/mails/dev-new-story-created.blade.php`, `app/Models/Story.php`, `tests/Feature/StoryEmailTriggersTest.php` | Alla creazione di qualsiasi ticket tutti i dev ricevono email: `CustomerNewStoryCreated` se creator è customer, `DevNewStoryCreated` altrimenti |
| Invio email creator su Released | oc:7977 | `app/Models/Story.php`, `tests/Feature/StoryEmailTriggersTest.php` | Il creator riceve sempre l'email su status→released, indipendentemente da ruolo, da chi agisce, e dall'auto-assign tester |
| API endpoint GET /me | oc:7974 | `routes/api.php`, `tests/Feature/Api/MeEndpointTest.php` | Restituisce id, name, email dell'utente autenticato via Sanctum |
| API CRUD per Quote + attach/detach products/recurring-products | oc:8286 | `app/Policies/QuotePolicy.php`, `app/Http/Controllers/Api/QuoteController.php`, `app/Http/Controllers/Api/ProductController.php`, `app/Http/Controllers/Api/RecurringProductController.php`, `app/Http/Requests/Api/QuoteApiRequest.php`, `routes/api.php`, `tests/Feature/Api/QuoteApiTest.php`, `tests/Feature/Api/ProductApiTest.php`, `tests/Feature/Api/QuotePolicyTest.php` | GET/POST/PATCH/DELETE /api/quotes, attach/detach upsert products/recurring-products con quantity, liste read-only /api/products e /api/recurring-products; fix a `QuotePolicy::before()` (era codice morto), solo Admin/Manager/Developer |
| Documentazione API con Scramble (OpenAPI/Swagger) | oc:8287 | `config/scramble.php`, `app/Providers/AppServiceProvider.php`, `app/Providers/NovaServiceProvider.php`, `app/Http/Controllers/Api/*.php`, `tests/Feature/Api/ApiDocsTest.php` | Doc pubblica su `/docs/api` generata automaticamente da `dedoc/scramble`, limitata alle sole route Orchestrator (esclude wm-package); "Try it out" abilitato solo per GET; link nel menu Nova (sezione ACTIONS) |
| API Quotes: PDF via API con link pubblico firmato ed endpoint customers | oc:8291 | `app/Services/QuotePdfService.php`, `app/Http/Controllers/QuotePublicController.php`, `app/Http/Controllers/Api/QuoteController.php`, `app/Http/Controllers/Api/CustomerController.php`, `app/Http/Controllers/Api/AuthController.php`, `app/Models/Customer.php`, `app/Models/Quote.php`, `app/Nova/Customer.php`, `app/Console/Commands/BackfillCustomerVatFromHeading.php`, `database/migrations/2026_07_28_120000_add_vat_and_address_to_customers_table.php`, `routes/api.php`, `routes/web.php` | `GET/POST /api/quotes/{quote}/pdf`(-link) + rotta pubblica firmata `throttle:30,1`; `GET /api/customers`(/{customer}) read-only; `index()` quotes con timestamp/sort/paginazione opt-in/filtro status multiplo; `show()` con `?include=`; comando `customers:backfill-vat` (dry-run default, 39/52 customer aggiornati) |
| Metriche totali Sales Kanban per stati To Present/Presented/Waiting For Order | oc:8330 | `nova-components/kanban-card/src/KanbanCard.php`, `nova-components/kanban-card/dist/js/card.js`, `nova-components/kanban-card/dist/css/card.css`, `app/Nova/Dashboards/Sales.php`, `lang/it.json`, `lang/en.json` | Nuovo metodo opt-in `KanbanCard::metricStatuses()` che mostra 3 metric-card (titolo + totale EUR + count) sopra la toolbar, alimentate da `totalCountByStatus` già esistente (nessun nuovo endpoint); reattive a ricerca/filtro, spinner/errore dedicati (`countsLoading`/`countsError`) |
| API Task per follow-up e automazioni | oc:8403 | `app/Http/Controllers/Api/TaskController.php`, `app/Http/Requests/Api/TaskApiRequest.php`, `app/Policies/TaskPolicy.php`, `app/Models/Task.php`, `routes/api.php` | GET/POST/PATCH `/api/tasks`(/{task}), autorizzazione differenziata per campo sul PATCH (`status` solo creator, `notes` qualsiasi ruolo abilitato), nuovo `Task::appendNote()` (prepend, mirror `Story::addDevNote()`); nessun DELETE, nessuna paginazione |
| Fix 500 generazione PDF preventivo con `additional_services` null | oc:8413 | `resources/views/quote-pdf.blade.php`, `tests/Feature/QuotePdfServiceTest.php` | `count(null)` è `TypeError` su PHP 8.1: la guardia `!is_string()` non copriva `null`, stato legittimo del campo (KeyValue Nova vuoto, `QuoteApiRequest` `nullable`). Closure `$normalizeAdditionalServices` in cima al template riusata nei 3 punti di lettura; nessuna scrittura DB, nessun backfill |
| Tab nel dettaglio Quote (Principale/Task/Prodotti/Recurring) | oc:8407 | `app/Nova/Quote.php`, `lang/it.json`, `lang/en.json` | `fields()` avvolto in `Tab::group('Quote Details')->withToolbar()` con 4 tab (Main/Task/Products/Recurring Products), stesso pattern nativo Nova già usato in `Customer.php`/`App.php`; nessuna modifica a visibilità index o scoping Task |

## Decisioni architetturali

### Fix 500 generazione PDF preventivo con `additional_services` null (oc:8413)
- **Normalizzazione completa (`null`/stringa JSON/non-array → `[]`) invece della guardia `is_array($v) && count($v) > 0`** indicata nelle note del ticket: quella guardia avrebbe reso una stringa JSON "presente" per il check «No items available» delle righe 63–72 del template (che fa `json_decode`) e "assente" per la lista servizi e la tabella costi — un PDF che dichiara di avere servizi aggiuntivi ma ne omette la voce di costo. Su un documento commerciale questo difetto è peggiore del 500 stesso: il 500 è visibile e viene segnalato, un totale mancante no. La normalizzazione vive in una closure `$normalizeAdditionalServices` definita nel blocco `@php` di testa e riusata in tutti e tre i punti di lettura.
- **Fix nel template Blade, non sul modello**: valutato un `Quote::normalizedAdditionalServices()` che centralizzasse la coercizione, scartato per tenere il diff minimo su un bugfix urgente. Conseguenza: la coercizione di tipo vive in una vista, e un quarto punto di lettura del campo può reintrodurre lo stesso `TypeError`. Centralizzazione annotata come follow-up in `docs/features/8413-quote-generazione-pdf/notes.md`.
- **Nessun mutator `null → []` in scrittura**: `additional_services: null` è un valore documentato nel contratto API pubblico (8 docblock `@response` in `Api/QuoteController`) e `[]` non gli è semanticamente equivalente per un consumatore esterno; inoltre `[]` è trattato come "traduzione da rimuovere" da `clearEmptyAdditionalServicesTranslations()`. Il fix è solo in lettura: Nova e API continueranno a produrre `null`, e le 2 righe esistenti in quello stato (id 209/211) non sono state backfillate. `Quote::getTotalAdditionalServicesPrice()` era già null-safe, quindi i totali non erano a rischio — solo il rendering.
- **`QuoteFactory` popola sempre un array per `additional_services`**: lo stato `null` non è raggiungibile passando dalla factory. Nei test va forzato sulla colonna (`DB::table('quotes')->...->update(['additional_services' => null])` + `refresh()`, helper `forceAdditionalServices()` in `QuotePdfServiceTest`). È il motivo per cui i test PDF preesistenti non coprivano questo path.

### Tab nel dettaglio Quote (oc:8407)
- **Tab nativo di Nova 4 (`Laravel\Nova\Tabs\Tab`), non `eminiarts/nova-tabs`**: quest'ultimo è installato in composer ma è una dipendenza transitiva di `Kongulov\NovaTabTranslatable`, non usato direttamente per il layout — verificato con `grep -rl "Eminiarts" app/Nova/` (0 risultati) prima di scrivere codice. Il pattern corretto da replicare era già in uso in `Customer.php` e `App.php`.
- **`NovaTabTranslatable` annidato dentro `Tab::group` non era inedito quanto sembrava dalla prima analisi**: `app/Nova/App.php` (righe 235/449, `home_tab()`) usa già `Tab::group(...)->withToolbar()` con `NovaTabTranslatable::make([Tiptap::make(...)])` in produzione — precedente diretto trovato solo durante la stesura del piano, non dalla prima challenge (che aveva controllato solo `Customer.php`, dove questi campi restano fuori dal gruppo tab). Riduce ma non azzera il rischio sul campo `title` di Quote (anche usato in `title()`), verificato comunque con test manuale end-to-end (create + update) prima del commit.
- **Il blocco `NovaTabTranslatable` che raggruppava Tiptap ×4 + `notes` è stato diviso in due chiamate separate** per rispettare l'ordine campi esatto richiesto (`Documents` tra "Piano di fatturazione" e "Note"): introduce due selettori lingua indipendenti nel tab Main invece di uno unico. Trade-off accettato consapevolmente in fase di review (`wm-review-ticket`): il rischio di un traduttore che dimentica di sincronizzare le due lingue è minore rispetto al beneficio di rispettare l'ordine letterale del ticket.
- **`App\Nova\QuoteNoFilter` eredita `fields()` da `Quote` senza override**: la nuova struttura a tab si propaga automaticamente al sub-panel "Preventivi" nel dettaglio Customer. Valutato in review come scelta corretta (non un bug): un override forkerebbe la UI in due definizioni da tenere sincronizzate a mano. Verificato manualmente che il rendering nested funziona senza rotture.
- **Bug di scoping Task via `QuoteNoFilter` reso più visibile ma non fixato (fuori scope)**: `App\Nova\Task::indexQuery()` bypassa lo scoping "solo i miei/creati da me" solo quando `$request->viaResource === 'quotes'` (introdotto in oc:8327/oc:8403) — ma l'uriKey di `QuoteNoFilter` è `quote-no-filters`, quindi il sub-panel Task raggiunto da Customer → tab Preventivi non beneficia del bypass. Bug preesistente, non introdotto da questo ticket ma reso più probabile da scoprire (Task è ora un tab di primo piano). Da aprire come ticket dedicato.
- **Wrapping in `Tab::group`/`Tab::make` non altera la visibilità dei campi sull'index** (`hideFromIndex`/`onlyOnIndex`/`sortable` invariati) — confermato leggendo `vendor/laravel/nova/src/Tabs/TabsGroup.php`: il wrapping aggiunge solo metadata `tab` al field, non tocca le regole di visibilità esistenti. Solo la vista Detail/Create/Update viene riorganizzata.

### API Task per follow-up e automazioni (oc:8403)
- **`TaskPolicy::create(User $user, ?Quote $quote = null)` con `$quote` opzionale**: Nova chiama `Gate::authorize('create', Task::class)` con un solo argomento (nessuna istanza `Quote`) per decidere se mostrare l'azione "crea" sulla risorsa — una firma a 2 parametri obbligatori rompeva **l'intera creazione di Task via Nova** con `ArgumentCountError`, non solo il blocco su quote chiuse. Trovato dall'utente in produzione (screenshot Ignition/Flare) dopo che tutta la suite PHPUnit era verde — i test esistenti chiamavano sempre `authorize('create', [Task::class, $quote])` con 2 argomenti, non intercettando il path a 1 argomento di Nova. Quando `$quote` è assente il blocco non si applica (comportamento Nova invariato); il controller API passa sempre la Quote esplicitamente, quindi il blocco resta effettivo lì. Aggiunto test di regressione dedicato (`nova_puo_verificare_create_senza_una_quote_specifica`).
- **Autorizzazione differenziata per campo sul `PATCH /api/tasks/{task}`**: `status` autorizzato solo se `creator_id === utente loggato` (mirror esatto di `ToggleTaskCompleted::authorizedToRun()`, invocato via ability dedicata `TaskPolicy::updateStatus()`), `notes` aperto a qualsiasi Admin/Manager/Developer (mirror di `Story::addDevNote()`, via `TaskPolicy::update()`). Il controller verifica `updateStatus` PRIMA di applicare qualsiasi modifica quando il payload contiene `status`: un payload misto `{status, notes}` da un non-creator fallisce con 403 sull'**intera** richiesta, `notes` non viene mai persistita anche se sarebbe stata autorizzabile da sola. Pattern non standard rispetto al resto del progetto (Quote/Tag hanno un solo verdetto booleano per endpoint) — documentato esplicitamente nei docblock di `TaskPolicy`.
- **`Task::appendNote()` prepende, non accoda**, mirror del comportamento reale di `Story::addDevNote()` (che nonostante il nome storico "prepende" sempre in cima, mai in coda). `creator_id` non è mai un campo accettato in input su `POST`/`PATCH` (whitelist esplicita in `TaskApiRequest::rules()`) — resta gestito solo da `Task::booted()`, per impedire mass-assignment via API.
- **`GET /api/tasks/{task}` è ruolo-only** (nessuno scoping ownership, mirror `QuotePolicy::view()`), mentre **`GET /api/tasks` è scoped** via `Task::scopeForUser()` (owner Quote o creatore) — asimmetria intenzionale: la lista filtra "i miei task", il dettaglio (raggiungibile solo conoscendo l'ID) resta visibile a chiunque abbia un ruolo abilitato, coerente col resto del progetto.
- **Nessuna paginazione, nessun `?include=` opt-in**: ogni Task nel payload include sempre `assignee` (derivato da `quote->user`, spesso `null` — il 72% delle Quote non ha `user_id` valorizzato, vedi oc:8327) e `quote_id`/`quote_title`. Contratto consumato da un client esterno (skill Cowork) fuori da questo repo: un futuro cambio verso paginazione/opt-in sarebbe un breaking change cross-repo, non un semplice refactor lato backend.
- **`creator_id` nullable (`nullOnDelete()`) → Task "orfani" permanenti** se l'utente creatore viene eliminato: `creator_id === utente loggato` non è mai vero per nessuno, il task non è più completabile/riapribile da nessuno. Limite ereditato da `ToggleTaskCompleted` (già presente in Nova prima di questo ticket), nessun admin-override aggiunto per non alterare il comportamento Nova esistente.

### Task collegati alle Quote (oc:8327)
- **Assegnatario mai persistito, sempre derivato da `quote->user`** via accessor `Task::assignee`: nessun campo `user_id`/`assigned_to` sul Task. Una riassegnazione della Quote riscrive retroattivamente la paternità percepita di tutti i Task collegati, inclusi quelli già completati — accettato consapevolmente, nessun audit trail storico sull'assegnatario in questo ciclo.
- **Nessuna restrizione su chi può creare un Task**: chiunque abbia accesso a Nova può creare un Task su qualsiasi Quote, anche senza esserne il proprietario, anche se la Quote non ha alcun `user_id` valorizzato (72% delle Quote attuali). Decisione presa in corso d'opera, diverge dal blocco inizialmente pianificato.
- **`Task::scopeForUser()` mostra Quote di cui l'utente è owner OPPURE Task creati dall'utente stesso** (`creator_id`): senza questa seconda condizione, un Task creato su una Quote altrui o senza owner sarebbe invisibile per sempre anche a chi lo ha creato, in nessun elenco personale.
- **`indexQuery()` su `App\Nova\Task` applica lo scoping "solo i miei/creati da me" SOLO quando la Resource è la vista principale** (`$request->viaResource !== 'quotes'`): quando la lista è richiesta come relazione `HasMany` dal sub-panel di una Quote, lo scoping va bypassato — altrimenti il sub-panel di una Quote non propria risulterebbe erroneamente vuoto, anche con Task realmente presenti.
- **Badge di urgenza (`Task::urgencyBadgeKey/Label`) confronta sempre e solo la data, mai l'ora esatta** (`now()->startOfDay()` fresco, mai mutando l'attributo `due_date` reale): un confronto su datetime completo (`isPast()`/`isToday()`) classificava erroneamente come "scaduto" un Task in scadenza "oggi" non appena l'orario corrente superava l'orario di scadenza nello stesso giorno — disallineato dalla semantica a livello di giorno già usata dagli `scope*` e dal filtro.
- **Nova Filter, non Kanban né Lens, per la vista globale**: il componente Kanban condiviso (`nova-components/kanban-card/`) è pensato per stati persistiti con drag&drop; le colonne "scaduto/oggi/imminente/completato" sono proiezioni calcolate da una data, non stati reali — un drag&drop tra queste colonne non avrebbe un'azione di scrittura naturale. Verificato anche sul comportamento reale di HubSpot (fonte del requisito): tab-di-filtro + tabella, non un board.
- **Azione "Segna come completato/Riapri" autorizzata solo per `creator_id === utente loggato`**, sostituisce l'azione "Replica" di default di Nova (`authorizedToReplicate() => false`). Richiede il nuovo campo `creator_id` (FK nullable verso `users`, `nullOnDelete`), valorizzato automaticamente all'utente autenticato in `Task::booted()`.
- **`onDelete('cascade')` su `quote_id`**: eliminare una Quote elimina silenziosamente tutti i suoi Task — accettato, i Task non hanno valore autonomo senza la Quote di riferimento.
- **`App\Nova\Quote::title()` con fallback `$this->name ?: $this->title`**: la Resource usa `$title = 'name'`, ma `name` è un campo fillable morto sul modello (colonna non popolata nella maggior parte delle Quote reali, vedi oc:8286) — senza fallback il campo `BelongsTo` verso Quote nel form Task mostrava un'etichetta vuota.

### Metriche totali Sales Kanban per stati To Present/Presented/Waiting For Order (oc:8330)
- **Attivazione strettamente opt-in via `KanbanCard::metricStatuses(array $statuses)`**: il componente `kanban-card` è condiviso da altri dashboard Nova (es. `app/Nova/Dashboards/Kanban.php`) che non hanno il concetto di `QuoteStatus`. Le metric-card si renderizzano solo se il dashboard configura esplicitamente `metricStatuses` (default array vuoto) — nessuna euristica JS basata su nomi/valori di stato, nessun impatto sugli altri dashboard.
- **Colore/label delle metric-card letti dalla stessa config `columns` già esistente** (passata lato PHP da `QuoteStatus::cases()` in `Sales.php`), non da una nuova chiamata a `QuoteStatus::label()/color()` lato frontend — singola fonte di verità, nessun rischio di disallineamento tra il colore della colonna kanban e quello della metric-card corrispondente.
- **Nuovo stato reattivo `countsLoading`/`countsError` nel JS**, distinto dal flag `loading` esistente (che copre solo il caricamento iniziale pesante degli item): `fetchCounts()` viene richiamato anche da ricerca/filtro senza necessariamente passare per `loading`, quindi serviva un flag dedicato per pilotare correttamente lo spinner delle metric-card e distinguere un fallimento di rete (mostrato come "—" con tooltip) da un totale realmente a zero.
- **Nessun nuovo endpoint backend**: le metric-card riusano `totalCountByStatus` (già popolato da `fetchCounts()` per il badge delle colonne) e le funzioni esistenti `getHeaderCount()`/`getHeaderSum()`/`formatCurrency()` — zero costo di rete aggiuntivo.
- **`nova-components/kanban-card/dist/js/card.js` e `dist/css/card.css` sono bundle scritti a mano, nessuna build/lint automatica**: ogni modifica va validata con `node --check nova-components/kanban-card/dist/js/card.js` prima del commit — un errore di sintassi qui rompe l'intero componente Kanban (drag&drop, ricerca, colonne) per tutti i dashboard che lo usano, non solo Sales.

### API Quotes: PDF via API con link pubblico firmato ed endpoint customers (oc:8291)
- **Non eliminare i docblock `@response` duplicati confidando nell'inferenza automatica di Scramble su un metodo privato condiviso** (es. `formatQuote()`): testato empiricamente — produce tipi sbagliati per attributi `Spatie\Translatable` (`title`/`notes` inferiti `array` invece di `string`), per valori computati (`discount`/`total`/`iva`/`final_price` inferiti `string` invece di `float`), e marca chiavi condizionali (`customer`/`products`/`recurringProducts`, presenti solo con `?include=`) come sempre `required`. I docblock manuali duplicati, per quanto ripetitivi, sono più accurati — non è debito da eliminare a cuor leggero.
- **Un tipo union nel docblock `@response` (`array<...>|array{data: ..., meta: ...}`) viene correttamente risolto da Scramble come `anyOf`** nello schema OpenAPI — usare questo pattern quando un endpoint ha più forme di risposta possibili (es. `index()` con paginazione opt-in: array semplice o `{data, meta}`), a differenza degli alias `@phpstan-type` che invece NON funzionano (vedi punto sotto).
- **Gli alias `@phpstan-type` non funzionano nei tag `@response` di Scramble** (v0.13.35): tentativo di centralizzare la shape di risposta duplicata in 8 docblock di `Api/QuoteController.php` con `@response 201 QuoteResource` (alias definito a livello di classe) — Scramble non lo risolve, produce silenziosamente uno schema errato (`{"type":"integer","const":201}`). Non usare questo pattern; se serve prevenire la deriva doc↔runtime, scrivere un test che ispezioni `/docs/api.json` (vedi `tests/Feature/Api/QuoteApiDocsTest.php`), non provare a deduplicare i docblock via alias di tipo.
- **Scramble non infra tutti i query param da analisi statica**: riconosce solo chiamate `$request->method('key')` con metodo mappato (`integer`/`float`/`boolean`/`enum`/`query`/`string`/`str`/`input`/`get`/`post`) in posizione AST diretta (assegnazione, argomento, elemento array) — non dentro un confronto (`===`) né annidata dentro un cast/funzione (es. `(string) $request->get('x', '')` dentro `explode(...)`). `sort` (letto solo in `if ($request->get('sort') === '-created_at')`), `page` (letto solo via `$request->filled('page')`, metodo non mappato, e comunque consumato internamente da `paginate()`) e `include` su `show()` (annidato in `explode(',', (string) $request->get('include', ''))`) restavano quindi assenti da `/docs/api`; `status[]` veniva documentato solo come `string` perché Scramble vede la prima occorrenza (`$request->input('status')`) e non sa che accetta anche un array. Fix: attributi PHP `#[QueryParameter(...)]` di `Dedoc\Scramble\Attributes` direttamente sul metodo (`Api\QuoteController::index()` e `::show()`) — letti via reflection, bypassano l'inferenza. Verificato sistematicamente tutti gli endpoint del ticket: `lang` su `pdf()`, `lang`/`expires_in_days` su `pdfLink()` (body, via `$request->validate()` inline) e `status`/`search` su `CustomerController::index()` erano già correttamente inferiti (chiamate in posizione diretta) — nessun fix necessario lì. Usare questo pattern (attributo esplicito) per qualsiasi futuro query param letto in modo "non standard" (dentro condizionali, cast, o consumato da helper Laravel come `paginate()`).
- **`Quote::clearEmptyAdditionalServicesTranslations(bool $persist = true)`**: normalizza SEMPRE le traduzioni in-memory (rimuove le lingue con `additional_services` vuoto, necessario perché `Spatie\Translatable` considera "tradotta" una lingua anche se il valore è un array vuoto e quindi non fa fallback), ma salva su DB solo se `$persist`. Un primo fix che rendeva l'intero metodo condizionale a `$persist` aveva introdotto una regressione: saltava anche la normalizzazione, causando omissione silenziosa dei servizi aggiuntivi nel PDF pubblico per lingue con traduzione vuota.
- **`QuotePdfService::stream(Quote $quote, string $lang, bool $persist = true)`** condiviso tra rotta web (Nova, `persist: true`, comportamento invariato), rotta API bearer (`persist: false` — l'endpoint è pensato per chiamate ripetute da skill, non deve avere side-effect) e rotta pubblica firmata (`persist: false`). Senza `persist: false`, ogni download di un preventivo `template=true` casca silenziosamente `template=false` su tutti gli altri preventivi template dello stesso cliente (hook `Quote::booted()`).
- **Rotta pubblica firmata (`routes/web.php`, `quotes.pdf.public`)**: middleware `['signed', 'throttle:30,1']` — il throttle è necessario perché la rotta vive fuori da `auth:sanctum` e quindi fuori dal `throttle:api` esistente. `->missing(fn () => abort(403))` obbligatorio: `SubstituteBindings` (nel gruppo `web`) risolve il binding PRIMA che `signed` verifichi la firma, quindi senza questo un id inesistente darebbe 404 mentre una firma invalida su un id esistente dà 403 — permettendo l'enumerazione degli id validi a un chiamante anonimo.
- **`expires_in_days` per il link pubblico**: validato `integer|min:1|max:90`, default 30. Nessuna revoca del singolo link prima della scadenza (solo rotazione `APP_KEY`, che invalida tutti i link firmati del progetto) — accettato consapevolmente.
- **`company_name` nell'API Customer non è una colonna**: alias di sola lettura su `full_name` (già popolato per 124/136 customer col nome legale), calcolato nel controller. Nessuna migrazione per questo campo.
- **Comando `customers:backfill-vat`**: dry-run di default (dato derivato da regex su testo libero `heading`), `--apply` per scrivere, report sempre salvato su `storage/app/customer-vat-backfill/` PRIMA di ogni apply. Il regex distingue Partita IVA (11 cifre numeriche) da Codice Fiscale alfanumerico di persona fisica (16 caratteri) — quest'ultimo non va MAI scritto in `vat`. Eseguito realmente sul DB principale post-merge: 39/52 customer con `heading` aggiornati.
- **Nessun campo Nova per `vat`/`address` nel piano originale** (ticket API-only) — aggiunti a posteriori su richiesta esplicita: `Text::make(__('VAT Number'), 'vat')` e `Textarea::make(__('Address'), 'address')` in `app/Nova/Customer.php`, con traduzioni dedicate in `lang/it.json`/`lang/en.json`. Attenzione: la chiave di traduzione `"VAT"` era già usata per l'etichetta IVA del PDF preventivo (`"IVA"`) — usata `"VAT Number"` per evitare di sovrascriverla silenziosamente (JSON con chiavi duplicate: l'ultima vince).
- **Docblock pubblico di `AuthController::login()` intenzionalmente ridotto**: il token Sanctum emesso non ha scadenza né ability granulari (nessun enforcement in questo ciclo) — questo dettaglio NON va scritto nel docblock pubblico (renderizzato su `/docs/api`, pagina pubblica) perché offrirebbe ricognizione gratuita a un attaccante. Per revocare un token compromesso: `$user->tokens()->delete()` via tinker (nessun endpoint dedicato).
- **Rotta web `/quote/{id}` esistente non ha alcuna autorizzazione** (`QuoteController@show` fa `findOrFail` nudo) — gap pre-esistente, non introdotto da questo ticket, lasciato esplicitamente fuori scope. Da aprire come ticket dedicato.
- **Endpoint `POST /api/quotes/{quote}/send` (invio email col PDF) confermato fuori scope in sede di scrum**, non solo come scelta tecnica del ciclo: l'invio email è responsabilità della skill Claude (già capace di gestirlo meglio); inviare dal backend userebbe l'indirizzo dell'utente loggato come mittente, impedendo una gestione efficace delle risposte (servirebbe un indirizzo no-reply/sistema dedicato, che la skill non richiede); si preferisce evitare implementazioni frammentate, rimandando l'intera gestione email-su-ticket a una discussione più ampia e strutturata in futuro. Se un ticket futuro riapre questo punto, va affrontato in modo organico (non solo per i preventivi) e non semplicemente riproposto come piccola aggiunta a `Api/QuoteController.php`.

### Documentazione API con Scramble (oc:8287)
- **Scramble documenta di default TUTTA la superficie `/api/*`**, incluse le route registrate da `wm-package` (mobile app v1/v2/v3, ec/poi, ec/track, ugc, wallet, elasticsearch, export) — scoperto solo in esecuzione, non previsto in overview. Limitato con `Scramble::routes()` in `AppServiceProvider::boot()`: filtro per action-name (`App\Http\Controllers\Api\*`) + whitelist esplicita per `AppController::config` e la closure `/me`. Il filtro per prefisso URI non basta perché `wm-package` registra proprie route `auth/*` che collidono col prefisso della nostra `/auth/login`.
- **`security_strategy` è disabilitato di default nel config pubblicato**: va abilitato esplicitamente (`MiddlewareAuthSecurityStrategy`) perché le operazioni sotto `auth:sanctum` ottengano il requisito di sicurezza Bearer a livello di documento.
- **"Try it out" ristretto alle sole GET**: le operazioni mutanti (POST/PATCH/DELETE) hanno `security: []` esplicito via `Scramble::afterOpenApiGenerated()`, così un Bearer token trapelato non può eseguire scritture reali dalla doc pubblica — restano comunque documentate.
- **La security è a livello di documento, non di operazione**: le operazioni GET ereditano `$spec.security` senza ripetere la chiave — non verificare `operation.security` per le route autenticate, verificare l'assenza della chiave e la presenza della security globale.

### API CRUD per Quote (oc:8286)
- **`QuotePolicy::before()` era codice morto per `update()`/`delete()`**: ritornava sempre un booleano netto (mai `null`), quindi short-circuitava sempre la valutazione — il blocco su `closed_won`/`closed_lost` scritto in `update()` non veniva mai realmente eseguito, né da Nova né dall'API. Corretto in un `if` che nega subito i ruoli non autorizzati e lascia proseguire (`null`) per i ruoli abilitati; `delete()` implementato con la stessa regola (prima era vuoto). Effetto collaterale scoperto durante l'esecuzione: `viewAny()`/`view()`/`create()` erano anch'essi metodi vuoti mai valutati prima — ora restituiscono `true` esplicitamente, altrimenti `create()` negava sempre (403 su ogni `POST`).
- **`name` è un fillable morto sul modello `Quote`**: `$fillable` lo include ma la colonna non esiste in DB (solo `title`). Scoperto con `Schema::getColumnListing('quotes')` durante l'esecuzione — l'API usa `title`, non `name`. Non corretto sul modello (fuori scope).
- **`title` è `$translatable`** (oltre a `additional_services`/`notes`): l'assegnazione di una stringa via `fill()` è intercettata da `Spatie\Translatable\HasTranslations` e scritta solo sulla lingua corrente — comportamento già coerente con la regola "solo lingua di default", nessuna gestione esplicita necessaria nel controller.
- **Attach/detach pivot (`products`/`recurringProducts`) upsert, non idempotente puro**: un secondo `POST` sulla stessa coppia aggiorna la `quantity` (`syncWithoutDetaching` con array pivot), non la ignora — decisione esplicita del dev dopo chiarimento in fase di reverse-interaction.
- **`ProductController`/`RecurringProductController` non riusano `QuotePolicy`**: duplicano il check ruoli invece di passare da Gate — accettato per scope minimo (solo `index` read-only), segnalato come cleanup da correggere se la lista ruoli abilitati cambia in futuro.

### Sync distribuita App multi-shard (oc:8242)
- **Identità composita `(shard, app_id)`**: `app_id` è l'id numerico remoto (stringa) dello shard, immutabile; l'`id` locale autoincrement resta la chiave per route/pivot/tag. Unique composito al posto dell'unique su `app_id`. In Nova l'ID visibile è `app_id` (+ colonna shard), mai l'id locale.
- **La sync scrive SOLO con `saveQuietly`**: mai eventi Eloquent (l'observer `updated` fa `BuildConfJson` con URL geohub hardcodati; il hook `created` crea tag automatici). Nessun side effect è invocato dalla sync.
- **Colonne a proprietà separata**: shard-owned (schema wm-package, `user_email` incluso) scritte solo dalla sync; orchestrator-owned (`user_id` valorizzato, `customer_name`, pivot `user_app`, tag) mai toccate dopo la creazione; `removed_from_shard_at` è sync-owned (timbrata E azzerata dalla sync). I `null` del payload non si scrivono mai (colonne NOT NULL con default).
- **Guardie riconciliazione**: payload vuoto/invalido → no-op con log; rimozioni > 30% delle attive dello shard → abort. Mai delete fisico: le app sparite vengono marcate dismesse, quelle ricomparse riattivate.
- **Registry in `config/shards.php`**: slug IMMUTABILI (rinominarli orfanizza le app dello shard); `enabled => false` è il kill switch/rollback operativo — mai il `down()` della migration dopo il primo sync multi-shard. Token in ENV `SHARD_TOKEN_<SLUG>`.
- **Contratto export wm-package**: `/api/v1/export/apps` — whitelist esplicita in `AppExportResource` (mai serializzare colonne modello), campo aggiunto = ok, rinomina/rimozione = `v2`. Bearer token da `WM_EXPORT_TOKEN` (assente = 403, endpoint spento).
- **Report PDF**: bottone "Store report" solo se `hasStorePresence()` (store link o `app_id` bundle-like); `storeBundleId()` deriva il package dal link Play Store — MAI passare l'`app_id` numerico allo script Python (store lookup fallisce → PDF vuoto). Pre-generazione notturna alle 03:30 (`apps:generate-reports --fresh`). Nome file shard-qualificato.
- **`App::author()` di wm-package richiedeva FK esplicita `user_id`**: l'inferita `author_id` non esiste — relazione rotta da sempre, fixata in oc:8242.

### Metrica "Todo >1g" nella card team performance (oc:8192)
- **`workingDaysBetween` conta giorni interi**: un ticket in todo per meno di un giorno lavorativo restituisce 0. L'etichetta "Todo >1g" chiarisce che si tratta di giorni completi, non ore. Valori 0 vengono mostrati come `—`.
- **Cache Redis `team_perf_avg_{year}_q{quarter}`**: TTL 1h. Dopo un deploy che aggiunge campi all'aggregato, svuotare le chiavi manualmente (`Cache::forget(...)`) altrimenti il frontend riceve il vecchio JSON senza i nuovi campi.
- **`card.js` modificato direttamente**: nessun sorgente Vue — stesso pattern di `kanban-card`. Validare sempre con `node --check` prima del commit.
- **Rollback non atomico**: rollback del solo PHP senza rollback di `card.js` causa `undefined` invece di `—` nel frontend. Entrambi i file devono essere rollbackati insieme.

### Fix download allegati — path generator ibrido (oc:8028)
- **wm-package sovrascrive `path_generator` e `disk_name`**: il suo ServiceProvider fa `array_merge` sulla config di `media-library`, rimpiazzando `CustomPathGenerator` con `WmfePathGenerator` e `disk_name` con `wmfe`. `AppServiceProvider::register()` deve ripristinare entrambi *dopo* il boot di wm-package.
- **`disk_name` hardcodato a `public`** in `AppServiceProvider`: tutti i file storici sono su disco `public`, non su S3. Non usare `env('MEDIA_DISK')` che nel container di sviluppo punta a `wmfe`.
- **Tre layout coesistenti su disco**: Layout A (`media/Model/name/file`, fino ad apr 2026), Layout B (`media/Model/name/id/file`, apr–mag 2026), Layout C (`orchestrator/media/id/file`, mag 2026–oggi). `OrchestratorPathGenerator` li tenta in ordine C→B→A; i nuovi upload vanno in C.
- **Nessuna migrazione fisica dei file**: il generator ibrido risolve il problema senza spostare file su disco.

### Fix email creazione ticket Scrum (oc:8091)
- **Guardia solo sull'invio email, non sull'assegnazione**: il `return` nell'hook `created` è posizionato dopo `$story->save()` (che assegna `creator_id`, `tester_id`) e prima del loop developer. I metadati del ticket Scrum vengono sempre popolati correttamente.
- **`$story->type` è stringa, non enum castata**: il modello `Story` non ha `$casts` per il campo `type`. Il confronto `=== StoryType::Scrum->value` è safe. Se in futuro si aggiunge il cast Eloquent, aggiornare la guardia.

### PDF preventivo — logo via file:// (oc:8047)
- **DomPDF non renderizza data URI PNG/SVG**: in questo setup (`barryvdh/laravel-dompdf ^3.0`), le immagini passate come `data:image/...;base64,...` in tag `<img>` non vengono renderizzate. Usare sempre `file://` + path assoluto per le immagini locali nei template PDF.
- **PNG ridimensionato per DomPDF**: immagini ad alta risoluzione (es. 2400px) non vengono renderizzate. Usare PNG ≤ 400–500px di larghezza per i loghi nei PDF.
- **Protocollo `file://` già in whitelist**: configurato in `config/dompdf.php` → `allowed_protocols`. Nessuna modifica alla config necessaria.

### Sync calendario asincrona con debounce (oc:8044)
- **Job con debounce invece di sync sincrona**: `SyncDeveloperCalendarJob` usa `ShouldBeUniqueUntilProcessing` (mai una sync persa: un save durante l'esecuzione accoda un nuovo job) + delay 60s nel costruttore + lock su Redis (`uniqueVia`) + `WithoutOverlapping` (la sync è delete-then-recreate, idempotente solo se serializzata).
- **Niente `saveQuietly()` sul cascade demote progress→todo**: gli eventi del modello alimentano StoryLog → `StoryTimeService` (calcolo ore) e la query calendario; il costo delle sync a catena è azzerato dalla dedup del job, non sopprimendo gli eventi.
- **Date del comando `sync:stories-calendar` inizializzate in `handle()`**, mai nel costruttore: Artisan cacha l'istanza del comando per processo, nei worker long-running una data fissata nel costruttore diventa stantia dopo mezzanotte.
- **Coda `default` senza modifiche a Horizon**: rischio timeout 60s/tries=1 accettato consapevolmente (volumi bassi, fallback alla sync schedulata delle 07:45). Supervisione Horizon: ticket oc:8059.

### Ottimizzazione Costi Hetzner (oc:7944)
- **Nova component self-contained**: `nova-components/hetzner-monitoring/` segue il pattern di `kanban-card` — il componente Vue è un JS puro registrato via `Nova::script()`, senza build step separato. Nessun Webpack/Vite da configurare per aggiunte read-only.
- **Token Hetzner in ENV**: convenzione `HETZNER_TOKEN_<SLUG>=xxx`. Letti dinamicamente da `config/hetzner.php` via `collect($_ENV)`. Aggiungere un nuovo progetto = aggiungere una variabile ENV + restart container (no deploy di codice).
- **Prezzi Volumes/Snapshots hardcodati**: l'API Hetzner Cloud non espone pricing per queste risorse. Valori da documentazione pubblica (mag 2026): Volumes €0.0476/GB/mese, Snapshots €0.0119/GB/mese. Aggiornare `HetznerApiService` se Hetzner modifica i prezzi.
- **Errori per progetto isolati**: un token non valido non blocca gli altri. La cache Redis è per-progetto (`hetzner_project_{slug}`, TTL 15 min).

### Auto-revert ticket in progress via Slack presence (oc:8136)
- **`everyTwentyMinutes()` non esiste in Laravel 10**: usare `->cron('*/20 12-18 * * *')` per scheduling ogni 20 minuti tra le 12 e le 18.
- **Slack User ID inizia con `U`**: gli ID che iniziano con `D` sono canali DM, non User ID. Per copiare lo User ID corretto: profilo Slack → ⋯ → "Copia ID membro".
- **`saveQuietly()` + StoryLog manuale**: il revert automatico usa `saveQuietly()` per evitare email/observer, ma crea `StoryLog` manualmente con `orchestrator_artisan@webmapp.it` come user di sistema.
- **`firstOrCreate` per system user**: `orchestrator_artisan@webmapp.it` potrebbe non esistere nel DB di test — usare `firstOrCreate` nel comando invece di `->first()`.
- **SLACK_BOT_TOKEN scope richiesto**: il bot token deve avere lo scope `users:read` nella sezione "Ambiti del token bot" (non "token utente") su api.slack.com/apps.

### API CRUD per Tag (oc:8155)
- **Il modello `Tag` ha due relazioni morfiche distinte**: `taggable()` (morphTo su `tags.taggable_type/id`, lega il tag a un parent come Project — non toccare via API) e `tagged()` (morphedByMany su pivot `taggables` — usare per attach/detach con Story).
- **`isAdmin()` non esiste su `User`**: il check corretto è `hasRole(UserRole::Admin)`.
- **Autorizzazione per ruolo nel controller**: solo `Developer` e `Admin` accedono alle API Tag — check via `abort_unless($user->hasRole(...))` nel metodo `authorizeRole()`.
- **Sanitize LIKE**: `str_replace(['%', '_'], ['\%', '\_'], $search)` obbligatorio prima di qualsiasi query LIKE su nome tag.
- **StoryLog su attach/detach**: creato manualmente nel controller con `changes = ['tag_attached' => $tag->id]` / `['tag_detached' => $tag->id]`.

### Override accesso Nova Orchestrator (oc:8161)
- **Il wm-package resta fail-closed**: il listener shared (`EnforceNovaAccessOnLogin`) continua a negare il login web quando `can('access-nova')` è false.
- **Deroga esplicita solo in Orchestrator**: `App\Models\User::can()` intercetta l'ability `access-nova` e ritorna sempre `true`; tutte le altre ability continuano a usare Spatie/Gate standard.
- **Scope volutamente minimale**: nessuna modifica nel package e nessun cambio su ruoli/permission seed; la deroga vive nello shard Orchestrator.
- **Copertura test dedicata**: `UserAccessNovaOverrideTest` garantisce che un utente Orchestrator mantenga sempre `can('access-nova') = true`.

### Fix tag automatici su update Nova (oc:8051)
- **`afterCreate`/`afterUpdate` in `Nova/Story.php`**: rimossi in oc:7972 e non ripristinati. La via Nova UI per gli update era completamente scoperta. La via API era già coperta da `StoryController::attachAutoTags()` — quella scelta di oc:7972 rimane valida e non è stata toccata.
- **Try/catch isolati per ogni chiamata TagService**: il blocco monolitico precedente bloccava le tre funzioni con una sola eccezione. Ora ogni chiamata fallisce indipendentemente.
- **`afterCreate` aggiunto a Nova**: era assente — l'observer `created()` garantiva già il tagging Nova ma `afterCreate` aggiunge un secondo livello idempotente.

### Invio email alla creazione ticket (oc:8040)
- **Due mail class separate**: `CustomerNewStoryCreated` (invariata) e `DevNewStoryCreated` (nuova). Differenze concrete: corpo (`customer_request` vs `description` con fallback) e rotta Nova (`/resources/customer-stories/` vs `/resources/stories/`). Unificazione in `NewStoryCreated` con parametro rotta è possibile in futuro a basso costo.
- **Dev creatore incluso nei destinatari**: nessuna esclusione — il dev che crea il ticket riceve l'email come tutti gli altri.

### Invio email creator su Released (oc:7977)
- **Nessuna guard sul blocco creator-released**: rimosse tutte le guard di deduplicazione (`creator != tester`, `creator != assignee`) e la self-notification. Per `released`, nessun altro path notifica tester o assignee — le guard erano inutili e bloccavano i developer-creator (auto-assign `tester_id = creator_id` nel hook `created`).
- **Non toccare il hook `created`**: il bug era nella logica email, non nell'auto-assign del tester. Principio: minimo scope.

### API endpoint GET /me (oc:7974)
- Closure inline in `routes/api.php` invece di un controller dedicato — accettato consapevolmente per semplicità; il progetto non usa `php artisan route:cache` in produzione

## Testing
I test girano sul **DB di supporto `orchestrator_test`** (configurato in `phpunit.xml`), non sul DB principale — nessun override necessario:

```bash
docker exec php81_orchestrator php artisan test --filter=TestClassName
```

Verificato giu 2026: PostgreSQL 17.5 con `pgvector` 0.8.2 e PostGIS 3.5.2; `orchestrator_test` esiste con tutte le migration applicate (la vecchia nota "pgvector non disponibile su PG 14" è obsoleta). Se il DB di test restasse indietro con le migration:

```bash
docker exec php81_orchestrator bash -c "DB_DATABASE=orchestrator_test php artisan migrate"
```

Tutti i test Feature usano `DatabaseTransactions` (rollback automatico). **Non usare** `DB_DATABASE=orchestrator` per i test: punterebbe al DB reale.
