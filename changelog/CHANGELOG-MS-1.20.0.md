# CHANGELOG MS-1.20.0

**Release Date:** 22/11/2025  
**Version:** MS-1.20.0

## 🚀 New Features

### Sistema Report Attività
- **Gestione Report Attività Mensili e Annuali** - Nuovo sistema completo per la gestione e generazione automatica di report attività per clienti e organizzazioni
  - Creazione report mensili e annuali per singolo cliente o organizzazione
  - Generazione automatica PDF con elenco ticket completati nel periodo
  - Filtri avanzati per periodo, tipo report, proprietario e organizzazione
  - Traduzione automatica dei report in base alla lingua preferita del proprietario

- **Generazione Automatica Report Mensili** - Comando Artisan schedulabile per generare automaticamente tutti i report mensili del mese precedente
  - Configurabile via variabile d'ambiente `ENABLE_GENERATE_MONTHLY_ACTIVITY_REPORTS`
  - Esecuzione automatica il 1° di ogni mese alle 12:00 (timezone Europe/Rome)
  - Generazione asincrona dei PDF tramite job queue (Horizon)

- **Interfaccia Customer per Download Report** - Nuova risorsa Nova per i clienti per visualizzare e scaricare i propri report attività
  - Menu dedicato "Report Attività" per i clienti
  - Visualizzazione lista report disponibili con filtri
  - Download diretto dei PDF generati

- **Calcolo Automatico Date Ticket** - Nuovo sistema per tracciare le date di rilascio e completamento dei ticket
  - Campi `released_at` e `done_at` aggiunti alla tabella `stories`
  - Calcolo automatico delle date dai log delle modifiche di stato
  - Comando Artisan `story:calculate-dates` per ricalcolare date per tutti i ticket o un subset specifico
  - Observer per aggiornare automaticamente le date quando i ticket cambiano stato

- **Miglioramenti Interfaccia Ticket** - Miglioramenti all'interfaccia di visualizzazione e duplicazione ticket
  - Campo "Storico e attività del ticket" reso collassabile
  - Duplicazione ticket preserva `problem_reason` e `waiting_reason` quando presenti
  - Miglioramenti interfaccia ticket archiviati con nuove colonne `released_at` e `done_at`

## 🔧 Improvements

### Comandi Artisan
- **Nuovo comando `story:calculate-dates`** - Calcola e aggiorna le date `released_at` e `done_at` per i ticket
  - Supporto per filtri per creator, user, o tutti i ticket
  - Calcolo basato sui log delle modifiche di stato
  - Utilizzo del servizio `StoryDateService` per logica centralizzata

- **Nuovo comando `orchestrator:activity-report-generate`** - Genera report attività mensili o annuali
  - Generazione per tutti i clienti/organizzazioni con ticket completati nel periodo
  - Supporto per generazione manuale o schedulata
  - Creazione job asincroni per generazione PDF

### Script Deployment
- **Miglioramenti script produzione** - Aggiunti comandi automatici allo script di deployment
  - Esecuzione automatica `story:calculate-dates` dopo deploy
  - Esecuzione automatica `orchestrator:activity-report-generate` dopo deploy
  - Creazione automatica directory necessarie per PDF
  - Configurazione symbolic link per accesso pubblico ai PDF

### Database e Migrations
- **Nuove tabelle**:
  - `activity_reports` - Gestione report attività
  - `activity_report_story` - Tabella pivot per relazioni report-ticket
  
- **Nuove colonne**:
  - `stories.released_at` - Data di rilascio del ticket
  - `stories.done_at` - Data di completamento del ticket
  - `users.activity_report_language` - Lingua preferita per report (it/en)
  - `organizations.activity_report_language` - Lingua preferita per report (it/en)

- **Vincolo univoco** - Aggiunto vincolo univoco su `activity_reports` per prevenire duplicati

### Observer e Servizi
- **Nuovo `ActivityReportObserver`** - Observer per aggiornare automaticamente le relazioni report-ticket
  - Aggiornamento automatico dei ticket associati quando cambiano date/periodo del report
  - Sincronizzazione dei ticket basata su `done_at` e proprietario

