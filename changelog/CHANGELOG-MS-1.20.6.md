# CHANGELOG MS-1.20.6

**Release Date:** 23/11/2025  
**Version:** MS-1.20.6

## 🔧 Improvements

### Activity Reports
- **Ricerca migliorata per Activity Reports** - Abilitata la ricerca sui nomi di customer e organizzazione nella vista index degli activity reports. Ora è possibile cercare activity reports per:
  - ID dell'activity report
  - Nome del customer (tramite relazione)
  - Nome dell'organizzazione (tramite relazione)
  - La ricerca funziona sui campi mostrati nella colonna "Owner" della vista index

## 📋 Technical Details

### File Modificati
- `app/Nova/ActivityReport.php` - Aggiunto metodo `searchableColumns()` con `SearchableRelation` per customer e organization

### Database
- **Nessuna migrazione** richiesta

## 📝 Notes

- **Miglioramento UX** - La ricerca degli activity reports è ora più efficace e permette di trovare rapidamente i report per customer o organizzazione
- **Compatibilità** - Nessun impatto sul funzionamento del sistema, solo miglioramento della ricerca
- **Ricerca relazioni** - Utilizza `SearchableRelation` di Laravel Nova per cercare nelle relazioni BelongsTo

