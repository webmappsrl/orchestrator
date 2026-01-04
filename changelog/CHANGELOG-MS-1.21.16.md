# CHANGELOG MS-1.21.16

**Release Date:** 04/01/2026  
**Version:** MS-1.21.16

## 🐛 Bug Fixes

### Ticket Archiviati (Archived Stories)
- **Errore al caricamento della pagina archived-stories** - Risolto errore che impediva il caricamento della pagina dei ticket archiviati causato da import mancante del campo Stack
  - Aggiunto import mancante di `Laravel\Nova\Fields\Stack`
  - La pagina ora carica correttamente senza errori

- **Errore 403 durante la replica di ticket archiviati** - Risolto errore 403 che impediva la replica di ticket dalla pagina archived-stories
  - Modificato `authorizedToCreate` per permettere la creazione durante la replica
  - Aggiunto controllo per rilevare quando si sta replicando tramite parametro `fromResourceId` o pattern URL `/replicate`
  - La replica ora funziona correttamente come in customer-stories

### Azioni e Funzionalità
- **Rimossa azione DuplicateStory da archived-stories** - Rimossa l'azione DuplicateStory dalla pagina archived-stories per utilizzare solo il pulsante Replicate standard di Nova
  - Eliminato file `app/Nova/Actions/DuplicateStory.php`
  - Rimossa azione dalle actions di ArchivedStories
  - La replica viene ora gestita esclusivamente tramite il pulsante Replicate standard

## 🔧 Improvements

### Replica Ticket Archiviati
- **Implementata logica replicate da customer-stories** - La funzione replicate in archived-stories ora si comporta come quella standard usata in customer-stories
  - Aggiunto metodo `authorizedToReplicate()` per permettere la replica
  - La replica utilizza la logica base della classe Story che gestisce automaticamente:
    - Aggiunta del suffisso "(COPY)" al titolo
    - Visualizzazione del campo "Replicated Tags" durante la replica
    - Copia automatica dei tag dal ticket originale

## 📋 Technical Details

### File Modificati
- `app/Nova/ArchivedStories.php` - Aggiunto import Stack, implementata logica replicate, modificato authorizedToCreate per permettere creazione durante replica
- `app/Nova/Actions/DuplicateStory.php` - File eliminato (non più necessario)

### Database
- Nessuna migrazione richiesta

