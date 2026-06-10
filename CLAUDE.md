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

# Run tests (uses real PostgreSQL DB, not SQLite)
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
| Invio email creator su Released | oc:7977 | `app/Models/Story.php`, `tests/Feature/StoryEmailTriggersTest.php` | Il creator riceve sempre l'email su status→released, indipendentemente da ruolo, da chi agisce, e dall'auto-assign tester |
| API endpoint GET /me | oc:7974 | `routes/api.php`, `tests/Feature/Api/MeEndpointTest.php` | Restituisce id, name, email dell'utente autenticato via Sanctum |

## Decisioni architetturali

### Invio email creator su Released (oc:7977)
- **Nessuna guard sul blocco creator-released**: rimosse tutte le guard di deduplicazione (`creator != tester`, `creator != assignee`) e la self-notification. Per `released`, nessun altro path notifica tester o assignee — le guard erano inutili e bloccavano i developer-creator (auto-assign `tester_id = creator_id` nel hook `created`).
- **Non toccare il hook `created`**: il bug era nella logica email, non nell'auto-assign del tester. Principio: minimo scope.

### API endpoint GET /me (oc:7974)
- Closure inline in `routes/api.php` invece di un controller dedicato — accettato consapevolmente per semplicità; il progetto non usa `php artisan route:cache` in produzione

## Testing
Tests use the real PostgreSQL database (not SQLite/in-memory). Run tests inside the container.

`phpunit.xml` punta a `orchestrator_test` ma il DB potrebbe non esistere (extension `pgvector` non disponibile su PG 14 blocca le migration). Usare il DB principale con:

```bash
DB_DATABASE=orchestrator php artisan test --filter=TestClassName
```

Sicuro perché tutti i test Feature usano `DatabaseTransactions` (rollback automatico).

## Decisioni architetturali

### Ottimizzazione Costi Hetzner (oc:7944)
- **Nova component self-contained**: `nova-components/hetzner-monitoring/` segue il pattern di `kanban-card` — il componente Vue è un JS puro registrato via `Nova::script()`, senza build step separato. Nessun Webpack/Vite da configurare per aggiunte read-only.
- **Token Hetzner in ENV**: convenzione `HETZNER_TOKEN_<SLUG>=xxx`. Letti dinamicamente da `config/hetzner.php` via `collect($_ENV)`. Aggiungere un nuovo progetto = aggiungere una variabile ENV + restart container (no deploy di codice).
- **Prezzi Volumes/Snapshots hardcodati**: l'API Hetzner Cloud non espone pricing per queste risorse. Valori da documentazione pubblica (mag 2026): Volumes €0.0476/GB/mese, Snapshots €0.0119/GB/mese. Aggiornare `HetznerApiService` se Hetzner modifica i prezzi.
- **Errori per progetto isolati**: un token non valido non blocca gli altri. La cache Redis è per-progetto (`hetzner_project_{slug}`, TTL 15 min).

## Feature disponibili

| Feature | Ticket | Moduli toccati | Note |
|---|---|---|---|
| Hetzner Monitoring | oc:7944 | `config/hetzner.php`, `app/Services/HetznerApiService.php`, `app/Http/Controllers/HetznerMonitoringController.php`, `app/Exports/HetznerExport.php`, `nova-components/hetzner-monitoring/`, `app/Nova/Dashboards/HetznerMonitoring.php` | Dashboard Nova con tabella per progetto Hetzner: server, floating IP, volumes, LB, snapshot. Cache Redis 15 min. Export CSV. |
