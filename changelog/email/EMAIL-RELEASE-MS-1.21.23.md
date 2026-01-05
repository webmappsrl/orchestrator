# 🔧 Release MS-1.21.23 - Miglioramenti Gestione Ticket in Attesa

**Ciao!** 👋

Siamo lieti di comunicarvi l'aggiornamento **MS-1.21.23** della piattaforma Orchestrator che introduce miglioramenti significativi nella gestione dei ticket in attesa e correzioni importanti alla logica di ripristino automatico.

---

## 🚀 **NUOVE FUNZIONALITÀ**

### **📊 Gestione Manuale Ticket in Attesa**
- **Nova Action per aggiornamento manuale**: Nuova action "Aggiorna Ticket in Attesa" disponibile nella risorsa `WaitingStory` per aggiornare manualmente i ticket selezionati senza aspettare il comando automatico
- **Ordinamento intelligente**: I ticket in attesa sono ora ordinati automaticamente per giorni di attesa (dal più vecchio al più recente) per facilitare la gestione delle priorità
- **Visualizzazione giorni di attesa**: La colonna "Ragione dell'attesa" mostra anche il numero di giorni trascorsi in stato Waiting per una migliore visibilità

### **🔧 Miglioramenti Tecnici**
- **Service riutilizzabile**: Estratto service centralizzato per la gestione dell'aggiornamento ticket in attesa, migliorando la manutenibilità del codice
- **Test completi**: Aggiunta suite di test completa (13 test, 47 asserzioni) per garantire affidabilità del sistema

---

## 🐛 **BUG FIX**

### **Correzione Logica Transizione Stati**
- **Ripristino corretto da todo**: Corretta la logica di ripristino stati nel comando automatico. I ticket che erano in stato `todo` prima di entrare in `waiting` vengono ora correttamente ripristinati in `todo` invece di essere spostati erroneamente
- **Gestione stati progress/released/done**: I ticket che erano in `progress`, `released` o `done` vengono correttamente ripristinati in `todo` come previsto dalla logica di business

---

## 📋 **DETTAGLI RILASCIO**

- **Versione:** MS-1.21.23
- **Data:** 05/01/2026
- **Stato:** Disponibile

---

## 🎉 **GRAZIE!**

**Buon lavoro!** 🙌

---

**Team Orchestrator**  
*Webmapp S.r.l.*

*Per domande o assistenza, contattate il team tecnico.*

