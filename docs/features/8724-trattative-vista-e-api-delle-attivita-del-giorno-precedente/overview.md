> Ticket: oc:8724

# Trattative: vista e API delle attività del giorno precedente

## Cosa cambia

Orchestrator inizia a tenere un **diario delle trattative**. Ogni volta che una persona crea, modifica
o chiude qualcosa su una trattativa (Quote) o su uno dei suoi task, viene scritta in automatico una
riga che dice chi l'ha fatto, quando e cosa è cambiato (valore prima e dopo).

Sul diario si appoggiano due modi di leggerlo:

- un **endpoint API** che, dato un intervallo di date, restituisce le trattative che in quel periodo
  hanno avuto attività, ognuna con l'elenco delle sue attività;
- una **vista Nova** nella sezione CRM che mostra la stessa informazione e si apre sul giorno
  lavorativo precedente.

Esempio di cosa si legge il martedì mattina:

> **Preventivo Comune di X** (presentato)
> - 10:14, Alessio, ha creato il task «Richiamare il cliente»
> - 11:30, Giuseppe, stato: nuovo → presentato
> - 12:02, Giuseppe, ha aggiunto il prodotto «App Android»

Il diario si riempie da quando la feature va in produzione: le attività precedenti non sono
ricostruibili.

## Perché

Per aprire la giornata e allinearsi serve sapere cosa è successo sulle trattative il giorno prima,
senza aprirle una per una. Fonte (ticket, citazione della call del 30/09/2026): «Per ogni trattativa
mi piacerebbe vedere quali task sono stati creati, chiusi, quali note sono state aggiunte alla
trattativa o quali modifiche sono state fatte alla trattativa»; «anche API come priorità».

Oggi non è possibile: Orchestrator conserva solo lo stato attuale di trattative e task, e uno
storico delle modifiche esiste solo per le Story (`story_logs`). Nelle note del ticket: in call è
stato detto che «non tracciamo gli avvenimenti», quindi prima va introdotto il tracciamento.

Lo scrum del 03/11/2026 (08:31, Giuseppe Bonfanti) assegna il ticket a Carla e chiede di raccogliere
per iscritto le domande aperte.

## Requisiti

Le decisioni senza fonte nel ticket sono state prese nel dialogo `wm-plan` del 03/11/2026 con Carla
Cupani.

**Diario**

