# 🚀 Release MS-1.17.1 - Sistema Task Schedulati Configurabile

**Ciao Team!** 👋

Annunciamo la **Release MS-1.17.1** - una versione che introduce un **sistema di configurazione flessibile** per i task schedulati e migliora la gestione delle email tramite Mailpit.

---

## 🎯 **COSA C'È DI NUOVO**

### **⚙️ Sistema Task Schedulati Configurabile**
- **Configurazione basata su file** con nuove variabili di ambiente
- **Controllo granulare** su ogni task schedulato
- **Default sicuro** - tutti i task disabilitati di default
- **Abilitazione per produzione** tramite file `.env`

### **📧 Miglioramenti Sistema Email**
- **Mailpit** al posto di Mailhog per gestione email
- **Dashboard web** disponibile su http://localhost:8025
- **Visualizzazione semplice** delle email catturate
- **Debug email** più efficiente

### **📋 Task Schedulati Disponibili**

| Task | Schedule | Variabile |
|------|----------|-----------|
| 📧 Processa email in arrivo | Ogni 5 minuti | `ENABLE_PROCESS_INBOUND_EMAILS` |
| 📊 Story progress to todo | 18:00 | `ENABLE_STORY_PROGRESS_TO_TODO` |
| 🎯 Story scrum to done | 16:00 | `ENABLE_STORY_SCRUM_TO_DONE` |
| 📅 Sync stories calendar | 07:45 | `ENABLE_SYNC_STORIES_CALENDAR` |
| 🔄 Auto update status | 07:45 | `ENABLE_STORY_AUTO_UPDATE_STATUS` |

---

## 🔧 **CONFIGURAZIONE RICHIESTA**

### ⚠️ **IMPORTANTE: Configurazione Necessaria**

Per abilitare i task schedulati in produzione, è necessario aggiungere al file `.env` le variabili corrispondenti:

```bash
# Esempio: abilitare il processamento delle email
ENABLE_PROCESS_INBOUND_EMAILS=true

# Esempio: abilitare tutti i task
ENABLE_STORY_PROGRESS_TO_TODO=true
ENABLE_STORY_SCRUM_TO_DONE=true
ENABLE_SYNC_STORIES_CALENDAR=true
ENABLE_STORY_AUTO_UPDATE_STATUS=true
ENABLE_PROCESS_INBOUND_EMAILS=true
```

### 📝 **Dopo la Configurazione**
1. Eseguire: `php artisan config:cache`
2. Verificare: `php artisan schedule:list`
3. I task abilitati appariranno nell'elenco

---

## 🎛️ **MIGLIORAMENTI PER SVILUPPATORI**

### **Configurazione Centralizzata**
- File `config/orchestrator.php` per gestione centralizzata
- Variabili di ambiente per controllo granulare
- Documentazione completa nel README.md

### **Dashboard Mailpit**
- Accesso web: http://localhost:8025
- Visualizzazione email in tempo reale
- Cattura tutte le email inviate dall'applicazione
- Log completo delle email

### **Kernel.php Aggiornato**
- Lettura configurazioni dinamiche
- Condizioni di abilitazione per ogni task
- Logging migliorato di avvio e fine task

---

## 📋 **DETTAGLI RILASCIO**

- **Versione:** MS-1.17.1
- **Data:** 29 Ottobre 2025
- **Branch:** montagna-servizi
- **Tag:** MS-1.17.1

---

## 🚀 **PROSSIMI PASSI**

1. **Aggiornare** il file `.env` con le variabili necessarie
2. **Abilitare** i task desiderati impostando `true`
3. **Ricarica** la configurazione: `php artisan config:cache`
4. **Verificare** i task: `php artisan schedule:list`
5. **Monitorare** le email tramite dashboard Mailpit

---

## 📖 **DOCUMENTAZIONE**

### Per Maggiori Dettagli
- **CHANGELOG completo:** `changelog/CHANGELOG-MS-1.17.1.md`
- **README sezione scheduler:** `/README.md` - "Scheduled Tasks Configuration"
- **Esempi di configurazione** disponibili nella documentazione

### Comandi Utili
```bash
# Lista task schedulati
php artisan schedule:list

# Esegui tutti i task in attesa
php artisan schedule:run

# Processa email manualmente
php artisan orchestrator:process-inbound-emails
```

---

## ⚠️ **NOTA IMPORTANTE**

**Tutti i task schedulati sono ora DISABILITATI di default.** Questo garantisce:
- ✅ Sicurezza in produzione
- ✅ Controllo esplicito delle funzionalità attive
- ✅ Prevenzione di esecuzioni indesiderate

Per attivarli, è necessario configurare le variabili nel file `.env`.

---

## 🎉 **GRAZIE!**

Grazie a tutto il team per il continuo supporto e feedback. Questa release migliora significativamente la gestione e il controllo dei task schedulati.

**Buon lavoro a tutti!** 🙌

---

**Team Orchestrator**  
*Webmapp S.r.l.*

*Per dettagli tecnici completi, consultare il CHANGELOG-MS-1.17.1.md*

