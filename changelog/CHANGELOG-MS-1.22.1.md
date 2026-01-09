# CHANGELOG MS-1.22.1

**Release Date:** 09/01/2026  
**Version:** MS-1.22.1

## 🔧 Improvements

### Documentazione
- **Riorganizzazione struttura documentazione** - Creata struttura modulare con cartelle dedicate per ogni modulo (system/, management/, fundraising/)
- **File overview per moduli** - Creati file overview.md per System e Management con panoramica e link ai file del modulo
- **Standardizzazione nomi file** - Tutti i file di documentazione rinominati in formato kebab-case per coerenza
- **Aggiornamento index.md** - Aggiornato file principale con nuovi percorsi e struttura modulare

## 📋 Technical Details

### File Modificati
- `docs/index.md` - Aggiornato con nuova struttura modulare e percorsi corretti
- `docs/system/overview.md` - Creato file panoramica modulo System
- `docs/system/artisan-commands.md` - Spostato da docs/ARTISAN_COMMANDS.md
- `docs/system/initialization.md` - Spostato da docs/INITIALIZATION.md
- `docs/system/pdf-export-troubleshooting.md` - Spostato da docs/PDF_EXPORT_TROUBLESHOOTING.md
- `docs/system/redis-fix.md` - Spostato da docs/REDIS_FIX_DOCUMENTATION.md
- `docs/management/overview.md` - Creato file panoramica modulo Management
- `docs/management/ticket-status-flow.md` - Spostato da docs/TICKET_STATUS_FLOW.md
- `docs/management/automatic-status-changes.md` - Spostato da docs/AUTOMATIC_STATUS_CHANGES.md
- `docs/fundraising/overview.md` - Spostato da docs/FUNDRAISING_SYSTEM_DOCUMENTATION.md

### Struttura Documentazione
La documentazione è ora organizzata in moduli:
- **System**: Aspetti tecnici e infrastrutturali (comandi Artisan, inizializzazione, troubleshooting)
- **Management**: Controllo e monitoraggio attività (flusso ticket, modifiche automatiche stato)
- **Fundraising**: Gestione opportunità e progetti di fundraising