- **Nuovo `StoryObserver`** - Miglioramenti all'observer per aggiornare le date ticket
  - Aggiornamento automatico `released_at` quando status cambia a "released"
  - Aggiornamento automatico `done_at` quando status cambia a "done"

- **Nuovo `StoryDateService`** - Servizio per logica di calcolo date ticket
  - Metodi per recuperare date più recenti dai log
  - Logica centralizzata per calcolo date da `story_logs`

### Interfaccia Nova
- **Nuova risorsa `ActivityReport`** - Risorsa Nova completa per gestione report attività (Admin)
  - Colonne unificate per visualizzazione compatta (Owner, Period, PDF URL)
  - Filtri per owner_type, report_type, customer, organization, year, month
  - Action per generazione PDF solo se associati ticket
  - Disabilitate operazioni manuali (create/update/delete) - solo generazione automatica

- **Nuova risorsa `CustomerActivityReport`** - Risorsa Nova per clienti per visualizzare i propri report
  - Visualizzazione solo dei report del cliente loggato
  - Download PDF diretto
  - Interfaccia semplificata per utenti customer

- **Miglioramenti risorsa `ArchivedStories`** - Aggiunte nuove colonne e miglioramenti
  - Colonne `released_at` e `done_at` sortabili (solo data)
  - Colonna compatta "TITOLO / ASSIGNED HOURS / INFORMAZIONI" su più righe
  - Rimossa colonna "scadenze"
  - Campo `updated_at` con solo data (rimossa ora)

### Configurazione
- **Nuove variabili ambiente**:
  - `PLATFORM_NAME` - Nome piattaforma per PDF (default: "Centro Servizi Montagna")
  - `PLATFORM_ACRONYM` - Acronimo piattaforma per nomi file PDF (default: "CSM")
  - `ENABLE_GENERATE_MONTHLY_ACTIVITY_REPORTS` - Abilita generazione automatica mensile (default: false)

## 🐛 Bug Fixes

### Generazione PDF
- **Risolto problema paginazione PDF** - Rimossa paginazione problematica dal footer dei PDF report attività
- **Risolto problema sovrapposizione footer** - Corretti margini e layout footer per evitare sovrapposizioni

### Duplicazione Ticket
- **Fix duplicazione `problem_reason` e `waiting_reason`** - I ticket duplicati ora preservano correttamente questi campi quando presenti

### Autorizzazioni Nova
- **Fix autorizzazioni `ActivityReport`** - Corretti metodi `authorizedTo...` per compatibilità con base class Nova
  - Tipo corretti per parametri `$request` (Request vs NovaRequest)
  - Rimossi type hints non compatibili su parametro `$relationship`

## 📋 Technical Details

### File Creati
- `app/Console/Commands/CalculateStoryDates.php` - Comando per calcolo date ticket
- `app/Console/Commands/GenerateMonthlyActivityReports.php` - Comando per generazione report mensili
- `app/Enums/OwnerType.php` - Enum per tipo proprietario (customer/organization)
- `app/Enums/ReportType.php` - Enum per tipo report (annual/monthly)
- `app/Http/Controllers/ActivityReportPdfController.php` - Controller per generazione PDF
- `app/Jobs/GenerateActivityReportPdfJob.php` - Job per generazione asincrona PDF
- `app/Models/ActivityReport.php` - Model per report attività
- `app/Nova/Actions/GenerateActivityReportPdf.php` - Action Nova per generazione PDF
- `app/Nova/ActivityReport.php` - Risorsa Nova per gestione report (Admin)
- `app/Nova/CustomerActivityReport.php` - Risorsa Nova per visualizzazione report (Customer)
- `app/Nova/Filters/ActivityReportMonthFilter.php` - Filtro per mese
- `app/Nova/Filters/ActivityReportOwnerTypeFilter.php` - Filtro per tipo proprietario
- `app/Nova/Filters/ActivityReportReportTypeFilter.php` - Filtro per tipo report
- `app/Nova/Filters/ActivityReportYearFilter.php` - Filtro per anno
- `app/Observers/ActivityReportObserver.php` - Observer per report attività
- `app/Services/StoryDateService.php` - Servizio per calcolo date ticket
- `docs/ARTISAN_COMMANDS.md` - Documentazione completa comandi Artisan
- `docs/AUTOMATIC_STATUS_CHANGES.md` - Documentazione modifiche automatiche stato ticket
- `changelog/MS-1.20.0-ENV-CHANGES.md` - Guida variabili .env per deployment

