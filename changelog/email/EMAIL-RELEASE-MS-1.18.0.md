# 🚀 Release MS-1.18.0 - Nuova Interfaccia Agile

**Ciao Team!** 👋

Siamo lieti di comunicarvi l'aggiornamento **MS-1.18.0** della piattaforma Orchestrator che introduce una revisione completa dell'interfaccia utente con nuove dashboard personalizzate, un sistema di tracciamento attività avanzato e miglioramenti significativi nell'organizzazione del workflow agile.

---

## 🎯 **COSA C'È DI NUOVO**

Questa release migliora l'esperienza di utilizzo della piattaforma attraverso nuove dashboard personalizzate, un migliore tracking delle attività e un'interfaccia più intuitiva per la gestione dei ticket. Le modifiche sono mirate a rendere il lavoro quotidiano più efficiente e organizzato.

### **📊 Dashboard Kanban-2**

Introduciamo una nuova dashboard completamente rinnovata per la visualizzazione dei vostri ticket in modo organizzato e chiaro:

- **Quattro tabelle dedicate** per diversi aspetti del workflow:
  - **In attesa di verifica (da testare)** - Ticket che avete completato e aspettano verifica
  - **Che problemi ho incontrato (in attesa)** - Ticket in cui avete problemi tecnici o siete in attesa di informazioni
  - **Cosa devo fare oggi (todo)** - Lavoro da completare oggi
  - **Cosa devo verificare (da testare)** - Ticket assegnati per testing
  
- **Visualizzazione attività recenti** "Cosa ho fatto ieri?" per tracciare le ultime 2 giornate lavorative con dettagli delle ore spese

- **Selettore utente** per Admin e Developer per visualizzare il lavoro di qualsiasi membro del team

- **Contatore ticket dinamico** in ogni tabella per avere sempre presente il carico di lavoro

---

## 🏗️ **FEATURE PRINCIPALI**

### **📈 Sistema di Tracking Attività**

Un nuovo sistema avanzato per tracciare automaticamente le attività su ogni ticket:

- **Tracciamento automatico** delle ore giornaliere spese su ciascun ticket
- **Calcolo intelligente** basato sugli orari lavorativi (9-18, Lun-Ven)
- **Aggiornamento in tempo reale** per tutte le modifiche ai ticket
- **Visualizzazione dettagliata** nella vista dettaglio di ogni ticket

Questa funzionalità vi permetterà di avere sempre una visibilità chiara su come state gestendo il vostro tempo e vi aiuterà nella pianificazione delle attività future.

### **🎨 Stati Ticket Ridisegnati**

Gli stati dei ticket sono stati completamente ridisegnati con badge colorati e icone intuitive:

- **Badge colorati** con icone emoji per identificazione immediata
- **Colori semantici** organizzati per logica:
  - **Arancioni**: assigned → todo → progress → testing (flusso di lavoro)
  - **Verde**: tested → released → done (completamento)
  - **Giallo**: waiting (attesa)
  - **Rosso**: problem, rejected (blocchi)
  
- **Dashboard documentazione** con spiegazioni dettagliate del significato di ogni stato

### **📝 Distinzione Problemi/Attese**

Ora potete distinguere chiaramente tra un problema tecnico e un'attesa di informazioni:

- **Nuovo stato "Problem"** per blocchi tecnici
- **Campi dedicati** per specificare:
  - Motivo dell'attesa quando un ticket è "in attesa"
  - Descrizione del problema quando un ticket è in "problem"
  
- **Validazione automatica** che richiede di compilare questi campi quando si selezionano gli stati corrispondenti

- **Tabelle separate** in Kanban-2 per una gestione ottimale di entrambi i casi

---

## 👥 **PER CHI È QUESTA RELEASE**

### **👨‍💼 Admin**

- **Dashboard Kanban-2 completa** per visualizzazione workload di tutto il team
- **Tracking attività dettagliato** per analisi performance e pianificazione
- **Configurazione accessi granulare** per menu e funzionalità
- **Dashboard Changelog** per overview di tutte le release
- **Gestione stati** con documentazione completa

### **👨‍💻 Developer**

- **Dashboard Kanban-2 personalizzata** con focus sul proprio lavoro quotidiano
- **Visualizzazione "Cosa ho fatto ieri?"** per tracciare automaticamente le proprie attività
- **Distinzione problemi/attese** per una gestione del workflow più efficace
- **Stati visualizzati** con badge colorati immediatamente comprensibili
- **Menu AGILE organizzato** per accesso rapido alle funzionalità principali
- **Comando dedicato** per elaborare dati storici di attività

### **🏢 Customer**

- **Interfaccia semplificata** con rimozione di elementi non essenziali
- **Menu ottimizzato** per accesso veloce alle funzionalità rilevanti
- **Visualizzazione ticket migliorata** senza distrazioni

### **👥 Manager**

- **Accesso completo a blocco CRM** per gestione clienti
- **Dashboard Kanban-2** per overview team
- **Tracking attività** per analisi performance e resource planning

---

## 🗂️ **MIGLIORAMENTI INTERFACCIA**

### **Menu Riorganizzato**

Il menu principale è stato completamente riorganizzato per una navigazione più intuitiva:

- **Nuovo blocco "NEW"** in prima posizione per creazione rapida: Ticket, FundRaising, Tag
- **Rinominato "DEV" in "AGILE"** con sottomenu "Tickets" organizzato
- **Nuovo blocco "HELP"** in prima posizione con:
  - Documentazione generale
  - Stati Ticket (nuova dashboard)
  - Changelog (nuova dashboard)

### **Ottimizzazioni Spazio**

- **Rimosse card** dalle pagine principali per dare più spazio alla visualizzazione ticket
- **Viste semplificate** per focus sul contenuto essenziale
- **Layout ottimizzato** per lavoro efficiente

---

## 📋 **DETTAGLI RILASCIO**

- **Versione:** MS-1.18.0
- **Data:** 03 Novembre 2025
- **Stato:** Disponibile
- **Branch:** montagna-servizi

---

## ⚠️ **NOTA IMPORTANTE**

### **Per gli Amministratori**

Al primo accesso dopo il deployment:

1. **Eseguire le migrazioni**:
   ```bash
   docker-compose exec phpfpm php artisan migrate
   ```

2. **Elaborare dati storici** (consigliato per visualizzare attività passate):
   ```bash
   docker-compose exec phpfpm php artisan users-stories-log:dispatch
   ```

3. **Pulire cache**:
   ```bash
   docker-compose exec phpfpm php artisan optimize:clear
   ```

Il tracking attività partirà automaticamente per tutte le modifiche future ai ticket. Per i dati storici, è consigliato eseguire il comando sopra indicato.

---

## 🎉 **GRAZIE!**

Questo aggiornamento migliora significativamente l'esperienza di utilizzo della piattaforma per tutti gli utenti. Continuiamo a lavorare per rendere Orchestrator sempre più efficiente e intuitivo.

Il feedback di tutti voi è fondamentale per migliorare costantemente la piattaforma. Non esitate a condividere i vostri commenti e suggerimenti!

**Buon lavoro a tutti!** 🙌

---

**Team Orchestrator**  
*Webmapp S.r.l.*

*Per domande o assistenza, contattate il team tecnico.*

