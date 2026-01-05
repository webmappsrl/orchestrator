# CHANGELOG MS-1.22.0

**Release Date:** 05/01/2026  
**Version:** MS-1.22.0

## 📋 Riassunto Minor Release 1.21.x

Questa minor release consolida tutte le patch rilasciate dalla versione MS-1.21.0 alla MS-1.21.24, includendo miglioramenti significativi all'interfaccia utente, nuove funzionalità per la gestione dei ticket, miglioramenti alla dashboard customer, sistema di changelog dinamico, e numerosi bug fix e miglioramenti tecnici.

---

## 🚀 New Features

### Dashboard e Interfaccia Kanban
- **Riorganizzazione Kanban2 Dashboard** - Nuove risorse Nova per stati ticket (ProblemStory, TestStory, WaitingStory), sottomenu SCRUM nella sezione AGILE, semplificazione dashboard Kanban2 con focus su attività giornaliere
- **Dashboard Customer** - Dashboard completa con card informative (login info, ticket da completare, progetti FSP, documentazione, archiviazione Google Drive, budget Google Drive)
- **Sistema Changelog Dinamico** - Sistema completamente dinamico per visualizzazione changelog in Nova, organizzazione automatica per minor release, conversione markdown → HTML automatica

### Gestione Ticket
- **Automated Scrum Archive** - Comando artisan `orchestrator:scrum-archive` per archiviare automaticamente ticket Scrum con task schedulato giornaliero
- **Auto-Restore Waiting Stories** - Comando `orchestrator:autoupdate-waiting` per ripristinare automaticamente ticket da stato "Waiting" dopo periodo configurato (default: 7 giorni)
- **Gestione Manuale Ticket in Attesa** - Nova Action per aggiornamento manuale ticket in attesa con ordinamento intelligente per giorni di attesa

### Documentazione e Template
- **Documentazione Flusso Ticket** - Dashboard TicketFlow in Nova con documentazione completa del flusso di evoluzione dei ticket
- **Template Deploy Produzione** - Template completo per deploy automatizzato in produzione con gestione Docker, backup automatico `.env`, e troubleshooting

### Branding
- **Branding Nova** - Configurazione logo Montagna Servizi in Nova con supporto SVG e configurazione tramite variabili d'ambiente

---

## 🔧 Improvements

### Interfaccia Ticket Nova
- **Riorganizzazione colonne index ticket** - Nuova colonna MAIN INFO che contiene ID (cliccabile), STATO e USER assegnato
- **Effective hours in History** - Aggiunte Effective hours come ultima riga nella colonna History
- **Colonne ragione attesa/problemi** - Aggiunta colonna "Ragione dell'attesa" in WaitingStory e "Ragione dei problemi" in ProblemStory
- **Info Column Enhancements** - Label "Tag:" aggiunto, link tester nella colonna Informazioni
- **Pannello storico e attività** - Campo "Ticket Changes" che mostra tutti i cambiamenti del ticket con timestamp e descrizioni dalla tabella `story_logs`
- **Campo Creatore modificabile** - Campo Creatore ora modificabile e searchable nella creazione ticket
- **Visualizzazione giorni di attesa** - Colonna "Ragione dell'attesa" mostra anche numero di giorni trascorsi in stato Waiting

### Gestione Stati Ticket
- **Miglioramenti Change Status Action** - Disponibile solo in detail view, gestione intelligente stati PROBLEM/WAITING con recupero stato precedente da `story_logs`
- **Gestione stati migliorata** - Rimossa opzione "Rejected" da TODO, campo tester obbligatorio per TEST, stato "Released" disponibile da TESTING
- **Gestione fallimento test** - Campo "Ragione del fallimento del test" obbligatorio quando si passa da Testing a TODO, nota automatica aggiunta alle note di sviluppo

### Replica Ticket
- **Suffisso (COPY) nel titolo** - Aggiunto automaticamente durante replica (pulsante Replicate o azione Duplicate Story)
- **Visualizzazione tag durante replica** - Campo "Replicated Tags" readonly durante replica
- **Copia automatica tag** - Tag del ticket originale copiati automaticamente al nuovo ticket

