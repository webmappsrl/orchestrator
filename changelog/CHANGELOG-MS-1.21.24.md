# CHANGELOG MS-1.21.24

**Release Date:** 05/01/2026  
**Version:** MS-1.21.24

## 🔧 Improvements

### Autorizzazione Ticket
- Implementazione delle regole di autorizzazione per Story policy
- Admin/Manager/Developer possono visualizzare, creare e replicare tutti i ticket
- Admin/Manager possono modificare ed eliminare tutti i ticket
- Developer può modificare solo i ticket assegnati (`user_id`) o di cui è tester (`tester_id`)
- Implementazione di `authorizedToUpdate` nella risorsa Story per verificare i permessi di modifica
- Implementazione di `authorizedToSee` e `authorizedToRun` nell'azione ChangeStatus usando `Gate::authorize` per delegare alla policy
- Le azioni Nova ora rispettano le regole di autorizzazione basate sui ruoli e sull'assegnazione dei ticket

## 📋 Technical Details

### File Modificati
- `app/Policies/StoryPolicy.php` - Implementazione completa delle regole di autorizzazione per viewAny, view, create, update, delete, replicate
- `app/Nova/Story.php` - Aggiunto metodo `authorizedToUpdate` per verificare i permessi di modifica
- `app/Nova/Actions/ChangeStatus.php` - Aggiunto `authorizedToSee` e refactoring di `authorizedToRun` per usare `Gate::authorize('update', $model)`

### Database
- Nessuna modifica al database

