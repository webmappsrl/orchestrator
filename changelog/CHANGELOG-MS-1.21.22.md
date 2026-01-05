# CHANGELOG MS-1.21.22

**Release Date:** 05/01/2026  
**Version:** MS-1.21.22

## 🚀 New Features

### Auto-Restore Waiting Stories
- Implementato comando Artisan `orchestrator:autoupdate-waiting` per ripristinare automaticamente i ticket da stato "Waiting" dopo un periodo configurato (default: 7 giorni)
- Il comando ripristina i ticket allo stato precedente recuperato dalla tabella `story_logs`
- Regola speciale: se lo stato precedente è `progress`, `released` o `done`, il ticket viene ripristinato in `todo`
- Aggiunta nota di sviluppo automatica con motivo dell'attesa e giorni trascorsi
- Comando schedulato giornalmente tramite Laravel Scheduler (controllato da `ORCHESTRATOR_AUTORESTORE_WAITING_ENABLED`)
- Configurazione tramite variabili d'ambiente:
  - `ORCHESTRATOR_AUTORESTORE_WAITING_ENABLED` (default: false)
  - `ORCHESTRATOR_AUTORESTORE_WAITING_DAYS` (default: 7)

### Artisan User Configuration
- Aggiunta configurazione centralizzata per l'email dell'utente utilizzato dai comandi Artisan (`ARTISAN_USER_EMAIL`)
- Configurazione in `config/orchestrator.php` con default `orchestrator_artisan@webmapp.it`
- Permette di personalizzare l'utente utilizzato per operazioni automatizzate senza modificare il codice

### Waiting Stories Dashboard Enhancement
- Aggiunta visualizzazione "Giorni di attesa" nella colonna "Ragione dell'attesa" della risorsa Nova `WaitingStory`
- Il numero di giorni viene calcolato automaticamente dalla data di ingresso nello stato Waiting

## 🐛 Bug Fixes

### Gestione Null User negli Observer
- Corretto errore "Attempt to read property 'id' on null" in `StoryObserver` quando l'utente Artisan non esiste nel database
- Aggiunta gestione graceful del caso null user in `StoryObserver`, `MediaObserver` e helper `log_story_activity`
- Il sistema ora logga un warning e continua l'esecuzione invece di generare un errore fatale

### Logica Transizione Stati Auto-Restore
- Corretta logica di transizione stati nel comando `orchestrator:autoupdate-waiting`
- Se lo stato precedente è `progress`, `released` o `done`, il ticket viene correttamente ripristinato in `todo` invece dello stato precedente
- Se lo stato precedente è `todo`, il ticket rimane correttamente in `todo`

## 📚 Documentation

### Documentazione Comandi Artisan
- Aggiunta documentazione completa del comando `orchestrator:autoupdate-waiting` in `docs/ARTISAN_COMMANDS.md`
- Documentazione del comportamento automatico e delle configurazioni disponibili

### Documentazione Transizioni Automatiche
- Aggiornata `docs/AUTOMATIC_STATUS_CHANGES.md` con sezione dedicata all'auto-restore delle storie in Waiting
- Aggiornata documentazione del flusso ticket (`docs/TICKET_STATUS_FLOW.md`) con informazioni sulla transizione automatica Waiting → Stato Precedente
- Aggiornata dashboard Nova `TicketFlow` (`resources/views/ticket-flow-documentation.blade.php`) con informazioni sulla transizione automatica

### Configurazione Ambiente
- Aggiornato `.env-example` con nuove variabili di configurazione:
  - `ORCHESTRATOR_AUTORESTORE_WAITING_ENABLED`
  - `ORCHESTRATOR_AUTORESTORE_WAITING_DAYS`
  - `ARTISAN_USER_EMAIL`

## 📋 Technical Details

### File Creati
- `app/Console/Commands/AutoUpdateWaitingStories.php` - Comando Artisan per auto-restore storie in Waiting

### File Modificati
- `app/Console/Kernel.php` - Aggiunto scheduling del comando `orchestrator:autoupdate-waiting`
- `config/orchestrator.php` - Aggiunte configurazioni `autorestore_waiting`, `autorestore_waiting_days`, `artisan_user_email`
- `app/Observers/StoryObserver.php` - Gestione graceful del caso null user, utilizzo configurazione `artisan_user_email`
- `app/Observers/MediaObserver.php` - Gestione graceful del caso null user, utilizzo configurazione `artisan_user_email`
- `app/Helpers/helpers.php` - Utilizzo configurazione `artisan_user_email` invece di email hardcoded
- `app/Nova/WaitingStory.php` - Aggiunta visualizzazione giorni di attesa nella colonna "Ragione dell'attesa"
- `docs/ARTISAN_COMMANDS.md` - Documentazione comando `orchestrator:autoupdate-waiting`
- `docs/AUTOMATIC_STATUS_CHANGES.md` - Documentazione transizione automatica Waiting → Stato Precedente
- `docs/TICKET_STATUS_FLOW.md` - Aggiornata documentazione flusso ticket con transizione automatica
- `resources/views/ticket-flow-documentation.blade.php` - Aggiornata dashboard TicketFlow con informazioni transizione automatica
- `.env-example` - Aggiunte nuove variabili di configurazione

### Database
- Nessuna migrazione richiesta