### Dashboard Customer
- **Card informative** - Login info, ticket da completare, progetti FSP, documentazione e contatti, archiviazione Google Drive, budget Google Drive
- **Miglioramenti menu** - Menu ADMIN nascosto ai customer, menu HELP visibile, label migliorati, riorganizzazione voci menu
- **Filtri documentazione** - Customer vedono solo documentazioni con category=customer, colonne nascoste per customer

### Sistema Changelog
- **Indice patch** - Indice delle patch all'inizio pagina changelog per ogni minor release
- **Navigazione migliorata** - Link "Torna all'indice" per navigazione rapida, scroll fluido tra sezioni

### Configurazione e Deployment
- **Comandi chmod non bloccanti** - Script deploy produzione più robusto, comandi chmod non interrompono esecuzione
- **Artisan User Configuration** - Configurazione centralizzata per email utente utilizzato dai comandi Artisan (`ARTISAN_USER_EMAIL`)
- **Configurazione Google Drive** - Campi `google_drive_url` e `google_drive_budget_url` nella tabella users per archiviazione documenti

### Miglioramenti Logging
- **StoryObserver migliorato** - Filtro valori null nei cambiamenti, inclusione sempre `updated_at` nel log di creazione, status default "new" se null

### Menu Navigation
- **Riorganizzazione menu SCRUM** - Spostamento voce "Nuovi" in SCRUM, organizzazione logica risorse e dashboard

---

## 🐛 Bug Fixes

### Activity Report PDF
- **Formattazione tabelle PDF** - Risolto problema formattazione tabelle nei PDF report attività mensili, tabelle entro margini A4 con colonne a larghezza fissa e text wrapping automatico
- **Ottimizzazione colonne** - Rimossa colonna Creator, larghezze colonne ottimizzate (ID: 8%, Done At: 12%, Title: 25%, Request: 55%)

### Interfaccia Utente - Validazione Campi
- **Campo "Ragione del fallimento del test"** - Mostrato solo quando necessario (stato corrente "Testing" e nuovo stato "Todo")

### Ticket Archiviati
- **Errore caricamento archived-stories** - Risolto errore causato da import mancante del campo Stack
- **Errore 403 replica ticket archiviati** - Risolto errore 403 durante replica, implementata logica replicate da customer-stories
- **Rimossa azione DuplicateStory** - Rimossa azione obsoleta, utilizzo solo pulsante Replicate standard

### Nova Resources
- **Errore "Only arrays and Traversables can be unpacked"** - Risolto errore in StoryShowedByCustomer con resource null o status null, aggiunti controlli espliciti

### Test Suite
- **Corretto StoryCreationLogTest** - Risolto problema asserzione che falliva a causa campo `updated_at` nei changes
- **Eliminato test obsoleto DuplicateStoryTest** - Rimosso test che causava errori PSR-4 autoloading

### Gestione Null User negli Observer
- **Errore "Attempt to read property 'id' on null"** - Corretto errore in StoryObserver, MediaObserver e helper quando utente Artisan non esiste, gestione graceful con warning

### Logica Transizione Stati
- **Correzione ripristino stati auto-restore** - Corretta logica transizione stati nel comando `orchestrator:autoupdate-waiting`, gestione corretta stati progress/released/done e todo

---

## 📋 Technical Details

### File Creati (Principali)
- `app/Nova/ProblemStory.php` - Risorsa Nova per ticket con status "Problemi"
- `app/Nova/TestStory.php` - Risorsa Nova per ticket con status "Da Testare"
- `app/Nova/WaitingStory.php` - Risorsa Nova per ticket con status "In Attesa"
- `app/Nova/Dashboards/CustomerDashboard.php` - Dashboard customer completa
- `app/Nova/Dashboards/TicketFlow.php` - Dashboard documentazione flusso ticket
- `app/Services/ChangelogService.php` - Servizio gestione changelog dinamico
- `app/Nova/Dashboards/ChangelogMinorRelease.php` - Dashboard dinamica per minor release
- `app/Console/Commands/ScrumArchiveCommand.php` - Comando archiviazione ticket Scrum
- `app/Console/Commands/AutoUpdateWaitingStories.php` - Comando auto-restore ticket Waiting
- `app/Services/AutoUpdateWaitingStoriesService.php` - Service centralizzato per aggiornamento ticket Waiting
- `app/Nova/Actions/UpdateWaitingStoriesAction.php` - Nova action per aggiornamento manuale ticket Waiting
- `public/images/logo-montagna-servizi.svg` - Logo SVG Montagna Servizi
- `.cursor/templates/deploy_produzione.md` - Template deploy produzione
- Varie view Blade per dashboard customer e changelog