### File Modificati
- `app/Console/Kernel.php` - Aggiunta schedulazione generazione report mensili
- `app/Models/Story.php` - Aggiunti campi `released_at` e `done_at`, observer per aggiornamento date
- `app/Models/User.php` - Aggiunto campo `activity_report_language`
- `app/Models/Organization.php` - Aggiunto campo `activity_report_language`
- `app/Nova/Actions/DuplicateStory.php` - Preservazione `problem_reason` e `waiting_reason` nella duplicazione
- `app/Nova/ArchivedStories.php` - Aggiunte colonne `released_at` e `done_at`, colonna compatta
- `app/Nova/Story.php` - Miglioramenti campo storico ticket (collassabile)
- `app/Nova/Organization.php` - Aggiunto campo `activity_report_language`
- `app/Nova/User.php` - Aggiunto campo `activity_report_language`
- `app/Observers/StoryObserver.php` - Aggiunta logica aggiornamento date automatico
- `app/Providers/NovaServiceProvider.php` - Aggiunte nuove risorse al menu
- `config/orchestrator.php` - Aggiunte configurazioni per piattaforma e generazione report
- `scripts/deploy_prod.sh` - Aggiunti comandi per aggiornamento date e generazione report
- `scripts/deploy_local_with_prod.sh` - Aggiunti comandi per setup Horizon e verifica
- `routes/web.php` - Aggiunta route per download PDF report
- `tests/Feature/DuplicateStoryTest.php` - Aggiunti test per duplicazione `problem_reason` e `waiting_reason`

### Database Migrations
- `2025_11_21_195426_add_released_at_and_done_at_to_stories_table.php` - Aggiunge campi date ticket
- `2025_11_22_111708_create_activity_reports_table.php` - Crea tabella report attività
- `2025_11_22_111747_create_activity_report_story_table.php` - Crea tabella pivot report-ticket
- `2025_11_22_121749_add_activity_report_language_to_users_and_organizations_tables.php` - Aggiunge campo lingua
- `2025_11_22_160544_add_unique_constraint_to_activity_reports_table.php` - Aggiunge vincolo univoco

### Database
- **Nuove tabelle**: `activity_reports`, `activity_report_story`
- **Tabelle modificate**: `stories` (aggiunti `released_at`, `done_at`), `users` (aggiunto `activity_report_language`), `organizations` (aggiunto `activity_report_language`)

## 📝 Notes

### Deployment
- **Variabili ambiente richieste**: Vedere `changelog/MS-1.20.0-ENV-CHANGES.md` per lista completa variabili da aggiungere al `.env`
- **Migrations richieste**: Eseguire tutte le migrations per le nuove tabelle e colonne
- **Comandi post-deployment**:
  - Eseguire `story:calculate-dates` per calcolare date ticket esistenti
  - Eseguire `orchestrator:activity-report-generate` per generare report mensili iniziali (opzionale)
- **Horizon**: Assicurarsi che Horizon sia attivo per elaborazione job PDF asincroni

### Configurazione
- **Generazione automatica report**: Disabilitata di default (`ENABLE_GENERATE_MONTHLY_ACTIVITY_REPORTS=false`)
  - Per abilitare, impostare a `true` nel `.env`
  - I report verranno generati automaticamente il 1° di ogni mese alle 12:00
- **Lingua report**: Configurabile per utente/organizzazione (default: italiano)
- **Piattaforma**: Nome e acronimo configurabili via variabili ambiente per personalizzazione PDF

### Backward Compatibility
- **Completamente retrocompatibile** - Nessuna breaking change
- **Campi opzionali** - Tutte le nuove colonne sono nullable o hanno default appropriati
- **Report esistenti** - Nessun impatto su funzionalità esistenti

### Testing
- **Test completati**: Tutti i 72 test passano
- **Nuovi test**: Aggiunti test per duplicazione `problem_reason` e `waiting_reason`
- **Test unitari**: Aggiunti test per `StoryDateService`

