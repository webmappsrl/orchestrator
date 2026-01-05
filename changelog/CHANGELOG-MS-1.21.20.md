# CHANGELOG MS-1.21.20

**Release Date:** 05/01/2026  
**Version:** MS-1.21.20

## 🚀 New Features

### Automated Scrum Archive
- **Nuovo comando `orchestrator:scrum-archive`** - Comando artisan per archiviare automaticamente tutti i ticket Scrum creati prima di oggi, impostando lo status a DONE, aggiornando il creator e associando un tag specifico configurato tramite variabili d'ambiente
- **Task schedulato automatico** - Il comando può essere eseguito automaticamente ogni giorno alle 5:00 del mattino tramite configurazione in `.env` (variabile `ENABLE_SCRUM_ARCHIVE`)

## 🔧 Improvements

### Configurazione
- **Variabili di ambiente aggiuntive** - Aggiunte nuove variabili di configurazione per il comando di archiviazione Scrum:
  - `SCRUM_ARCHIVE_CREATOR_ID` - ID utente da impostare come creator quando si archiviano i ticket Scrum
  - `SCRUM_ARCHIVE_TAG_ID` - ID tag da associare quando si archiviano i ticket Scrum
  - `ENABLE_SCRUM_ARCHIVE` - Flag per abilitare/disabilitare il task schedulato (default: false)

## 📋 Technical Details

### File Creati
- `app/Console/Commands/ScrumArchiveCommand.php` - Nuovo comando artisan per archiviare ticket Scrum

### File Modificati
- `app/Console/Kernel.php` - Aggiunto task schedulato per il comando `orchestrator:scrum-archive` alle 5:00 (timezone Europe/Rome)
- `config/orchestrator.php` - Aggiunta configurazione `scrum_archive` nella sezione `tasks`
- `.env-example` - Aggiunte variabili di esempio per la configurazione del comando di archiviazione

### Database
- Nessuna modifica al database

