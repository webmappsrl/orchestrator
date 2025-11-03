# CHANGELOG MS-1.18.0

**Release Date:** 03/11/2025  
**Version:** MS-1.18.0  
**Branch:** montagna-servizi

---

## 🎯 **RELEASE HIGHLIGHTS**

Questa release MS-1.18.0 introduce una revisione completa dell'interfaccia utente della piattaforma Orchestrator, con nuove dashboard personalizzate, miglioramenti nell'organizzazione dei ticket e un sistema di tracciamento attività avanzato. Le modifiche migliorano significativamente l'esperienza utente per tutti i ruoli, con particolare focus sulla gestione del workflow agile.

---

## 🚀 **NUOVE FUNZIONALITÀ**

### **🎛️ Dashboard Kanban-2**
- **Nuova dashboard completa** per la visualizzazione dei ticket in modo organizzato
- **Quattro tabelle dedicate**:
  - In attesa di verifica (da testare)
  - Che problemi ho incontrato (in attesa)  
  - Cosa devo fare oggi (todo)
  - Cosa devo verificare (da testare)
- **Visualizzazione attività recenti** "Cosa ho fatto ieri?" per tracciare le ultime 2 giornate lavorative
- **Contatore ticket dinamico** in ogni tabella
- **Selettore utente** per Admin e Developer per visualizzare il lavoro di qualsiasi team member

### **📊 Sistema di Tracking Attività**
- **Nuova tabella `users_stories_log`** per tracciare le ore giornaliere spese su ciascun ticket
- **Calcolo automatico dei minuti lavorati** basato sugli orari lavorativi (9-18, Lun-Ven)
- **Aggiornamento in tempo reale** tramite observer e job asincroni
- **Visualizzazione attività per ticket** nella vista dettaglio

### **🎨 Interfaccia Stati Ticket**
- **Badge colorati con icone** per gli stati dei ticket
- **Dashboard documentazione stati** con descrizioni e significati
- **Colori personalizzati** per ogni stato secondo logica semantica:
  - **Arancioni**: assigned → todo → progress → testing
  - **Verde**: tested → released → done
  - **Giallo**: waiting
  - **Rosso**: problem, rejected
- **Icone emoji** per identificazione immediata

### **📝 Distinzione Problemi/Attese**
- **Nuovo stato "Problem"** per distinguere problemi tecnici dalle attese
- **Campi dedicati** per:
  - Motivo attesa (`waiting_reason`)
  - Descrizione problema (`problem_reason`)
- **Validazione automatica** obbligatoria quando selezionati gli stati corrispondenti
- **Tabelle separate** in Kanban-2 per gestione ottimale

### **🗂️ Riorganizzazione Menu**
- **Nuovo blocco "NEW"** per creazione rapida: Ticket, FundRaising, Tag
- **Rinomina "DEV" in "AGILE"** con sottomenu "Tickets" organizzato
- **Nuovo blocco "HELP"** in prima posizione:
  - Documentazione generale
  - Stati Ticket
  - Changelog
- **Permessi granulari** per accesso basato su ruoli
- **Rimozione card** dalle pagine principali per spazio maggiore

### **📚 Dashboard Changelog**
- **Visualizzazione release** con contenuti formattati
- **Storia completa** delle modifiche in ordine inverso
- **Interfaccia statica** con HTML dedicato per ogni release
- **Navigazione Help** > Changelog

---

## 👥 **CONTROLLO ACCESSI E PERMESSI**

- **Blocco "NEW"**: visibile a tutti eccetto Customer
- **Blocco "CRM"**: visibile solo a Manager e Admin
- **Item "FundRaising"**: visibile solo a ruoli fundraising e admin
- **Item "Tag"**: visibile solo ad Admin
- **Menu "AGILE"**: completo per tutti i ruoli interni
- **Kanban-2**: accessibile ad Admin e Developer

---

## 🔧 **MIGLIORAMENTI TECNICI**

### **Database**
- **Nuova tabella `users_stories_log`**:
  - `date`, `user_id`, `story_id`, `elapsed_minutes`
  - Unique constraint su (date, user_id, story_id)
  - Relazioni BelongsTo con User e Story
- **Nuovi campi `stories`**:
  - `waiting_reason` (text, nullable)
  - `problem_reason` (text, nullable)

### **Codice**
- **Enum `StoryStatus`**:
  - Aggiunto case `Problem`
  - Metodi `color()` e `icon()` per visualizzazione
  - Riorganizzazione ordine stati logico
- **Nuovi modelli**:
  - `UsersStoriesLog` con relazioni
- **Nuovi servizi**:
  - `UpdateUsersStoriesLogService` per calcolo attività
  - Comando `users-stories-log:dispatch` per elaborazione batch
  - Job `UpdateUsersStoriesLogJob` per elaborazione asincrona
- **Observer aggiornati**:
  - `StoryObserver` integra aggiornamento `users_stories_log`
- **Nova Resources**:
  - `Kanban2` dashboard completamente nuova
  - Metodi `userActivityField()` per visualizzazione attività
  - Metodi `waitingReasonField()` e `problemReasonField()`
  - Metodi `statusField()` refactor con HTML custom

### **Performance**
- **Eager loading** relazioni nelle query Kanban-2
- **Aggiornamenti asincroni** tramite queue per `users_stories_log`
- **Query ottimizzate** per attività recenti con grouping

---

## 📊 **NUOVE FUNZIONALITÀ PER RUOLI**

