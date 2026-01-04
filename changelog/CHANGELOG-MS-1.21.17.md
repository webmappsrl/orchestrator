# CHANGELOG MS-1.21.17

**Release Date:** 04/01/2026  
**Version:** MS-1.21.17

## 🔧 Improvements

### Change Status Action
- L'action "Change Status" è ora disponibile solo nella detail view dei ticket, non più nella index
- Quando un ticket è in stato PROBLEM o WAITING, l'action mostra solo lo stato precedente come possibile uscita
- Lo stato precedente viene recuperato automaticamente dalla tabella `story_logs`
- Migliorata l'esperienza utente limitando le opzioni disponibili solo agli stati validi

## 📋 Technical Details

### File Modificati
- `app/Nova/Actions/ChangeStatus.php` - Aggiunto metodo `getPreviousStatusFromLogs()` per recuperare lo stato precedente da story_logs quando il ticket è in PROBLEM/WAITING. Aggiunto metodo helper `getResourceIdsFromRequest()` per gestire correttamente le bulk actions
- `app/Nova/Story.php` - Aggiunto `onlyOnDetail()` e `canSee()` su ChangeStatus action. Aggiunto metodo `actionsForIndex()` che ritorna array vuoto
- `app/Nova/ArchivedStories.php` - Aggiunto `onlyOnDetail()` e `canSee()` su ChangeStatus. Aggiunto `actionsForIndex()`
- `app/Nova/ArchivedStoryShowedByCustomer.php` - Aggiunto `onlyOnDetail()` e `canSee()` su ChangeStatus. Aggiunto `actionsForIndex()`
- `app/Nova/InProgressStory.php` - Aggiunto `onlyOnDetail()` e `canSee()` su ChangeStatus

### Database
- Nessuna modifica al database

