# CHANGELOG MS-1.20.8

**Release Date:** 23/11/2025  
**Version:** MS-1.20.8

## 🔧 Improvements

### Story Logging
- **Log automatico alla creazione ticket** - Alla creazione di un ticket viene ora automaticamente inserito un elemento nella tabella `story_logs` con:
  - `user_id` preso da `story->user_id` se presente
  - Se `story->user_id` è null, viene usato l'utente loggato
  - Se anche l'utente loggato è null, non viene creato il log (perché `user_id` è required nella tabella)
  - `changes` contiene lo status iniziale del ticket
  - Implementato tramite `StoryObserver::created()` per garantire coerenza

## 📋 Technical Details

### File Modificati
- `app/Observers/StoryObserver.php` - Aggiunta creazione StoryLog nel metodo `created()`
- `tests/Unit/Services/ScrumStoryServiceTest.php` - Aggiornato per filtrare log per status 'done'
- `tests/Unit/Services/AutoUpdateStoryStatusServiceTest.php` - Aggiornato per filtrare log per status 'done'
- `tests/Unit/Services/StoryDateServiceTest.php` - Aggiornato per gestire il nuovo log di creazione

### File Creati
- `tests/Feature/StoryCreationLogTest.php` - Test feature per verificare la creazione del log alla creazione del ticket

### Database
- **Nessuna migrazione** richiesta

## 📝 Notes

- **Tracciabilità migliorata** - Ogni ticket ora ha un log di creazione che traccia chi ha creato il ticket e lo status iniziale
- **Compatibilità** - Nessun impatto sul funzionamento del sistema, solo miglioramento della tracciabilità
- **Test aggiornati** - Tutti i test sono stati aggiornati per gestire il nuovo comportamento automatico