### **👨‍💼 ADMIN**
- **Dashboard Kanban-2** con visualizzazione completo workload team
- **Tracking attività** dettagliato per tutti gli utenti
- **Configurazione accessi** granulare per menu e voci
- **Dashboard Changelog** per overview release
- **Gestione stati** con documentazione completa

### **👨‍💻 DEVELOPER**
- **Dashboard Kanban-2 personalizzata** con focus sul proprio lavoro
- **Visualizzazione "Cosa ho fatto ieri?"** per tracciamento attività
- **Distinzione problemi/attese** per gestione workflow ottimale
- **Stati visualizzati** con badge colorati intuitivi
- **Menu AGILE** organizzato per lavoro quotidiano

### **🏢 CUSTOMER**
- **Interfaccia semplificata** con rimozione clutter
- **Menu ottimizzato** per accesso veloce a funzionalità rilevanti
- **Visualizzazione ticket** migliorata senza card distraenti

### **👥 MANAGER**
- **Accesso blocco CRM** per gestione clienti
- **Dashboard Kanban-2** per overview team
- **Tracking attività** per analisi performance

---

## 📋 **DETTAGLI TECNICI**

### File Creati
- `app/Nova/Dashboards/Kanban2.php` - Dashboard Kanban-2
- `app/Nova/Dashboards/TicketStatus.php` - Dashboard documentazione stati
- `app/Nova/Dashboards/Changelog.php` - Dashboard changelog
- `app/Models/UsersStoriesLog.php` - Model per tracking attività
- `app/Actions/UpdateUsersStoriesLogService.php` - Servizio calcolo attività
- `app/Jobs/UpdateUsersStoriesLogJob.php` - Job asincrono
- `app/Console/Commands/DispatchUpdateUsersStoriesLogCommand.php` - Comando dispatch
- `resources/views/story-viewer-kanban2.blade.php` - View tabelle Kanban-2
- `resources/views/story-viewer-recent-activities.blade.php` - View attività recenti
- `resources/views/ticket-status-documentation.blade.php` - View stati ticket
- `resources/views/changelog-dashboard.blade.php` - View changelog
- `database/migrations/2025_11_03_030621_create_users_stories_log_table.php` - Migrazione users_stories_log
- `database/migrations/2025_11_02_172519_add_waiting_and_problem_reasons_to_stories_table.php` - Migrazione campi reason
- `.cursor/templates/major_release.md` - Template processo release
- `.cursor/templates/aggiorna-con-prod.md` - Template aggiornamento DB

### File Modificati
- `config/app.php` - Aggiornamento versione
- `app/Enums/StoryStatus.php` - Aggiunto Problem, metodi color/icon, riordinato
- `app/Models/Story.php` - Fillable waiting/problem_reason, validazione boot
- `app/Observers/StoryObserver.php` - Integrato UpdateUsersStoriesLogJob
- `app/Traits/fieldTrait.php` - Nuovi metodi field, refactor statusField
- `app/Providers/NovaServiceProvider.php` - Riorganizzazione menu, nuove dashboard
- `app/Nova/Story.php` - Metodo userActivityField, refactor validation
- `app/Nova/CustomerStory.php` - Metodo label, cards vuoti
- `app/Nova/InProgressStory.php` - Label, cards vuoti, authorizedToCreate
- `app/Nova/AssignedToMeStory.php` - Label, cards vuoti, authorizedToCreate
- `app/Nova/ToBeTestedStory.php` - Label, cards vuoti
- `app/Nova/BacklogStory.php` - Label, cards vuoti, authorizedToCreate
- `app/Nova/ArchivedStories.php` - Label, cards vuoti

### Database
- **Migrazione**: `2025_11_03_030621_create_users_stories_log_table.php`
  - Tabelle create: `users_stories_log`
  - Foreign keys: user_id, story_id
  - Index: date
  - Unique: (date, user_id, story_id)
- **Migrazione**: `2025_11_02_172519_add_waiting_and_problem_reasons_to_stories_table.php`
  - Colonna aggiunta a `stories`: `waiting_reason` (text, nullable)
  - Colonna aggiunta a `stories`: `problem_reason` (text, nullable)

---

## ⚠️ **BREAKING CHANGES**

Nessun breaking change. Le modifiche sono retrocompatibili.

---

## 📝 **NOTES**

### Deployment
1. **Eseguire migrazioni**:
   ```bash
   docker-compose exec phpfpm php artisan migrate
   ```

2. **Elaborare dati storici** (opzionale, consigliato):
   ```bash
   docker-compose exec phpfpm php artisan users-stories-log:dispatch
   ```

3. **Pulire cache**:
   ```bash
   docker-compose exec phpfpm php artisan optimize:clear
   ```

### Configurazione
- Le nuove dashboard sono accessibili automaticamente tramite menu
- Il tracking attività parte automaticamente per tutte le modifiche future
- Per i dati storici, eseguire il comando dispatch sopra indicato

### Performance
- Le query Kanban-2 includono eager loading per ottimizzazione
- L'elaborazione `users_stories_log` avviene asincronamente tramite queue
- Il calcolo delle ore considera solo orari lavorativi (9-18, Lun-Ven)

---

## 🎉 **ACKNOWLEDGMENTS**

Ringraziamenti speciali a tutto il team per il feedback continuo e per aver testato le nuove funzionalità durante lo sviluppo. Questa release rappresenta un passo significativo verso un'interfaccia più intuitiva e un workflow più efficiente.

**Buon lavoro a tutti!** 🙌

---

**Team Orchestrator**  
*Webmapp S.r.l.*

