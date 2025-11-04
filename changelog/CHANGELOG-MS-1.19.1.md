# CHANGELOG MS-1.19.1

**Release Date:** 04/11/2025  
**Version:** MS-1.19.1

## 🐛 Bug Fixes

### Fix Autenticazione Utente
- **Risolto errore "Call to a member function hasRole() on null"** - Aggiunti controlli null in tutti i punti dove viene chiamato `$request->user()->hasRole()` senza verificare se l'utente è autenticato
- **Corretti 16 file** che potevano causare errore quando si accedeva a Nova senza autenticazione o durante il caricamento iniziale del menu
- **File corretti**:
  - `app/Nova/Organization.php` - Tutti i metodi di autorizzazione
  - `app/Nova/FundraisingProject.php` - Metodo `authorizedToUpdate`
  - `app/Nova/Story.php` - Tutti i `canSee` e metodi che usano `hasRole()`
  - `app/Nova/User.php` - Tutti i `canSee` e actions
  - `app/Nova/Actions/ChangeStoryCreator.php` - `authorizedToSee` e `authorizedToRun`
  - `app/Nova/Lenses/StoriesByQuarter.php` - Metodo `infoField`
  - `app/Providers/NovaServiceProvider.php` - Menu items e `initialPath`
  - Tutte le dashboard Activity (8 file) - Tutti i `canSee` con controllo null

## 📋 Technical Details

### File Modificati
- `app/Nova/Organization.php` - Aggiunti controlli null in `availableForNavigation`, `authorizedToCreate`, `authorizedToUpdate`, `authorizedToDelete`
- `app/Nova/FundraisingProject.php` - Aggiunto controllo null in `authorizedToUpdate`
- `app/Nova/Story.php` - Aggiunti controlli null in `indexQuery`, `canSee` dei campi, `cards`, `actions`, `navigationLinks`
- `app/Nova/User.php` - Aggiunti controlli null in `canSee` dei campi e actions
- `app/Nova/Actions/ChangeStoryCreator.php` - Aggiunti controlli null in `authorizedToSee` e `authorizedToRun`
- `app/Nova/Lenses/StoriesByQuarter.php` - Aggiunto controllo null in `infoField`
- `app/Providers/NovaServiceProvider.php` - Aggiunti controlli null in menu items e `initialPath`
- `app/Nova/Dashboards/Activity.php` - Aggiunti controlli null in `canSee`
- `app/Nova/Dashboards/ActivityUser.php` - Aggiunti controlli null in `canSee`
- `app/Nova/Dashboards/ActivityTags.php` - Aggiunti controlli null in `canSee`
- `app/Nova/Dashboards/ActivityCustomer.php` - Aggiunti controlli null in `canSee`
- `app/Nova/Dashboards/ActivityOrganizations.php` - Aggiunti controlli null in `canSee`
- `app/Nova/Dashboards/ActivityTagsDetails.php` - Aggiunti controlli null in `canSee`
- `app/Nova/Dashboards/ActivityCustomerDetails.php` - Aggiunti controlli null in `canSee`
- `app/Nova/Dashboards/ActivityOrganizationsDetails.php` - Aggiunti controlli null in `canSee`
- `app/Nova/Dashboards/Kanban2.php` - Aggiunti controlli null in `canSee`

### Database
- **Nessuna migrazione** richiesta

## 📝 Notes

- **Fix critico** - Questo fix risolve un errore che impediva l'accesso a Nova quando l'utente non era autenticato o durante il caricamento iniziale
- **Impatto** - Nessun impatto sulle funzionalità esistenti, solo correzione di un bug di autenticazione
- **Compatibilità** - Nessuna breaking change, completamente retrocompatibile