- [ ] Una tabella dedicata (`quote_activities`), costruita sul modello di `story_logs`. Ogni riga
      contiene: trattativa, task (se l'attività riguarda un task), utente, tipo di evento, valori
      prima e dopo, data e ora. Non si usa un pacchetto di activity log.
- [ ] Ogni riga conserva il titolo del task al momento dell'evento, così la riga resta leggibile
      anche se il task viene cancellato in seguito.
- [ ] **Trattativa**: si registrano la creazione e la modifica di ogni campo che una persona cambia a
      mano: titolo, nome, stato, priorità, cliente, servizi aggiuntivi, sconto, note, link Google
      Drive, motivo di perdita, contatti del cliente.
- [ ] **Prodotti**: si registrano l'aggiunta e la rimozione dei prodotti e dei prodotti ricorrenti
      della trattativa, da qualunque punto avvengano (API, Nova, duplicazione).
- [ ] Restano fuori i campi scritti dal sistema: `pdf_links`, `template`, `created_at`, `updated_at`.
- [ ] **Note della trattativa**: il campo `notes` resta com'è. La sua modifica si registra come
      qualunque altro campo, con il testo prima e dopo.
- [ ] **Task**: si registrano creazione, chiusura, riapertura, cancellazione e la modifica di ogni
      campo (titolo, note, scadenza, assegnatario).
- [ ] **Note dei task**: le note si aggiungono in cima al campo `notes` (`Task::appendNote`), che
      contiene quindi tutta la storia del task. Quando il nuovo testo è il vecchio con una nota in
      più in cima, il diario salva **solo la nota aggiunta** («Giuseppe ha aggiunto la nota: …»).
      Se il campo viene riscritto a mano (testo vecchio corretto o tolto), si registra prima e dopo
      come per gli altri campi.
- [ ] **Cancellazioni**: le righe del diario non spariscono quando si cancella ciò a cui si
      riferiscono. Ogni riga conserva una copia del titolo della trattativa, del titolo del task e
      del nome dell'utente; se trattativa, task o utente vengono cancellati, il collegamento si
      svuota e la riga resta leggibile con quei nomi.
- [ ] **Trattativa cancellata**: la cancellazione si registra come evento («Alessio ha cancellato
      la trattativa»). La trattativa cancellata compare nell'API e nella vista con il titolo
      copiato, senza link, insieme alle attività precedenti alla cancellazione.
- [ ] Un'azione fatta senza utente autenticato (job, comando) si registra con utente vuoto, e nella
      lettura appare come «Sistema».

**API**

- [ ] Nuovo endpoint `GET` sotto `auth:sanctum`, con parametri opzionali `from` e `to`
      (`AAAA-MM-GG`, stesso formato di `due_from`/`due_to` dei task; 422 se il formato è errato).
- [ ] **La data è un parametro, non è fissata nel codice.** Fonte: scrum del 04/11/2026, Giuseppe
      Bonfanti: «Ovvio che […] non è che dobbiamo arcodare il giorno prima. Gli si passa una data e
      dà i dati in base a quella data. […] Cioè la data diventa una variabile», «perché se per
      qualsiasi motivo non ci serve il giorno prima, ma ci serve 10 giorni prima con una data
      arcodata è un po' un casino». Per un giorno solo si passa `from` uguale a `to`.
- [ ] Senza date si usa il **giorno lavorativo precedente**: martedì–venerdì il giorno prima, lunedì
      da venerdì a domenica. Le festività non sono gestite: il giorno dopo una festa la data si passa
      come parametro (o si cambia dal filtro nella vista).
- [ ] I giorni si calcolano sull'ora italiana (`Europe/Rome`): una modifica delle 00:30 appartiene al
      giorno in cui è avvenuta in Italia.
- [ ] Compaiono le trattative con almeno un'attività nell'intervallo, **tranne quelle che erano già
      chiuse** (`closed won` o `closed lost`) **prima dell'inizio dell'intervallo**. Lo stato si
      valuta su quel periodo, non su oggi: una trattativa chiusa stamattina compare nel riepilogo
      di ieri, e lo stesso riepilogo riletto una settimana dopo dà lo stesso risultato. La data di
      chiusura si ricava dal cambio di stato registrato nel diario; una trattativa chiusa senza
      cambio di stato nel diario (chiusa prima del rilascio) si considera chiusa da prima.
- [ ] La risposta è raggruppata per trattativa e si legge da sola: per ogni trattativa id, titolo,
      stato e cliente; per ogni attività data e ora, nome dell'utente, tipo di evento fisso, task
      coinvolto (id e titolo) e campi cambiati con valore prima e dopo.
- [ ] Autorizzazione uguale a quella delle API trattative esistenti (`QuotePolicy::viewAny`).
- [ ] Endpoint documentato in Scramble come gli altri endpoint Quote.

**Vista Nova**

- [ ] Vista con componenti standard di Nova, nessun componente custom, nella sezione **CRM**: la
      vedono quindi Admin e Manager, come il resto della sezione.
- [ ] Le liste standard di Nova mostrano una riga per record e non raggruppano: la vista è una
      lista con una riga per attività (trattativa, ora, utente, cosa è successo), ordinata per
      trattativa e poi per ora, con il nome della trattativa come link. Il raggruppamento vero
      resta nell'API.
- [ ] Si apre sul giorno lavorativo precedente e ha un filtro per cambiare data.
- [ ] Mostra le stesse trattative e attività dell'API, con le stesse regole di filtro.

**Testi**

- [ ] Ogni testo visibile ha la chiave in inglese con traduzione in `lang/it.json` (lingua di default
      `it`) e voce in `lang/en.json`.

**Verifica**

- [ ] Test sul DB `orchestrator_test` che coprono: registrazione di ogni tipo di evento,
      esclusione dei campi tecnici, filtro aperte più chiuse nell'intervallo, giorno lavorativo
      precedente il lunedì, confine di mezzanotte sull'ora italiana, autorizzazione dell'endpoint.

## Rischi

- **Scritture che non passano dagli eventi del modello.** Un sistema basato sugli eventi Eloquent
  non vede: `QuotePdfLinks` (update SQL diretto su `pdf_links`), il declassamento dei template
  fratelli (`->update()` in `Quote::booted`), la riassegnazione dei task con `saveQuietly()` quando
  si cancella un utente, la cancellazione a cascata dei task quando si cancella una trattativa.
  *Mitigazione*: i primi due riguardano campi tecnici esclusi per scelta; gli altri due sono effetti
  automatici, non azioni di una persona, e restano fuori dal diario (vedi Out of scope).
- **Prodotti.** Aggiungere o togliere un prodotto non modifica la trattativa, quindi non scatta
  nessun evento sulla Quote. *Mitigazione*: la registrazione va agganciata alla relazione con i
  prodotti, in modo che valga per ogni punto di scrittura (API, Nova, `DuplicateQuote`). Il piano
  deve verificarlo con un test per ciascun punto.
- **Campi tradotti.** `title` e `notes` sono salvati in JSON con una versione per lingua. Il prima e
  dopo va registrato in modo che resti leggibile, non come JSON grezzo.
- **Fuso orario.** L'app è in `Europe/Rome`, il server del database in UTC. Prima di costruire i
  filtri per giorno va verificato come sono salvati davvero i timestamp, altrimenti le attività
  vicino alla mezzanotte finiscono nel giorno sbagliato.
- **Volume del diario.** La modifica delle note salva due volte un testo che può essere lungo.
  *Mitigazione*: la tabella è indicizzata per data e trattativa; il volume reale di produzione non è
  noto (in locale ci sono 123 trattative e 2 task).
- **Contratto dell'API.** La forma della risposta, una volta usata da un client esterno, non si
  cambia senza romperlo. Chi la userà non è ancora noto (domanda aperta per Alessio). *Mitigazione*:
  risposta autodescrittiva e tipi di evento fissi, adatta sia a una skill Claude sia a uno script.

## Domande aperte per il reviewer

Nello scrum del 04/11/2026 Giuseppe Bonfanti ha chiesto ad Alessandro Peci di rispondere alle
domande del ticket («Alessandro, prova a rispondergli tu»). Il piano e il codice partono dopo le
risposte. Ci sono quattro domande:

1. **Chi userà l'API, e in quale momento?** Per esempio una skill Claude, uno strumento di Alessio o
   altro. L'API l'ha chiesta Alessio come priorità nella call del 30/09/2026. Dalla risposta dipende la
   forma della risposta JSON, che una volta usata da un client esterno non si cambia senza romperlo.
   - Risposta:

2. **Che data si passa all'API?** Nello scrum Giuseppe dice: «i risultati li deve dare traslati di
   un giorno […] Gli si passa una data e dà i dati in base a quella data». Le letture possibili sono
   due:
   - **A)** quella scritta in questa overview: si passa il giorno (o l'intervallo `from`/`to`) di
     cui si vogliono le attività. Per esempio `from=2026-11-03&to=2026-11-03` restituisce le
     attività del 3 novembre. Senza parametri si usa il giorno lavorativo precedente;
   - **B)** si passa la data di oggi e si ricevono le attività del giorno prima. Per esempio
     `date=2026-11-04` restituisce le attività del 3 novembre.
   - Risposta:

