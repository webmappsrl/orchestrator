# System - Panoramica

Modulo dedicato agli aspetti tecnici e infrastrutturali del sistema Orchestra.

## Sezioni

- **Documentazione**: Documentazione tecnica del sistema
- **Comandi Artisan**: Tutti i comandi disponibili nell'applicazione
- **Inizializzazione**: Setup e configurazione iniziale del database
- **Troubleshooting**: Guide per risolvere problemi comuni

## Documentazione Disponibile

### [Comandi Artisan](artisan-commands.md)
Documentazione completa di tutti i comandi Artisan disponibili nell'applicazione, inclusi:
- Story Commands
- Sync Commands
- Orchestrator Commands
- Database Commands
- User Commands
- Tag Commands

### [Inizializzazione Database](initialization.md)
Guida per inizializzare il database con dati di produzione, inclusi:
- Utenti admin, developer e customer
- Tag predefiniti
- Configurazione e credenziali di default

### [PDF Export Troubleshooting](pdf-export-troubleshooting.md)
Guida per diagnosticare e risolvere problemi con l'export PDF della documentazione:
- Verifica file e route
- Pulizia cache
- Configurazione DomPDF
- Permessi directory
- Problemi comuni e soluzioni

### [Redis Fix](redis-fix.md)
Documentazione sulla risoluzione dell'errore 500 causato da Redis configurato come replica read-only:
- Problema identificato
- Soluzione implementata
- Prevenzione futura
- Comandi utili per debugging

## Convenzioni

- Tutti i comandi Artisan devono essere eseguiti tramite Docker: `docker-compose exec phpfpm php artisan <command>`
- Le configurazioni sono in `config/orchestrator.php`
- I log sono disponibili in `storage/logs/laravel.log`

