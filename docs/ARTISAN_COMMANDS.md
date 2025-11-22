# Artisan Commands Documentation

Questo documento elenca tutti i comandi Artisan definiti nell'applicazione Orchestra, con signature, descrizione, opzioni e argomenti.

## 📋 Indice {#indice}

1. [Story Commands](#story-commands)
   - 1.1. [story:calculate-dates](#11-storycalculate-dates)
   - 1.2. [story:progress-to-todo](#12-storyprogress-to-todo)
   - 1.3. [story:scrum-to-done](#13-storyscrum-to-done)
   - 1.4. [story:auto-update-status](#14-storyauto-update-status)
   - 1.5. [story:update-status](#15-storyupdate-status)
   - 1.6. [story:send-waiting-reminder](#16-storysend-waiting-reminder)
2. [Sync Commands](#sync-commands)
   - 2.1. [sync:stories-calendar](#21-syncstories-calendar)
3. [Orchestrator Commands](#orchestrator-commands)
   - 3.1. [orchestrator:import](#31-orchestratorimport)
   - 3.2. [orchestrator:import-products](#32-orchestratorimport-products)
   - 3.3. [orchestrator:process-inbound-emails](#33-orchestratorprocess-inbound-emails)
   - 3.4. [orchestrator:initialize-scores](#34-orchestratorinitialize-scores)
   - 3.5. [orchestrator:activity-report-generate](#35-orchestratoractivity-report-generate)
4. [Database Commands](#database-commands)
   - 4.1. [app:initialize-database](#41-appinitialize-database)
5. [User Commands](#user-commands)
   - 5.1. [users-stories-log-dispatch](#51-users-stories-log-dispatch)
6. [Tag Commands](#tag-commands)
   - 6.1. [tag:projects](#61-tagprojects)

---

## Story Commands

↑ [Torna all'indice](#indice)

### 1.1. story:calculate-dates

↑ [Torna all'indice](#indice)

**File:** `app/Console/Commands/CalculateStoryDates.php`

**Signature:**
```bash
story:calculate-dates 
    {--creator= : Filter by creator ID}
    {--user= : Filter by assigned user ID}
    {--story= : Calculate dates for a specific story ID}
```

**Description:**
Calculate and update released_at and done_at dates for stories from story logs.

**Opzioni:**
- `--creator=` - Filtra le story per creator ID
- `--user=` - Filtra le story per assigned user ID (developer assegnato)
- `--story=` - Calcola le date per una specifica story ID

**Comportamento:**
- Calcola le date `released_at` e `done_at` per le story basandosi sui log in `story_logs`
- Le date vengono cercate nei log e aggiornate se trovate
- Può essere filtrato per creator, user assegnato o story specifica
- Mostra una progress bar durante l'elaborazione
- Report finale con numero di story processate e aggiornate

**Esempio:**
```bash
# Calcola date per tutte le story
php artisan story:calculate-dates

# Calcola date per story di un creator specifico
php artisan story:calculate-dates --creator=123

# Calcola date per story assegnate a un user specifico
php artisan story:calculate-dates --user=456

# Calcola date per una story specifica
php artisan story:calculate-dates --story=789
```

---

### 1.2. story:progress-to-todo

↑ [Torna all'indice](#indice)

**File:** `app/Console/Commands/MoveProgressStoriesInTodoCommand.php`

**Signature:**
```bash
story:progress-to-todo
```

**Description:**
Update all progress stories status from progress to todo.

**Comportamento:**
- Trova tutte le story con status `Progress`
- Imposta lo status a `Todo`
- Salva ogni story (triggera eventi)

**Schedule:**
- Eseguito giornalmente alle 18:00 (timezone: Europe/Rome)
- Configurabile via `config('orchestrator.tasks.story_progress_to_todo')`

**Esempio:**
```bash
php artisan story:progress-to-todo
```

**Note:**
- Questo comando è schedulato automaticamente (vedi `app/Console/Kernel.php`)
- Usa `save()` quindi triggera eventi Eloquent e crea entry in `story_logs`

---

### 1.3. story:scrum-to-done

↑ [Torna all'indice](#indice)

**File:** `app/Console/Commands/MoveScrumStoriesInDoneCommand.php`

**Signature:**
```bash
story:scrum-to-done
```

**Description:**
Update all scrumm stories status to done.

**Comportamento:**
- Trova tutte le story di tipo `Scrum` create o aggiornate oggi
- Imposta lo status a `Done`
- Salva con `saveQuietly()` (NON triggera eventi)

**Schedule:**
- Eseguito giornalmente alle 16:00 (timezone: Europe/Rome)
- Configurabile via `config('orchestrator.tasks.story_scrum_to_done')`

**Esempio:**
```bash
php artisan story:scrum-to-done
```

**Note:**
- Questo comando è schedulato automaticamente (vedi `app/Console/Kernel.php`)
- Usa `saveQuietly()` quindi NON crea entry in `story_logs`

---

### 1.4. story:auto-update-status

↑ [Torna all'indice](#indice)

**File:** `app/Console/Commands/AutoUpdateStoryStatus.php`

**Signature:**
```bash
story:auto-update-status
```

**Description:**
Automatically updates story statuses from Released to Done after 3 working days.

**Comportamento:**
- Trova tutte le story con status `Released` aggiornate almeno 3 giorni lavorativi fa
- I "3 giorni lavorativi" escludono sabato e domenica
- Imposta lo status a `Done`
- Salva con `saveQuietly()` (NON triggera eventi)

**Schedule:**
- Eseguito giornalmente alle 07:45 (timezone: Europe/Rome)
- Configurabile via `config('orchestrator.tasks.story_auto_update_status')`

**Esempio:**
```bash
php artisan story:auto-update-status
```

**Note:**
- Questo comando è schedulato automaticamente (vedi `app/Console/Kernel.php`)
- Usa `saveQuietly()` quindi NON crea entry in `story_logs`

---

### 1.5. story:update-status

↑ [Torna all'indice](#indice)

**File:** `app/Console/Commands/UpdateStoryStatusCommand.php`

**Signature:**
```bash
story:update-status
```

**Description:**
Update story status from new to assigned if a developer is assigned.

**Comportamento:**
- Trova tutte le story con status `New` che hanno un `user_id` assegnato
- Imposta lo status a `Assigned`
- Se una story ha `user_id` ma non ha `creator_id`, imposta `creator_id = user_id`
- Salva ogni story (triggera eventi)

**Esempio:**
```bash
php artisan story:update-status
```

**Note:**
- Questo comando può essere eseguito manualmente per sincronizzare lo status di story esistenti
- Usa `save()` quindi triggera eventi Eloquent e crea entry in `story_logs`

---

### 1.6. story:send-waiting-reminder

↑ [Torna all'indice](#indice)

**File:** `app/Console/Commands/SendWaitingStoryReminder.php`

**Signature:**
```bash
story:send-waiting-reminder
```

**Description:**
Sends a reminder email to customers for stories in Waiting status after 3 working days.

**Comportamento:**
- Trova tutte le story con status `Waiting` create almeno 3 giorni lavorativi fa
- Verifica se devono ricevere un reminder basandosi sull'ultima modifica rilevante in `story_logs`
- Invia un'email di reminder al creator della story
- Crea una entry in `story_logs` per tracciare l'invio del reminder

**Logica:**
1. Cerca in `story_logs` l'ultima entry dove `changes->status = Waiting`
2. Cerca l'ultima modifica rilevante dopo quel punto (escludendo le entry con solo `watch`)
3. Se l'ultima modifica rilevante è più vecchia di 3 giorni lavorativi, invia il reminder

**Esempio:**
```bash
php artisan story:send-waiting-reminder
```

**Note:**
- I "3 giorni lavorativi" escludono sabato e domenica
- Questo comando NON modifica lo status della story
- Crea entry in `story_logs` con `user_id = 1` (sistema)

---

## Sync Commands

↑ [Torna all'indice](#indice)

### 2.1. sync:stories-calendar

↑ [Torna all'indice](#indice)

**File:** `app/Console/Commands/SyncStoriesWithGoogleCalendar.php`

**Signature:**
```bash
sync:stories-calendar {developerEmail?}
```

**Description:**
Sync assigned stories with Google Calendar.

**Argomenti:**
- `developerEmail` (opzionale) - Email del developer per cui sincronizzare. Se omesso, sincronizza per tutti i developers.

**Comportamento:**
- Sincronizza le story assegnate con Google Calendar
- Se viene passata un'email, sincronizza solo per quel developer
- Se non viene passata un'email, sincronizza per tutti i developers con ruolo `developer`
- Elimina tutti gli eventi esistenti per oggi che iniziano con "OC:"
- Crea nuovi eventi in Google Calendar per:
  - Story di tipo `Scrum` create oggi
  - Story con status `Todo` o `Progress`
  - Story con status `Test` (per il tester)
  - Story con status `Tested` (create dal developer o assegnate al developer)
  - Story con status `Waiting`

**Colori Eventi:**
- Feature: Blue (1)
- Helpdesk: Green (2)
- Tested: Grape (3)
- Waiting: Light Gray (5)
- Testing: Tangerine (6)
- Scrum: (7)
- Default: Yellow (8)
- Bug: Bold Red (11)

**Schedule:**
- Eseguito giornalmente alle 07:45 (timezone: Europe/Rome)
- Configurabile via `config('orchestrator.tasks.sync_stories_calendar')`

**Esempio:**
```bash
# Sincronizza per tutti i developers
php artisan sync:stories-calendar

# Sincronizza per un developer specifico
php artisan sync:stories-calendar developer@example.com
```

**Note:**
- Richiede configurazione Google Calendar (vedi `config/services.php`)
- Gli eventi vengono creati sul calendario del developer (identificato dall'email)

---

## Orchestrator Commands

↑ [Torna all'indice](#indice)

### 3.1. orchestrator:import

↑ [Torna all'indice](#indice)

**File:** `app/Console/Commands/OrchestratorImport.php`

**Signature:**
```bash
orchestrator:import
```

**Description:**
Import data from WMPM.

**Comportamento:**
- Importa dati da WMPM (WebMapp Platform Manager)
- Importa le Apps da `https://geohub.webmapp.it/api/v1/app/all`
- Fa backup della tabella `user_app` prima dell'import
- Truncate e ricrea le Apps
- Ripristina i dati nella tabella `user_app` dopo l'import

**Esempio:**
```bash
php artisan orchestrator:import
```

**Note:**
- Questo comando richiede una connessione internet per accedere alle API di GeoHub
- Il codice per importare i Layers è commentato ma presente nel file

---

### 3.2. orchestrator:import-products

↑ [Torna all'indice](#indice)

**File:** `app/Console/Commands/ImportProducts.php`

**Signature:**
```bash
orchestrator:import-products {path : Path to the Excel file}
```

**Description:**
Import products from an Excel file.

**Argomenti:**
- `path` (richiesto) - Percorso del file Excel da importare (relativo al disco `importer`)

**Comportamento:**
- Importa prodotti da un file Excel
- Il file deve essere salvato nel disco `importer` (vedi `config/filesystems.php`)
- Usa la classe `ProductsImport` per processare il file

**Esempio:**
```bash
php artisan orchestrator:import-products products.xlsx
```

**Note:**
- Il file Excel deve essere salvato nel disco `importer` configurato
- Usa il package `maatwebsite/excel` per leggere i file Excel

---

### 3.3. orchestrator:process-inbound-emails

↑ [Torna all'indice](#indice)

**File:** `app/Console/Commands/ProcessInboundEmailsCommand.php`

**Signature:**
```bash
orchestrator:process-inbound-emails
```

**Description:**
Dispatch jobs to process inbound emails.

**Comportamento:**
- Dispatches il job `ProcessInboundEmails` che processa le email in arrivo
- Il job legge email non lette dalla casella configurata (IMAP)
- Crea nuove story per ogni email da un utente registrato
- Associa gli allegati alle story create

**Schedule:**
- Eseguito ogni 5 minuti (se abilitato)
- Configurabile via `config('orchestrator.tasks.process_inbound_emails')`

**Esempio:**
```bash
php artisan orchestrator:process-inbound-emails
```

**Note:**
- Questo comando è schedulato automaticamente (vedi `app/Console/Kernel.php`)
- Richiede configurazione IMAP (vedi `config/imap.php` o configurazione services)
- Il job effettivo è `app/Jobs/ProcessInboundEmails.php`

---

### 3.4. orchestrator:initialize-scores

↑ [Torna all'indice](#indice)

**File:** `app/Console/Commands/InitializeNullCustomersScore.php`

**Signature:**
```bash
orchestrator:initialize-scores
```

**Description:**
Initializes null scores for customers.

**Comportamento:**
- Inizializza i punteggi nulli per i clienti
- Trova tutti i clienti con punteggio nullo
- Imposta un punteggio di default (probabilmente 0)

**Esempio:**
```bash
php artisan orchestrator:initialize-scores
```

**Note:**
- Questo comando può essere usato per inizializzare i punteggi dei clienti dopo una migrazione o reset

---

### 3.5. orchestrator:activity-report-generate

↑ [Torna all'indice](#indice)

**File:** `app/Console/Commands/GenerateMonthlyActivityReports.php`

**Signature:**
```bash
orchestrator:activity-report-generate 
    {--year= : The year for the report (defaults to previous month)}
    {--month= : The month for the report (defaults to previous month)}
```

**Description:**
Generate monthly activity reports for all customers and organizations with at least one ticket in the specified month.

**Opzioni:**
- `--year=` - L'anno per il report (default: mese precedente)
- `--month=` - Il mese per il report (default: mese precedente)

**Comportamento:**
1. Determina anno e mese (default: mese precedente se non specificati)
2. Itera su tutti i customers con ruolo `Customer`
3. Verifica se il customer ha almeno un ticket con `done_at` nel periodo specificato
4. Se sì, dispatcha un job `GenerateActivityReportPdfJob` per quel customer
5. Itera su tutte le organizzazioni
6. Verifica se l'organizzazione ha almeno un ticket con `done_at` nel periodo (ticket creati da utenti appartenenti all'organizzazione)
7. Se sì, dispatcha un job `GenerateActivityReportPdfJob` per quell'organizzazione
8. I job vengono processati in coda e generano i PDF automaticamente

**Job Dispatched:**
- Il job `GenerateActivityReportPdfJob` crea un `ActivityReport`, sincronizza i ticket associati e genera il PDF

**Schedule:**
- Eseguito il 1° di ogni mese alle 12:00 (timezone: Europe/Rome) per il mese precedente
- Configurabile via `config('orchestrator.tasks.generate_monthly_activity_reports')`
- Abilitabile/disabilitabile via `ENABLE_GENERATE_MONTHLY_ACTIVITY_REPORTS` in `.env` (default: `false`)

**Esempio:**
```bash
# Genera report per il mese precedente (default)
php artisan orchestrator:activity-report-generate

# Genera report per un mese specifico
php artisan orchestrator:activity-report-generate --year=2025 --month=11
```

**Note:**
- Questo comando è schedulato automaticamente (vedi `app/Console/Kernel.php`)
- I job vengono processati in coda (vedi `config/queue.php`)
- I PDF vengono salvati in `storage/app/public/activity-reports/`
- I report vengono creati solo per customers/organizzazioni che hanno almeno un ticket completato nel periodo

---

## Database Commands

↑ [Torna all'indice](#indice)

### 4.1. app:initialize-database

↑ [Torna all'indice](#indice)

**File:** `app/Console/Commands/InitializeDatabase.php`

**Signature:**
```bash
app:initialize-database {--force : Force initialization without confirmation}
```

**Description:**
Initialize database with production-ready data (admin, developers, customers, tags).

**Opzioni:**
- `--force` - Forza l'inizializzazione senza richiedere conferma

**Comportamento:**
1. **Clear Database:**
   - Truncate tutte le tabelle (eccetto `migrations`)
   - Usa `TRUNCATE TABLE ... RESTART IDENTITY CASCADE` (PostgreSQL)

2. **Run Migrations:**
   - Esegue tutte le migrazioni con `--force`

3. **Seed Initial Data:**
   - Crea un utente admin (info@montagnaservizi.com / M0ntagn@S3rviz!)
   - Crea developer users da `config('initialization.developers')`
   - Crea customer users da `config/customer.list` (formato: `email;name`)
   - Crea tags predefiniti

**Tags Creati:**
- SOA/Servizi di Amministrazione Ordinaria
- SOAD/Servizi di segreteria a Distanza
- SOAD/Verbalizzazione online
- SOAD/Organizzazione Drive
- SOAD/Controllo dei budget
- SOAD/Invio Newsletter
- SOAD/Raccolta dati moduli online
- SOAD/Pagamenti online
- SOAD/Inserimento contenuti digitali
- SOAC/Servizi di consulenza a distanza
- SOAC/Terzo Settore
- SOAC/Commercialista
- SOAC/Ufficio Legale
- FS/Fundraising
- FS/Monitoraggio
- FS/Progettazione
- FS/Accompagnamento
- FS/Coordinamento
- FS/Rendicontazione
- MS/Montagna Servizi
- MS/Amministrazione
- MS/Formazione
- MS/Investimenti
- MS/Team Meeting

**Esempio:**
```bash
# Con conferma
php artisan app:initialize-database

# Senza conferma
php artisan app:initialize-database --force
```

**⚠️ Avviso:**
- Questo comando **CANCELLA TUTTI I DATI** dal database
- Usa `--force` per evitare la richiesta di conferma

**Note:**
- Richiede file `config/customer.list` per i customer users
- Richiede configurazione `config('initialization.developers')` per i developer users
- Le password di default sono: `developer123` per developers, `customer123` per customers

---

## User Commands

↑ [Torna all'indice](#indice)

### 5.1. users-stories-log-dispatch

↑ [Torna all'indice](#indice)

**File:** `app/Console/Commands/DispatchUpdateUsersStoriesLogCommand.php`

**Signature:**
```bash
users-stories-log-dispatch 
    {--story_id= : Specific story ID to update}
    {--user_id= : Specific user ID to update}
```

**Description:**
Dispatch job to update users_stories_log table.

**Opzioni:**
- `--story_id=` - Story ID specifica da aggiornare
- `--user_id=` - User ID specifico da aggiornare

**Comportamento:**
- Dispatches il job `UpdateUsersStoriesLogJob`
- Se viene passato `--story_id`, aggiorna solo quella story
- Se viene passato `--user_id`, aggiorna solo quel user
- Se non vengono passate opzioni, aggiorna tutte le story

**Esempio:**
```bash
# Aggiorna tutte le story
php artisan users-stories-log-dispatch

# Aggiorna una story specifica
php artisan users-stories-log-dispatch --story_id=123

# Aggiorna un user specifico
php artisan users-stories-log-dispatch --user_id=456
```

**Note:**
- Il job effettivo è `app/Jobs/UpdateUsersStoriesLogJob.php`
- Aggiorna la tabella `users_stories_log` che traccia il tempo lavorato per ogni user su ogni story

---

## Tag Commands

↑ [Torna all'indice](#indice)

### 6.1. tag:projects

↑ [Torna all'indice](#indice)

**File:** `app/Console/Commands/TagAllProjects.php`

**Signature:**
```bash
tag:projects
```

**Description:**
Creates a tag for each project and associates it with the project.

**Comportamento:**
- Trova tutti i progetti che non hanno ancora un tag associato
- Crea un nuovo tag con il nome del progetto
- Associa il tag al progetto
- Se il progetto appartiene a un customer, associa anche il tag al customer

**Esempio:**
```bash
php artisan tag:projects
```

**Note:**
- Questo comando può essere eseguito più volte in sicurezza
- Controlla se un tag esiste già prima di crearlo
- Se il tag esiste già, lo associa comunque al progetto

---

## 📊 Riepilogo Comandi

| Comando | File | Schedule | Opzioni/Argomenti |
|---------|------|----------|-------------------|
| **Story Commands** |
| `story:calculate-dates` | `CalculateStoryDates.php` | Manuale | `--creator`, `--user`, `--story` |
| `story:progress-to-todo` | `MoveProgressStoriesInTodoCommand.php` | 18:00 daily | - |
| `story:scrum-to-done` | `MoveScrumStoriesInDoneCommand.php` | 16:00 daily | - |
| `story:auto-update-status` | `AutoUpdateStoryStatus.php` | 07:45 daily | - |
| `story:update-status` | `UpdateStoryStatusCommand.php` | Manuale | - |
| `story:send-waiting-reminder` | `SendWaitingStoryReminder.php` | Manuale | - |
| **Sync Commands** |
| `sync:stories-calendar` | `SyncStoriesWithGoogleCalendar.php` | 07:45 daily | `developerEmail?` |
| **Orchestrator Commands** |
| `orchestrator:import` | `OrchestratorImport.php` | Manuale | - |
| `orchestrator:import-products` | `ImportProducts.php` | Manuale | `path` (required) |
| `orchestrator:process-inbound-emails` | `ProcessInboundEmailsCommand.php` | Ogni 5 min | - |
| `orchestrator:initialize-scores` | `InitializeNullCustomersScore.php` | Manuale | - |
| `orchestrator:activity-report-generate` | `GenerateMonthlyActivityReports.php` | 1° del mese 12:00 | `--year`, `--month` |
| **Database Commands** |
| `app:initialize-database` | `InitializeDatabase.php` | Manuale | `--force` |
| **User Commands** |
| `users-stories-log-dispatch` | `DispatchUpdateUsersStoriesLogCommand.php` | Manuale | `--story_id`, `--user_id` |
| **Tag Commands** |
| `tag:projects` | `TagAllProjects.php` | Manuale | - |

---

## 🔍 Note Importanti

1. **Comandi Schedulati:**
   - I comandi schedulati possono essere abilitati/disabilitati via variabili di ambiente in `.env`
   - Verifica `config('orchestrator.tasks.*')` per vedere quali task sono abilitati
   - Esegui `php artisan schedule:list` per vedere i task configurati

2. **Comandi con `saveQuietly()`:**
   - `story:scrum-to-done` e `story:auto-update-status` usano `saveQuietly()`
   - Questi comandi NON creano entry in `story_logs` perché non triggerano eventi Eloquent

3. **Comandi con `save()`:**
   - Gli altri comandi che modificano story usano `save()`
   - Questi comandi creano entry in `story_logs` tramite `StoryObserver`

4. **Esecuzione Manuale:**
   - Tutti i comandi schedulati possono essere eseguiti manualmente
   - Usa `php artisan schedule:run` per eseguire tutti i task schedulati

---

**Ultimo aggiornamento:** Novembre 2025  
**Versione:** MS-1.20.0

