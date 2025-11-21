# CHANGELOG MS-1.19.5

**Release Date:** 21/11/2025  
**Version:** MS-1.19.5

## 🔧 Improvements

### Dashboard Kanban2 - Link ai Ticket
- **Link ai ticket standardizzati** - Tutti i link ai ticket nella dashboard Kanban2 puntano ora all'edit dei ticket dei clienti (`/resources/customer-stories/{id}`) invece di `/resources/assigned-to-me-stories/{id}`. Questo garantisce una navigazione coerente e un accesso diretto all'interfaccia di modifica dei ticket per tutti gli utenti

## 📋 Technical Details

### File Modificati
- `resources/views/story-viewer-kanban2.blade.php` - Modificato `$urlNova` per puntare a `/resources/customer-stories` invece di `/resources/assigned-to-me-stories`
- `resources/views/story-viewer-recent-activities.blade.php` - Modificato `$urlNova` per puntare a `/resources/customer-stories` invece di `/resources/assigned-to-me-stories`

### Database
- **Nessuna migrazione** richiesta

## 📝 Notes

- **Miglioramento UX** - Questa modifica garantisce che tutti i link nella dashboard Kanban2 puntino alla stessa interfaccia di modifica dei ticket, migliorando la coerenza dell'interfaccia utente
- **Interfaccia unificata** - Tutti gli utenti accedono ora ai ticket tramite l'interfaccia customer-stories, indipendentemente dalla tabella della dashboard Kanban2 selezionata
- **Compatibilità** - Nessuna breaking change, completamente retrocompatibile

