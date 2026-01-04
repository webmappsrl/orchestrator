# Flusso Naturale dell'Evoluzione dei Ticket

Questo documento descrive il flusso naturale dell'evoluzione dei ticket nel sistema Orchestra, basato sulla definizione degli stati e sulle regole di transizione implementate nel codice.

## 📋 Indice

1. [Stati dei Ticket](#stati-dei-ticket)
2. [Flusso Principale (Happy Path)](#flusso-principale-happy-path)
3. [Flussi Alternativi](#flussi-alternativi)
4. [Transizioni Automatiche](#transizioni-automatiche)
5. [Regole di Validazione](#regole-di-validazione)
6. [Diagramma di Flusso](#diagramma-di-flusso)

---

## Stati dei Ticket

### Stati Iniziali
- **✨ New** (Blu): Il ticket è stato appena creato e non è ancora stato assegnato a nessun sviluppatore.
- **⏱️ Backlog** (Grigio): Il ticket è stato messo in coda per essere lavorato in futuro. Non è ancora in lavorazione attiva.

### Stati di Lavorazione (Arancioni)
- **👤 Assigned** (Arancione scuro): Il ticket è stato assegnato a uno sviluppatore ma non è ancora iniziato il lavoro.
- **📋 Todo** (Arancione medio): Il ticket è pronto per essere lavorato dallo sviluppatore assegnato.
- **⚡ Progress** (Arancione chiaro): Il ticket è attualmente in lavorazione da parte dello sviluppatore assegnato.
- **🧪 Testing** (Arancione molto chiaro): Il ticket è stato completato dallo sviluppatore e ora è in fase di verifica da parte di un tester.

### Stati di Completamento (Verde)
- **✅ Tested** (Verde chiaro): Il ticket è stato testato positivamente e può essere rilasciato o è pronto per la produzione.
- **🌐 Released** (Verde scuro): Il ticket è stato rilasciato in produzione e completato con successo.
- **✔️ Done** (Verde medio): Il ticket è completamente terminato e chiuso.

### Stati di Blocco/Attesa
- **⏸️ Waiting** (Giallo): Il ticket è in pausa in attesa di informazioni, approvazioni o azioni da parte di altre persone.
- **⚠️ Problem** (Rosso): Lo sviluppatore ha incontrato un problema tecnico che non riesce a risolvere autonomamente e richiede supporto.

### Stati Finali Negativi
- **❌ Rejected** (Rosso): Il ticket è stato rifiutato e non verrà implementato.

---

## Flusso Principale (Happy Path)

Il flusso naturale di un ticket segue questa sequenza:

```
✨ New → 👤 Assigned → 📋 Todo → ⚡ Progress → 🧪 Testing → ✅ Tested → 🌐 Released → ✔️ Done
```

**Alternativa senza testing:**
```
✨ New → 👤 Assigned → 📋 Todo → ⚡ Progress → 🌐 Released → ✔️ Done
```

### Dettaglio delle Transizioni

#### 1. **New → Assigned**
- **Trigger**: Assegnazione automatica quando viene assegnato un `user_id` (developer) a un ticket con stato `New`
- **Regola**: Automatica nel modello Story (`Story::boot()`)
- **Descrizione**: Quando un ticket viene creato e viene immediatamente assegnato a uno sviluppatore, lo stato passa automaticamente a `Assigned`

#### 2. **Assigned → Todo**
- **Trigger**: Manuale o automatico quando il developer è pronto a iniziare il lavoro
- **Descrizione**: Il ticket è stato assegnato e ora è nella lista delle cose da fare dello sviluppatore

#### 3. **Todo → Progress**
- **Trigger**: Manuale quando lo sviluppatore inizia effettivamente a lavorare sul ticket
- **Regola Speciale**: Solo un ticket può essere in `Progress` per ogni sviluppatore. Quando un ticket passa a `Progress`, tutti gli altri ticket dello stesso sviluppatore in `Progress` vengono automaticamente impostati a `Todo` (StoryObserver)
- **Descrizione**: Il lavoro è iniziato e lo sviluppatore sta attivamente lavorando sul ticket

#### 4. **Progress → Testing**
- **Trigger**: Manuale quando lo sviluppatore completa il lavoro di sviluppo
- **Regola**: Richiede che sia assegnato un `tester_id` prima di passare a `Testing`
- **Descrizione**: Il lavoro di sviluppo è completato e il ticket è pronto per essere testato

#### 4b. **Progress → Released** (alternativa)
- **Trigger**: Manuale quando lo sviluppatore completa il lavoro e il ticket non richiede verifica di un tester
- **Regola**: Imposta automaticamente `released_at = now()` quando lo stato cambia a `Released`
- **Descrizione**: Il ticket viene rilasciato direttamente in produzione senza passare per la fase di testing

#### 5. **Testing → Tested**
- **Trigger**: Manuale quando il tester completa i test con successo
- **Descrizione**: I test sono stati completati con successo e il ticket è pronto per essere rilasciato

#### 5b. **Testing → Todo** (test fallito)
- **Trigger**: Manuale quando il tester ritiene che il test sia fallito
- **Descrizione**: Se il test non viene superato, il ticket torna a `Todo` per permettere allo sviluppatore di correggere i problemi individuati

#### 6. **Tested → Released**
- **Trigger**: Manuale quando il ticket viene rilasciato in produzione
- **Regola**: Imposta automaticamente `released_at = now()` quando lo stato cambia a `Released`
- **Descrizione**: Il ticket è stato rilasciato in produzione

#### 7. **Released → Done**
- **Trigger**: Automatico dopo 3 giorni lavorativi dalla data di `released_at` (comando schedulato alle 07:45) oppure manuale
- **Regola**: Il comando `story:auto-update-status` controlla i ticket rilasciati da almeno 3 giorni lavorativi e li imposta a `Done`
- **Descrizione**: Il ticket è completamente terminato e chiuso

---

## Flussi Alternativi

### Flusso Backlog

```
✨ New → ⏱️ Backlog → 👤 Assigned → [continua flusso principale]
```

- **New → Backlog**: Quando un ticket viene messo in coda per lavorazione futura
- **Backlog → Assigned**: Quando il ticket viene preso in carico e assegnato a uno sviluppatore

### Flusso Problem

```
[New/Backlog/Assigned/Todo/Progress] → ⚠️ Problem → [stato precedente]
```

- **Quando**: Lo sviluppatore incontra un problema tecnico che non riesce a risolvere autonomamente
- **Regola**: Richiede obbligatoriamente la compilazione del campo `problem_reason`
- **Risoluzione**: Dopo aver risolto il problema, il ticket torna **solo** allo stato precedente da cui era partito

**Stati da cui si può passare a Problem:**
- `New`
- `Backlog`
- `Assigned`
- `Todo`
- `Progress`

**Stati verso cui si può tornare da Problem:**
- Solo lo **stato precedente** da cui si è passati a Problem:
  - Se era `New` → torna a `New`
  - Se era `Backlog` → torna a `Backlog`
  - Se era `Assigned` → torna a `Assigned`
  - Se era `Todo` → torna a `Todo`
  - Se era `Progress` → torna a `Progress`

### Flusso Waiting

```
[New/Backlog/Assigned/Todo/Progress] → ⏸️ Waiting → [stato precedente]
```

- **Quando**: Il ticket è in pausa in attesa di informazioni, approvazioni o azioni esterne
- **Regola**: Richiede obbligatoriamente la compilazione del campo `waiting_reason`
- **Risoluzione**: Quando l'attesa termina, il ticket torna **solo** allo stato precedente da cui era partito
- **Reminder**: I ticket in `Waiting` da più di 3 giorni lavorativi ricevono automaticamente un reminder email

**Stati da cui si può passare a Waiting:**
- `New`
- `Backlog`
- `Assigned`
- `Todo`
- `Progress`

**Stati verso cui si può tornare da Waiting:**
- Solo lo **stato precedente** da cui si è passati a Waiting:
  - Se era `New` → torna a `New`
  - Se era `Backlog` → torna a `Backlog`
  - Se era `Assigned` → torna a `Assigned`
  - Se era `Todo` → torna a `Todo`
  - Se era `Progress` → torna a `Progress`

### Flusso Rejected

```
[Qualsiasi stato] → ❌ Rejected
```

- **Quando**: Il ticket viene rifiutato e non verrà implementato
- **Descrizione**: Stato finale negativo che può essere raggiunto da qualsiasi stato
- **Nota**: Una volta `Rejected`, il ticket generalmente non viene più lavorato

---

## Transizioni Automatiche

### 1. Assegnazione Automatica (New → Assigned)
- **Quando**: Un ticket con stato `New` riceve un `user_id` (developer)
- **Dove**: `Story::boot()` -> `saving()`
- **Comportamento**: Lo stato cambia automaticamente a `Assigned`

### 2. Rimozione Developer da New
- **Quando**: Un ticket con stato `New` viene cambiato manualmente a un altro stato
- **Dove**: `Story::boot()` -> `saving()`
- **Comportamento**: Il campo `user_id` viene impostato a `null`

### 3. Solo un Progress per Developer
- **Quando**: Un ticket viene impostato a `Progress`
- **Dove**: `StoryObserver::saving()`
- **Comportamento**: Tutti gli altri ticket dello stesso sviluppatore in `Progress` vengono impostati a `Todo`

### 4. Progress → Todo (Automatico alle 18:00)
- **Quando**: Giornaliero alle 18:00
- **Dove**: Comando `story:progress-to-todo`
- **Comportamento**: Tutti i ticket in `Progress` vengono impostati a `Todo` per evitare che rimangano in lavorazione durante la notte

### 5. Released → Done (Automatico dopo 3 giorni)
- **Quando**: Giornaliero alle 07:45, per ticket rilasciati da almeno 3 giorni lavorativi
- **Dove**: Comando `story:auto-update-status`
- **Comportamento**: I ticket in `Released` da più di 3 giorni lavorativi vengono impostati a `Done`

### 6. Aggiornamento Richiesta Cliente → Todo
- **Quando**: Un utente diverso dal developer assegnato modifica il campo `customer_request` e il developer ha ruolo `Customer`
- **Dove**: `StoryObserver::updating()`
- **Comportamento**: Lo stato viene impostato a `Todo` per notificare al developer che c'è una nuova richiesta

### 7. Sincronizzazione Story Figlie
- **Quando**: Cambia lo status di un ticket padre
- **Dove**: `Story::booted()` -> `updated()`
- **Comportamento**: Tutte le story figlie vengono aggiornate allo stesso status del padre

---

## Regole di Validazione

### Validazioni Obbligatorie

1. **Testing richiede Tester**
   - Non è possibile passare a `Testing` senza aver assegnato un `tester_id`
   - Errore: "Impossibile cambiare lo stato a 'Da testare' senza avere assegnato un tester."

2. **Waiting richiede Motivo**
   - Non è possibile passare a `Waiting` senza aver compilato `waiting_reason`
   - Errore: "Impossibile cambiare lo stato a 'In attesa' senza specificare il motivo dell'attesa."

3. **Problem richiede Descrizione**
   - Non è possibile passare a `Problem` senza aver compilato `problem_reason`
   - Errore: "Impossibile cambiare lo stato a 'Problema' senza specificare la descrizione del problema."

### Regole di Business

1. **Solo un Progress per Developer**
   - Un developer può avere solo un ticket in `Progress` alla volta
   - Gli altri vengono automaticamente impostati a `Todo`

2. **Story Figlie non possono avere Figlie**
   - Una story che è già figlia di un'altra non può avere figlie proprie
   - Errore: "Una storia che è figlia non può avere figli."

---

## Diagramma di Flusso

```
                    ┌─────────┐
                    │   New   │
                    └────┬────┘
                         │
            ┌────────────┼────────────┐
            │            │            │
            ▼            ▼            ▼
      ┌─────────┐  ┌─────────┐  ┌─────────┐
      │ Backlog │  │Assigned │  │Rejected│
      └────┬────┘  └────┬────┘  └─────────┘
           │            │
           └──────┬─────┘
                  │
                  ▼
            ┌─────────┐
            │   Todo  │
            └────┬────┘
                 │
                 ▼
          ┌─────────────┐
          │  Progress   │
          └──────┬──────┘
                 │
        ┌────────┼────────┐
        │        │        │
        ▼        ▼        ▼
   ┌────────┐ ┌──────┐ ┌──────┐
   │Testing │ │Problem│ │Waiting│
   └────┬───┘ └───┬──┘ └───┬──┘
        │         │         │
        │         └────┬────┘
        │              │
        ▼              ▼
   ┌─────────┐    ┌─────────┐
   │ Tested  │    │  Todo   │
   └────┬────┘    └─────────┘
        │
        ▼
   ┌──────────┐
   │ Released │
   └────┬─────┘
        │
        │ (automatico dopo 3 giorni)
        │
        ▼
   ┌─────────┐
   │   Done  │
   └─────────┘
```

### Legenda Colori del Diagramma

- 🔵 **Blu**: Stati iniziali (New)
- 🟠 **Arancione**: Stati di lavorazione (Assigned, Todo, Progress, Testing)
- 🟢 **Verde**: Stati di completamento (Tested, Released, Done)
- 🟡 **Giallo**: Stati di attesa (Waiting)
- 🔴 **Rosso**: Stati di blocco/rifiuto (Problem, Rejected)
- ⚪ **Grigio**: Backlog

---

## Azioni Rapide Disponibili in Nova

Nell'interfaccia Nova sono disponibili azioni rapide per cambiare lo stato dei ticket senza dover modificare manualmente il campo status:

### Azioni Inline (disponibili nella vista dettaglio)

1. **Change status to Todo**
   - Disponibile quando lo stato non è già `Todo`
   - Azione: `StoryToTodoStatusAction`
   - Permette di riportare un ticket a `Todo` per riprendere il lavoro

2. **Change status to Progress**
   - Disponibile quando lo stato non è già `Progress`
   - Azione: `StoryToProgressStatusAction`
   - Permette di iniziare immediatamente a lavorare su un ticket

3. **Change status to Test**
   - Disponibile quando lo stato non è già `Testing`
   - Azione: `StoryToTestStatusAction`
   - **Validazione**: Richiede che sia assegnato un `tester_id`
   - Permette di passare il ticket in fase di test

4. **Change status to Done**
   - Disponibile quando lo stato non è già `Done`
   - Azione: `StoryToDoneStatusAction`
   - Permette di chiudere immediatamente un ticket

5. **Change status to Rejected**
   - Disponibile quando lo stato non è già `Rejected`
   - Azione: `StoryToRejectedStatusAction`
   - Permette di rifiutare un ticket

### Azioni Bulk (disponibili nella vista lista)

1. **Move to Backlog**
   - Disponibile quando non si è nella vista di un progetto
   - Azione: `moveToBacklogAction`
   - Permette di spostare più ticket contemporaneamente in `Backlog`

2. **Edit Stories**
   - Azione: `EditStories`
   - Permette di modificare status, user e deadline per più ticket contemporaneamente

### Altre Azioni Utili

1. **Respond to Story Request**
   - Disponibile quando lo stato non è `Done` o `Rejected`
   - Permette di rispondere direttamente alle richieste del cliente

2. **Convert Story to Tag**
   - Disponibile solo nella vista dettaglio
   - Permette di convertire un ticket in un tag per riutilizzo futuro

---

## Best Practices

### Per gli Sviluppatori

1. **Inizia sempre da Todo**: Quando prendi in carico un ticket, assicurati che sia in `Todo` prima di passarlo a `Progress`
2. **Un Progress alla volta**: Ricorda che solo un ticket può essere in `Progress` per te alla volta
3. **Usa Problem per blocchi tecnici**: Se incontri un problema tecnico, passa a `Problem` e descrivi il problema nel campo `problem_reason`
4. **Usa Waiting per attese esterne**: Se devi aspettare informazioni o approvazioni, passa a `Waiting` e specifica il motivo
5. **Assegna sempre un Tester**: Prima di passare a `Testing`, assicurati che sia assegnato un tester

### Per i Tester

1. **Testa solo ticket in Testing**: I ticket in `Testing` sono quelli completati dallo sviluppatore e pronti per essere testati
2. **Passa a Tested solo se OK**: Passa a `Tested` solo se i test sono completati con successo
3. **Rifiuta se necessario**: Se il ticket non è implementato correttamente, puoi rifiutarlo o richiedere modifiche

### Per i Manager/Admin

1. **Assegna ticket da New**: Quando crei un ticket, assegnarlo immediatamente a uno sviluppatore lo porta automaticamente a `Assigned`
2. **Usa Backlog per priorità**: I ticket meno prioritari possono essere messi in `Backlog` per lavorazione futura
3. **Monitora Waiting e Problem**: I ticket in `Waiting` e `Problem` richiedono attenzione e possono bloccare il flusso di lavoro

---

## Note Importanti

1. **Transizioni Automatiche**: Alcune transizioni avvengono automaticamente tramite comandi schedulati. Consulta `docs/AUTOMATIC_STATUS_CHANGES.md` per i dettagli completi.

2. **Story Logs**: Tutte le modifiche di stato vengono registrate in `story_logs` per tracciabilità completa.

3. **Epic Status**: Lo status degli Epic viene aggiornato automaticamente in base agli status delle story figlie.

4. **Notifiche**: Le modifiche di stato generano notifiche email e Nova per gli utenti interessati (developer/tester).

---

**Ultimo aggiornamento**: Gennaio 2025  
**Versione**: MS-1.21.0

