# CHANGELOG - Release MS-1.16.1
**Data Release:** 27 Settembre 2025  
**Versione:** MS-1.16.1  
**Branch:** montagna-servizi  

---

## 🎯 **RELEASE HIGHLIGHTS**

È con grande soddisfazione che annunciamo la **Release MS-1.16.1**, una versione significativa che introduce il **nuovo sistema FundRaising** completamente integrato nella piattaforma Orchestrator. Questa release rappresenta un importante passo avanti nella gestione dei progetti di fundraising e nell'esperienza utente per i nostri clienti.

---

## 🚀 **NUOVE FUNZIONALITÀ**

### **📊 Sistema FundRaising Completo**
- **Gestione Opportunità di Finanziamento (FRO)**
  - Creazione e gestione completa delle opportunità di finanziamento
  - Campi dettagliati: nome bando, URL ufficiale, fondo di dotazione, scadenze
  - Gestione sponsor, programmi e requisiti specifici
  - Scope territoriale configurabile (Cooperazione, Europeo, Nazionale, Regionale, Territoriale, Comuni)

- **Gestione Progetti di Fundraising (FRP)**
  - Creazione progetti collegati alle opportunità di finanziamento
  - Gestione capofila e partner (integrazione con sistema utenti)
  - Tracking importi richiesti e approvati
  - Stati progetto: Bozza, Presentato, Approvato, Respinto, Completato

### **👥 Controllo Accessi Basato su Ruoli**
- **Nuovo Ruolo "Fundraising"**
  - Accesso completo al sistema FundRaising
  - Gestione opportunità e progetti
  - Dashboard dedicata con focus su fundraising

- **Ruolo "Customer" Potenziato**
  - Visualizzazione opportunità di finanziamento attive
  - Accesso ai propri progetti (come capofila o partner)
  - Dashboard customer con informazioni fundraising

### **📥 Import JSON Avanzato**
- **Action "Crea da JSON"**
  - Import rapido di opportunità di finanziamento da dati JSON
  - Validazione automatica dei campi obbligatori
  - Override configurabili per responsabile e scope territoriale
  - Template JSON integrato per facilità d'uso

### **🎛️ Dashboard e Interfaccia Utente**
- **Dashboard Customer Dedicata**
  - Card opportunità attive
  - Visualizzazione progetti coinvolti
  - Attività recenti correlate ai progetti

- **Menu Personalizzato**
  - Sezione "FUNDRAISING" per utenti fundraising/admin
  - Sezione "CUSTOMER" con sottosezione FundRaising per clienti
  - Navigazione ottimizzata per ruolo utente

---

## 🔧 **MIGLIORAMENTI TECNICI**

### **🏗️ Architettura e Database**
- **Nuove Tabelle**
  - `fundraising_opportunities` - Gestione opportunità di finanziamento
  - `fundraising_projects` - Gestione progetti di fundraising
  - `fundraising_project_partners` - Tabella pivot per partner di progetto

- **Relazioni Database**
  - Integrazione completa con sistema utenti esistente
  - Collegamento progetti con storie/ticket per tracciabilità
  - Foreign keys ottimizzate per performance

### **🛡️ Sicurezza e Permessi**
- **Policy Granulari**
  - Controllo accessi specifico per risorse fundraising
  - Policy separate per utenti customer vs fundraising
  - Protezione dati sensibili e accessi non autorizzati

### **🎨 Interfaccia Nova**
- **Risorse Customer Dedicati**
  - `CustomerFundraisingOpportunity` - Visualizzazione limitata per clienti
  - `CustomerFundraisingProject` - Accesso solo ai progetti coinvolti
  - Filtri e azioni specifiche per ruolo utente

- **Actions e Filtri Avanzati**
  - Filtri per scope territoriale e stato progetti
  - Filtro opportunità scadute/attive
  - Azioni personalizzate per workflow fundraising

---

## 🐛 **CORREZIONI E OTTIMIZZAZIONI**

### **🔍 Debug e Sviluppo**
- **Laravel Debugbar Integrata**
  - Monitoraggio query SQL in tempo reale
  - Analisi performance e memoria
  - Log dettagliati per debugging avanzato

### **🧹 Pulizia Codice**
- **Rimozione Funzionalità Problematiche**
  - Eliminata action PDF export (problemi encoding UTF-8)
  - Cleanup codice non utilizzato
  - Ottimizzazione import e namespace

### **⚡ Performance**
- **Ottimizzazioni Database**
  - Query ottimizzate per relazioni fundraising
  - Indici appropriati per ricerca e filtraggio
  - Lazy loading per relazioni complesse

---

## 📋 **DETTAGLI TECNICI**

### **🗄️ Migrazioni Database**
```sql
- create_fundraising_opportunities_table
- create_fundraising_projects_table  
- create_fundraising_project_partners_table
- add_fundraising_project_id_to_stories_table
- fix_fundraising_customer_relations_to_users
```

### **🎯 Modelli Eloquent**
- `FundraisingOpportunity` - Gestione opportunità
- `FundraisingProject` - Gestione progetti
- Relazioni aggiornate in `User`, `Customer`, `Story`

### **🔧 Configurazione**
- Nuovo ruolo `fundraising` in `UserRole` enum
- Inizializzazione utenti di test (Sara Mariani - fundraising, Roberto Manfredi - customer)
- Menu Nova personalizzato per ruoli

---

## 👥 **IMPATTO SUL TEAM**

### **👨‍💻 Per gli Sviluppatori**
- Nuovo sistema di gestione progetti fundraising
- API e relazioni database estese
- Debugbar per sviluppo più efficiente
- Codice pulito e documentato

### **👤 Per gli Utenti Fundraising**
- Interfaccia dedicata per gestione opportunità
- Import rapido da dati esterni
- Dashboard con overview completa
- Workflow ottimizzato per fundraising

### **🏢 Per i Clienti**
- Accesso trasparente ai propri progetti
- Dashboard personalizzata con informazioni rilevanti
- Visibilità su opportunità di finanziamento attive
- Esperienza utente migliorata

---

## 🚀 **DEPLOYMENT E RILASCIO**

### **📦 Branch e Tag**
- **Branch:** `montagna-servizi`
- **Tag:** `MS-1.16.1`
- **Commit:** 8f582d7

### **🔧 Requisiti Deployment**
- Database migrations da eseguire
- Cache clearing necessario
- Configurazione utenti di test opzionale

### **📊 Metriche Release**
- **File modificati:** 25+ file
- **Nuove funzionalità:** 8+ features principali
- **Righe codice:** +2000 linee aggiunte
- **Test coverage:** Funzionalità core testate

---

## 📞 **SUPPORTO E DOCUMENTAZIONE**

Per domande tecniche o supporto:
- **Documentazione:** Consultare il README aggiornato
- **Issues:** GitHub Issues per bug reports
- **Team:** Contattare il team di sviluppo per chiarimenti

---

## 🎉 **CONCLUSIONI**

La **Release MS-1.16.1** rappresenta un traguardo significativo per la piattaforma Orchestrator. L'introduzione del sistema FundRaising completa l'ecosistema di gestione progetti, offrendo agli utenti strumenti potenti e intuitivi per la gestione dei finanziamenti.

**Grazie a tutto il team per il lavoro straordinario!** 🙌

---

**Team di Sviluppo Orchestrator**  
*Webmapp S.r.l.*  
27 Settembre 2025
