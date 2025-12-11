# CHANGELOG MS-1.21.3

**Release Date:** 12/12/2025  
**Version:** MS-1.21.3

## 🔧 Improvements

### Interfaccia Ticket NOVA
- **Riorganizzazione colonne index ticket** - Rimosse colonne separate ID, STATO e ASSIGNED/HOURS dalla vista index. Aggiunta nuova colonna MAIN INFO che contiene ID (cliccabile per aprire il dettaglio), STATO (con grafica attuale) e USER (utente assegnato al ticket)
- **Effective hours in History** - Aggiunte le Effective hours come ultima riga nella colonna History di tutte le risorse ticket
- **Ragione dell'attesa in waiting-stories** - Aggiunta colonna "Ragione dell'attesa" tra Info e History nella vista index di WaitingStory. Il testo è limitato a 40 caratteri per riga con interruzioni automatiche per migliorare la leggibilità
- **Ragione dei problemi in problem-stories** - Aggiunta colonna "Ragione dei problemi" tra Info e History nella vista index di ProblemStory. Il testo è limitato a 40 caratteri per riga con interruzioni automatiche per migliorare la leggibilità

### Menu Navigation
- **Spostamento voce menu Nuovi** - Spostata la voce "Nuovi" da AGILE>TICKET>NUOVI a AGILE>SCRUM come prima voce del menu SCRUM

## 📋 Technical Details

### File Modificati
- `app/Nova/Story.php` - Riorganizzazione colonne index con nuova colonna MAIN INFO
- `app/Nova/ArchivedStories.php` - Riorganizzazione colonne index con nuova colonna MAIN INFO
- `app/Nova/ArchivedStoryShowedByCustomer.php` - Riorganizzazione colonne index con nuova colonna MAIN INFO
- `app/Nova/StoryShowedByCustomer.php` - Riorganizzazione colonne index con nuova colonna MAIN INFO
- `app/Nova/WaitingStory.php` - Aggiunta colonna "Ragione dell'attesa" nell'index
- `app/Nova/ProblemStory.php` - Aggiunta colonna "Ragione dei problemi" nell'index
- `app/Traits/fieldTrait.php` - Aggiunto metodo clickableIdField(), assignedUserTextField() e aggiornato historyField() per includere Effective hours
- `app/Providers/NovaServiceProvider.php` - Spostata voce menu "Nuovi" in SCRUM

### Database
- **Nessuna migrazione** richiesta

## 📝 Notes

- **Compatibilità** - Nessun impatto sul funzionamento esistente, solo miglioramenti dell'interfaccia
- **Visualizzazione** - Le modifiche interessano solo la vista index delle risorse ticket, le viste detail e edit rimangono invariate

