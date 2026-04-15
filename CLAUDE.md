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

## Testing
Tests use the real PostgreSQL database (not SQLite/in-memory). See `phpunit.xml` — `DB_CONNECTION` is not overridden. Run tests inside the container.