### File Modificati (Principali)
- `app/Nova/Story.php` - Riorganizzazione colonne, campo Ticket Changes, gestione replicate
- `app/Nova/Actions/ChangeStatus.php` - Miglioramenti gestione stati, campo fallimento test, recupero stato precedente
- `app/Traits/fieldTrait.php` - Nuovi metodi per colonne, gestione tag, history log
- `app/Providers/NovaServiceProvider.php` - Riorganizzazione menu, registrazione dashboard dinamiche
- `app/Observers/StoryObserver.php` - Miglioramenti logging, gestione null user
- `app/Models/Story.php` - Metodo replicate migliorato, gestione tag durante replica
- `app/Console/Kernel.php` - Scheduling comandi automatizzati
- `config/orchestrator.php` - Configurazioni scrum archive, auto-restore, artisan user, nova logo
- `config/nova.php` - Configurazione logo branding
- Varie risorse Nova (ArchivedStories, WaitingStory, ProblemStory, etc.)

### Database
- Migrazione: `2025_12_28_132357_add_google_drive_url_to_users_table.php` - Campo `google_drive_url` in users
- Migrazione: `2025_12_28_133645_add_google_drive_budget_url_to_users_table.php` - Campo `google_drive_budget_url` in users

### Dependencies
- Aggiunta dipendenza `league/commonmark` per conversione markdown → HTML

---

## 📝 Notes

### Compatibilità
- **Nessuna breaking change** - Tutte le modifiche sono retrocompatibili
- **Miglioramenti incrementali** - Focus su nuove feature e miglioramenti dell'esperienza utente
- **Performance** - Ottimizzazioni varie per migliorare performance (pre-caricamento utenti, caching, etc.)

### Deployment
- **Cache clearing** - Dopo il deploy, eseguire `docker-compose exec phpfpm php artisan optimize:clear`
- **Migrazioni** - Eseguire migrazioni se presenti: `docker-compose exec phpfpm php artisan migrate`
- **Configurazione** - Verificare variabili d'ambiente per nuove funzionalità (scrum archive, auto-restore waiting, etc.)

### Documentazione
- **Documentazione completa** - Aggiornata documentazione comandi Artisan, transizioni automatiche, flusso ticket
- **Template deploy** - Disponibile template completo per deploy produzione automatizzato
- **Dashboard Changelog** - Sistema completamente dinamico, nessuna modifica manuale necessaria per nuove release

### Organizzazione
- **Menu riorganizzato** - Menu SCRUM e HELP migliorati per migliore navigazione
- **Dashboard customer** - Interfaccia completa e intuitiva per utenti customer
- **Sistema changelog** - Visualizzazione automatica e organizzata di tutte le release

---

## 🎯 Statistiche Release 1.21.x

- **Totale patch rilasciate:** 25 (da MS-1.21.0 a MS-1.21.24)
- **Nuove feature principali:** 8
- **Miglioramenti significativi:** 15+
- **Bug fix:** 10+
- **File creati:** 20+
- **File modificati:** 50+
- **Migrazioni database:** 2
- **Nuove dipendenze:** 1 (league/commonmark)

---

**Questa minor release rappresenta un consolidamento significativo delle funzionalità e miglioramenti sviluppati durante il ciclo 1.21.x, con focus particolare su:**
- Miglioramento dell'esperienza utente nelle dashboard e interfacce Nova
- Automazione di processi manuali (archiviazione Scrum, auto-restore Waiting)
- Sistema di documentazione e changelog dinamico
- Robustezza e stabilità del sistema (bug fix, gestione errori, test)

