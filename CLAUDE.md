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
| Metrica "Todo >1g" nella card team performance | oc:8192 | `app/Services/Metrics/StoryMetricsCalculator.php`, `app/Http/Controllers/Nova/TeamPerformanceController.php`, `nova-components/team-performance/dist/js/card.js` | Nuovo metodo `todoStagnationTotalDays()` (somma tutti gli intervalli todo); esposto come colonna per-ticket e KPI aggregato; etichetta "Todo >1g" perché `workingDaysBetween` conta giorni interi; 0 giorni mostrato come `—` |
| Fix download allegati (path generator ibrido) | oc:8028 | `app/Services/MediaLibrary/OrchestratorPathGenerator.php`, `app/Providers/AppServiceProvider.php` | Generator C→B→A ripristina accesso ai 605/631 media legacy; wm-package sovrascriveva path_generator con WmfePathGenerator |
| PDF preventivo — logo visibile | oc:8047 | `resources/views/quote-pdf.blade.php`, `public/images/logo.png` | Usa `file://` path invece di data URI base64; DomPDF non renderizza data URI in questo setup |
| Sync calendario asincrona con debounce | oc:8044 | `app/Jobs/SyncDeveloperCalendarJob.php`, `app/Observers/StoryObserver.php`, `app/Console/Commands/SyncStoriesWithGoogleCalendar.php`, `tests/Feature/SyncDeveloperCalendarJobTest.php` | La sync Google Calendar al save di una Story è un job in coda (debounce 60s, unique per email); save Nova < 2s, bulk edit senza timeout |
| Hetzner Monitoring | oc:7944 | `config/hetzner.php`, `app/Services/HetznerApiService.php`, `app/Http/Controllers/HetznerMonitoringController.php`, `app/Exports/HetznerExport.php`, `nova-components/hetzner-monitoring/`, `app/Nova/Dashboards/HetznerMonitoring.php` | Dashboard Nova con tabella per progetto Hetzner: server, floating IP, volumes, LB, snapshot. Cache Redis 15 min. Export CSV. |
| Auto-revert ticket in progress quando dev è offline su Slack | oc:8136 | `app/Console/Commands/SlackRevertProgressCommand.php`, `app/Services/SlackService.php`, `database/migrations/2026_06_25_120000_add_slack_user_id_to_users_table.php`, `app/Models/User.php`, `app/Nova/User.php`, `config/services.php`, `app/Console/Kernel.php` | Comando schedulato ogni 20 min (12-18) che verifica presenza Slack dei dev con ticket in progress; se offline → saveQuietly() + StoryLog manuale |
| API CRUD per Tag con attach/detach stories | oc:8155 | `app/Http/Controllers/Api/TagController.php`, `app/Http/Requests/Api/TagApiRequest.php`, `routes/api.php`, `tests/Feature/Api/TagApiTest.php` | GET/POST/PATCH /api/tags, GET /api/tags/{tag}, POST/DELETE /api/tags/{tag}/stories/{story}; solo Developer e Admin; StoryLog su attach/detach |
| Fix tag automatici su update Nova | oc:8051 | `app/Nova/Story.php`, `app/Observers/StoryObserver.php` | Ripristinati `afterCreate`/`afterUpdate` in Nova; try/catch isolati in observer |
| Fix email creazione ticket Scrum | oc:8091 | `app/Models/Story.php`, `tests/Feature/StoryEmailTriggersTest.php` | Alla creazione di un ticket di tipo Scrum nessuna mail viene inviata ai developer; tutti gli altri tipi inviano normalmente |
| Invio email alla creazione ticket | oc:8040 | `app/Mail/DevNewStoryCreated.php`, `resources/views/mails/dev-new-story-created.blade.php`, `app/Models/Story.php`, `tests/Feature/StoryEmailTriggersTest.php` | Alla creazione di qualsiasi ticket tutti i dev ricevono email: `CustomerNewStoryCreated` se creator è customer, `DevNewStoryCreated` altrimenti |
| Invio email creator su Released | oc:7977 | `app/Models/Story.php`, `tests/Feature/StoryEmailTriggersTest.php` | Il creator riceve sempre l'email su status→released, indipendentemente da ruolo, da chi agisce, e dall'auto-assign tester |
| API endpoint GET /me | oc:7974 | `routes/api.php`, `tests/Feature/Api/MeEndpointTest.php` | Restituisce id, name, email dell'utente autenticato via Sanctum |

## Decisioni architetturali

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
