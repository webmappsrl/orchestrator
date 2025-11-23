# CHANGELOG MS-1.20.4

**Release Date:** 23/11/2025  
**Version:** MS-1.20.4

## 🔧 Improvements

### Interfaccia Ticket
- **Compattazione campi data nella vista index** - I campi di data separati (Creato il, Aggiornato il, Rilasciato il, Completato il) sono stati compattati in un unico campo "History" nella vista index di tutti i ticket:
  - Campo "History" mostra tutte le date in un unico campo con una data per riga
  - Formato: "Creato il: [data]", "Aggiornato il: [data]", "Rilasciato il: [data]", "Concluso il: [data]"
  - Applicato a tutte le risorse ticket: Story, CustomerStory, StoryShowedByCustomer, ArchivedStoryShowedByCustomer, ArchivedStories

- **Pulizia vista detail ticket** - Rimossi i seguenti campi dal pannello "Ticket history and activities" nella vista detail:
  - Creato il (Created At)
  - Aggiornato il (Updated At)
  - Rilasciato il (Released At)
  - Completato il (Done At)
  - Log del ticket (Story Log)
  - Il pannello ora contiene solo il campo "User Activity"

## 📋 Technical Details

### File Modificati
- `app/Traits/fieldTrait.php` - Aggiunto metodo `historyField()` per mostrare tutte le date in un unico campo
- `app/Nova/Story.php` - Sostituito `createdAtField()` con `historyField()` in index, rimossi campi data e Story Log da detail
- `app/Nova/StoryShowedByCustomer.php` - Rimossi `createdAtField()` e `updatedAtField()`, aggiunto `historyField()`
- `app/Nova/ArchivedStoryShowedByCustomer.php` - Rimossi `createdAtField()` e `updatedAtField()`, aggiunto `historyField()`
- `app/Nova/ArchivedStories.php` - Rimossi tutti i campi di data individuali, aggiunto `historyField()`

### Database
- **Nessuna migrazione** richiesta

## 📝 Notes

- **Miglioramento UX** - La vista index dei ticket è ora più compatta e leggibile con tutte le informazioni di data in un unico campo
- **Compatibilità** - Nessun impatto sul funzionamento del sistema, solo miglioramenti visuali
- **Vista Detail** - Le informazioni di data sono ancora disponibili tramite il campo "History" nella vista index

