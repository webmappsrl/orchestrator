# CHANGELOG MS-1.21.13

**Release Date:** 04/01/2026  
**Version:** MS-1.21.13

## 🔧 Improvements

### Interfaccia Ticket
- **Pannello storico e attività del ticket** - Aggiunto campo "Ticket Changes" nel pannello "Ticket history and activities" che mostra tutti i cambiamenti del ticket con timestamp e descrizioni
  - Il campo legge i dati dalla tabella `story_logs` invece che dal campo `history_log`
  - Visualizzazione formattata con tabella contenente data/ora, utente e descrizione dei cambiamenti
  - Ordinamento per data/ora decrescente basato sul campo `updated_at` dal JSON changes
  - Supporto per mostrare nomi utente invece di ID per i campi `user_id`, `tester_id`, `creator_id`
  - Visualizzazione di tutti i log senza limiti

### Campo Creatore
- **Campo Creatore modificabile** - Il campo "Creatore" è ora modificabile e searchable nella pagina di creazione ticket
  - Assegnazione automatica dell'utente loggato come creatore solo se il campo è vuoto
  - Possibilità di selezionare un creatore diverso durante la creazione del ticket

### Miglioramenti Logging
- **Miglioramenti StoryObserver** - Migliorata la gestione dei log delle modifiche
  - Filtro dei valori null nei cambiamenti registrati nei log
  - Inclusione sempre del campo `updated_at` nel log di creazione
  - Assicurato che lo status nel log di creazione non sia mai null (default a "new")

## 📋 Technical Details

### File Modificati
- `app/Models/Story.php` - Aggiunto `history_log` al fillable
- `app/Nova/Story.php` - Aggiunto campo "Ticket Changes" al pannello storico e attività
- `app/Observers/StoryObserver.php` - Migliorata gestione log con filtro null e sempre updated_at
- `app/Traits/fieldTrait.php` - Creato metodo `historyLogField()` per visualizzare i log dalla tabella story_logs, reso Creator modificabile e searchable

### Database
- **Nessuna migrazione** richiesta

## 📝 Notes

- **Miglioramento UX** - Gli utenti possono ora vedere tutti i cambiamenti del ticket in modo organizzato e cronologico
- **Compatibilità** - Nessun impatto sul funzionamento esistente, solo aggiunte di funzionalità
- **Performance** - Pre-caricamento degli utenti per migliorare le performance nella visualizzazione dei log

