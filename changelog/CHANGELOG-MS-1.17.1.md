# CHANGELOG MS-1.17.1

**Release Date:** 29/10/2025  
**Version:** MS-1.17.1

## 🚀 New Features

### Sistema di Configurazione Task Schedulati
- **Configurazione basata su file** `config/orchestrator.php` per gestire tutti i task schedulati
- **Variabili di ambiente per abilitazione/disabilitazione** di ogni task tramite `.env`
- **Default sicuro**: tutti i task sono disabilitati di default (`false`)
- **Controllo granulare** su ogni task schedulato dell'applicazione

### Task Schedulati Configurabili
- **`ENABLE_PROCESS_INBOUND_EMAILS`** - Processa email in arrivo ogni 5 minuti (crea ticket)
- **`ENABLE_STORY_PROGRESS_TO_TODO`** - Trasferisce le story da progress a todo alle 18:00
- **`ENABLE_STORY_SCRUM_TO_DONE`** - Processa le story scrum alle 16:00
- **`ENABLE_SYNC_STORIES_CALENDAR`** - Sincronizza con Google Calendar alle 07:45
- **`ENABLE_STORY_AUTO_UPDATE_STATUS`** - Auto-aggiorna status story alle 07:45

## 🔧 Improvements

### Configurazione Email
- **Configurazione aggiornata** per utilizzare Mailpit al posto di Mailhog
- **Variabile `MAIL_HOST`** ora puntata su `mailpit` invece di `mailhog`
- **Dashboard Mailpit** disponibile su http://localhost:8025 per visualizzare le email catturate
- **Test email** più semplice tramite interfaccia web

### Miglioramenti Scheduler
- **Kernel.php aggiornato** per leggere configurazioni dinamiche
- **Condizioni di abilitazione** per ogni task basate su variabili di ambiente
- **Logging migliorato** con informazioni di avvio e fine di ogni task
- **Documentazione completa** nel README.md

## 📋 Technical Details

### File Creati
- `config/orchestrator.php` - Nuovo file di configurazione per task schedulati
- `changelog/CHANGELOG-MS-1.17.1.md` - Changelog della release
- `changelog/email/EMAIL-RELEASE-MS-1.17.1.md` - Email per sviluppatori

### File Modificati
- `app/Console/Kernel.php` - Aggiunto controllo condizionale per ogni task
- `README.md` - Sezione "Scheduled Tasks Configuration" aggiunta
- `.env-example` - Variabili `ENABLE_*` aggiunte
- `config/mail.php` - Configurazione Mailpit (poi rimossa, uso diretto tramite `.env`)

### Database
- **Nessuna migrazione** richiesta
- **Strutture esistenti** utilizzate

### Dependencies
- **Nessuna dipendenza** aggiunta
- **Mailpit** già presente in `docker-compose.yml`

## 🎯 User Impact

### Per Sviluppatori
- ✅ **Controllo totale** su quali task eseguire
- ✅ **Configurazione semplice** tramite file `.env`
- ✅ **Default sicuro** (tutti i task disabilitati)
- ✅ **Documentazione completa** nel README
- ✅ **Dashboard Mailpit** per debug email

### Per Amministratori
- ✅ **Abilitazione selettiva** dei task in produzione
- ✅ **Configurazione centralizzata** nel file `.env`
- ✅ **Monitoraggio** tramite dashboard Mailpit
- ✅ **Sicurezza** - nessun task attivo senza configurazione esplicita

## 🔄 Migration Notes

### Migrazione da Versione Precedente
Questa release modifica il comportamento dello scheduler. **IMPORTANTE**: i task schedulati ora richiedono configurazione esplicita nel file `.env`.

### Configurazione Richiesta

Per abilitare i task schedulati, aggiungere al file `.env` le variabili corrispondenti:

```bash
# Abilitare processamento email in arrivo (ogni 5 minuti)
ENABLE_PROCESS_INBOUND_EMAILS=true

# Abilitare story progress to todo (18:00)
ENABLE_STORY_PROGRESS_TO_TODO=true

# Abilitare story scrum to done (16:00)
ENABLE_STORY_SCRUM_TO_DONE=true

# Abilitare sync stories calendar (07:45)
ENABLE_SYNC_STORIES_CALENDAR=true

# Abilitare story auto update status (07:45)
ENABLE_STORY_AUTO_UPDATE_STATUS=true
```

### Dopo la Configurazione
1. Eseguire `php artisan config:cache` per ricaricare la configurazione
2. Verificare i task con `php artisan schedule:list`
3. Attendere la prossima esecuzione dello scheduler o eseguire manualmente `php artisan schedule:run`

### Cambiamento Mail Configuration
- **`MAIL_HOST`** cambiato da `mailhog` a `mailpit`
- **Dashboard** disponibile su http://localhost:8025
- **Nessuna interruzione** nel servizio email

## 📚 Documentation

### README Aggiornato
- **Sezione completa** "Scheduled Tasks Configuration" aggiunta
- **Tabella delle variabili** con descrizioni
- **Esempi di configurazione** con comandi pratici
- **Istruzioni per esecuzione manuale** dei task
- **Configurazione cron job** documentata

### Variabili Disponibili
| Variabile | Task | Schedule | Default |
|-----------|------|----------|---------|
| `ENABLE_STORY_PROGRESS_TO_TODO` | Story progress to todo | Daily at 18:00 | `false` |
| `ENABLE_STORY_SCRUM_TO_DONE` | Story scrum to done | Daily at 16:00 | `false` |
| `ENABLE_SYNC_STORIES_CALENDAR` | Sync stories calendar | Daily at 07:45 | `false` |
| `ENABLE_STORY_AUTO_UPDATE_STATUS` | Story auto update status | Daily at 07:45 | `false` |
| `ENABLE_PROCESS_INBOUND_EMAILS` | Process inbound emails | Every 5 minutes | `false` |

## 🚀 Next Steps

### Possibili Miglioramenti Futuri
- **Notifiche email** configurabili per ogni task
- **Dashboard** dedicata per monitoraggio task schedulati
- **Report** di esecuzione dei task
- **Integrazione** con sistemi di monitoring esterni

---

**Note:** Questa release introduce un sistema di controllo granulare sui task schedulati, permettendo una configurazione flessibile e sicura dell'ambiente di produzione.