3. **Vista Nova: lista standard o vista raggruppata?** Le liste standard di Nova mostrano una riga
   per record. Per questo la vista è una lista con una riga per attività, ordinata per trattativa,
   come descritto nei requisiti. Per avere le attività raggruppate sotto ogni trattativa, come
   nell'esempio in cima alla pagina, serve un componente custom. Questo vuol dire più lavoro, e va
   contro la scelta dei componenti standard fatta nel dialogo del 03/11/2026.
   - Risposta:

4. **Prodotti: cambio di quantità e duplicazione.** Il ticket chiede «quali modifiche sono state
   fatte alla trattativa», ma non nomina i prodotti. Nel dialogo del 03/11/2026 abbiamo deciso di
   registrare l'aggiunta e la rimozione dei prodotti. Restano due casi che il codice permette:
   - **cambio di quantità**: l'API che aggiunge un prodotto già presente ne cambia solo la quantità
     (`app/Http/Controllers/Api/QuoteController.php:300`, e `:332` per i ricorrenti). Per esempio
     «App Android» passa da 1 a 3 e il prezzo del preventivo cambia. Va registrato («Giuseppe, App
     Android: quantità 1 → 3»)?
   - **duplicazione**: l'azione Nova «Duplica» copia tutti i prodotti nella trattativa nuova
     (`app/Nova/Actions/DuplicateQuote.php:41` e `:47`). Duplicando un preventivo con 8 prodotti, il
     riepilogo deve mostrare una riga sola («Alessio ha creato la trattativa duplicando il Preventivo
     Comune di X») oppure «creata» più 8 righe «ha aggiunto il prodotto…»?
   - Risposta:

## Out of scope

- Trasformare le note della trattativa in elementi separati, con autore e data per ciascuna: se
  servirà, in un ticket a sé.
- Ricostruire le attività precedenti al rilascio.
- Registrare gli effetti automatici che non sono azioni di una persona: riassegnazione dei task alla
  cancellazione di un utente, task cancellati a cascata con la loro trattativa, generazione dei PDF.
- Notifiche (mail, Slack) sulle attività: qui si legge soltanto.

## Moduli toccati

Tutto nel repo principale `orchestrator`. Nessun submodule coinvolto.

- `database/migrations/` — nuova tabella `quote_activities`
- `app/Models/QuoteActivity.php` — nuovo modello
- `app/Models/Quote.php`, `app/Models/Task.php` — registrazione delle attività (observer o hook) e
  relazione verso il diario
- relazione Quote ↔ prodotti / prodotti ricorrenti — registrazione di aggiunta e rimozione
- `app/Http/Controllers/Api/` — nuovo endpoint attività
- `routes/api.php` — nuova rotta
- `app/Nova/` — vista attività nella sezione CRM, `app/Providers/NovaServiceProvider.php` per il menu
- `lang/it.json`, `lang/en.json` — testi
- `tests/Feature/` — test di diario, API e vista
