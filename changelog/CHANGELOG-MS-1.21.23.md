# CHANGELOG MS-1.21.23

**Release Date:** 05/01/2026  
**Version:** MS-1.21.23

## 🚀 New Features

### Gestione Manuale Ticket in Attesa
- **Nova Action per aggiornamento manuale**: Aggiunta action "Aggiorna Ticket in Attesa" nella risorsa `WaitingStory` per aggiornare manualmente i ticket selezionati
- **Ordinamento intelligente**: I ticket in attesa sono ora ordinati automaticamente per giorni di attesa (dal più vecchio al più recente) nella dashboard Nova
- **Visualizzazione giorni di attesa**: La colonna "Ragione dell'attesa" mostra anche il numero di giorni trascorsi in stato Waiting
- **Service riutilizzabile**: Estratto `AutoUpdateWaitingStoriesService` dal comando Artisan per permettere riutilizzo in action Nova e altri contesti
- **Test completi**: Aggiunta suite di test completa per il service (13 test, 47 asserzioni) per garantire affidabilità

### Miglioramenti Comando Auto-Update
- **Rifattorizzazione comando**: Il comando `orchestrator:autoupdate-waiting` ora utilizza il service centralizzato per maggiore manutenibilità
- **Logica migliorata**: Migliorata la logica di ripristino stati, con gestione corretta della transizione da `todo` a `progress` quando necessario

## 🐛 Bug Fixes

### Logica Transizione Stati
- **Correzione ripristino stati**: Corretta la logica di transizione stati nel comando `orchestrator:autoupdate-waiting`
- **Ripristino corretto da todo**: I ticket che erano in stato `todo` prima di entrare in `waiting` vengono ora correttamente ripristinati in `todo` invece di essere spostati in `progress`
- **Gestione stati progress/released/done**: I ticket che erano in `progress`, `released` o `done` vengono correttamente ripristinati in `todo` come previsto

## 📋 Technical Details

### File Creati
- `app/Nova/Actions/UpdateWaitingStoriesAction.php` - Nova action per aggiornamento manuale ticket in attesa
- `app/Services/AutoUpdateWaitingStoriesService.php` - Service centralizzato per gestione aggiornamento ticket in attesa
- `tests/Unit/Services/AutoUpdateWaitingStoriesServiceTest.php` - Test completi per il service (13 test, 47 asserzioni)

### File Modificati
- `app/Console/Commands/AutoUpdateWaitingStories.php` - Rifattorizzato per utilizzare il service centralizzato
- `app/Nova/WaitingStory.php` - Aggiunto ordinamento per giorni in attesa e visualizzazione giorni nella colonna "Ragione dell'attesa"

### Database
- Nessuna migrazione richiesta

