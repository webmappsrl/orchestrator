# CHANGELOG MS-1.19.3

**Release Date:** 21/11/2025  
**Version:** MS-1.19.3

## 🔧 Improvements

### Gestione Tag nei Form Customer Stories
- **Aggiunto bottone di creazione inline per tag** - Gli utenti con ruolo admin o manager possono ora creare nuovi tag direttamente dalla select nel form di creazione/modifica dei ticket customer, senza dover navigare all'interfaccia dedicata dei tag
- **Controllo permessi implementato** - Il bottone di creazione inline viene mostrato solo agli utenti autorizzati (admin/manager), garantendo che gli altri utenti vedano solo la select per selezionare tag esistenti

## 📋 Technical Details

### File Modificati
- `app/Nova/Tag.php` - Aggiunto metodo `authorizedToCreate()` per limitare la creazione di tag solo ad admin/manager
- `app/Traits/fieldTrait.php` - Modificato metodo `tagsField()` per aggiungere `showCreateRelationButton()` con controllo condizionale che mostra il bottone solo agli utenti autorizzati

### Database
- **Nessuna migrazione** richiesta

## 📝 Notes

- **Miglioramento UX** - Questo miglioramento semplifica il workflow per admin/manager, permettendo loro di creare tag on-the-fly durante la creazione/modifica dei ticket
- **Sicurezza** - La creazione di tag rimane limitata solo agli utenti con ruoli appropriati (admin/manager)
- **Compatibilità** - Nessuna breaking change, completamente retrocompatibile

