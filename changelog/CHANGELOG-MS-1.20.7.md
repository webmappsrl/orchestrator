# CHANGELOG MS-1.20.7

**Release Date:** 23/11/2025  
**Version:** MS-1.20.7

## 🔧 Improvements

### Dashboard Kanban-2
- **Filtro tabella "Cosa ho fatto ieri?"** - La tabella mostra ora solo i ticket con stato "Released" o "Done", rimuovendo tutti gli altri stati che sono già presenti nelle altre tabelle della dashboard

- **Nuova tabella sperimentale "Che cosa ho fatto ieri (sperimentale)?"** - Aggiunta nuova tabella nella dashboard Kanban-2 che mostra:
  - Ticket con `released_at` o `done_at` degli ultimi 2 giorni
  - A partire dall'ultimo giorno con almeno un ticket (escluso il giorno corrente)
  - Stesse colonne della tabella "Cosa ho fatto ieri" (ID, Status, Tags, Creator, Ticket, Tempo Speso, Data)
  - Calcolo del tempo totale dai log (`UsersStoriesLog`) per ogni ticket
  - Visualizzazione della data da `released_at` o `done_at` (preferenza a `released_at`)

### Interfaccia Nova
- **Ricerca migliorata per Activity Reports** - Abilitata la ricerca sui nomi di customer e organizzazione nella vista index degli activity reports
- **Ricerca utenti in Organization** - Abilitata la ricerca degli utenti quando si attaccano utenti a un'organizzazione

### Interfaccia Ticket
- **Compattazione campi data** - I campi di data separati sono stati compattati in un unico campo "History" nella vista index di tutti i ticket
- **Pulizia vista detail** - Rimossi i campi "Creato il", "Aggiornato il", "Rilasciato il", "Completato il" e "Log del ticket" dal pannello "Ticket history and activities" nella vista detail

## 📋 Technical Details

### File Modificati
- `app/Nova/Dashboards/Kanban2.php` - Aggiunto filtro per status Released/Done in getRecentActivities(), aggiunti metodi getExperimentalActivities() e experimentalActivitiesCard()
- `app/Nova/ActivityReport.php` - Aggiunto metodo searchableColumns() con SearchableRelation per customer e organization
- `app/Nova/Organization.php` - Aggiunto metodo searchable() al campo BelongsToMany Users
- `app/Nova/Story.php` - Rimossi campi data individuali e Story Log da detail view
- `app/Traits/fieldTrait.php` - Aggiunto metodo historyField() per mostrare tutte le date in un unico campo
- `app/Nova/StoryShowedByCustomer.php` - Aggiornato per usare historyField()
- `app/Nova/ArchivedStoryShowedByCustomer.php` - Aggiornato per usare historyField()
- `app/Nova/ArchivedStories.php` - Aggiornato per usare historyField()

### File Creati
- `resources/views/story-viewer-experimental-activities.blade.php` - Nuova view per la tabella sperimentale

### Database
- **Nessuna migrazione** richiesta

## 📝 Notes

- **Miglioramento UX** - Le dashboard e le interfacce sono ora più pulite e organizzate
- **Compatibilità** - Nessun impatto sul funzionamento del sistema, solo miglioramenti visuali e funzionali
- **Tabella sperimentale** - La nuova tabella "Che cosa ho fatto ieri (sperimentale)?" è in fase di test e può essere modificata in base al feedback

