> Ticket: oc:8724

# Trattative: vista e API delle attività del giorno precedente

## Cosa cambia

Orchestrator inizia a registrare le **attività delle trattative** (tabella `quote_activities`). Ogni volta che una persona crea, modifica
o chiude qualcosa su una trattativa (Quote) o su uno dei suoi task, viene scritta in automatico una
riga che dice chi l'ha fatto, quando e cosa è cambiato (valore prima e dopo).

Sulle attività registrate si appoggia un **endpoint API** che, dato un giorno di partenza e un numero di giorni,
restituisce le trattative che in quel periodo hanno avuto attività, ognuna con le sue attività
nel campo `activities`. Senza parametri restituisce le attività di oggi.

La view Nova chiesta dal ticket esce da questo ciclo (vedi Out of scope): nello scrum dell'11/11/2026
Giuseppe Bonfanti dice «concentrati solo sul fare le PI e la parte della nuova di di Nova staccala».

Esempio di cosa restituisce l'API il martedì mattina con `from` uguale a lunedì:

> **Preventivo Comune di X** (presentato)
> - 10:14, Alessio, ha creato il task «Richiamare il cliente»
> - 11:30, Giuseppe, stato: nuovo → presentato
> - 12:02, Giuseppe, ha aggiunto il prodotto «App Android»

Le attività si registrano da quando la feature va in produzione: le attività precedenti non sono
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
per iscritto le domande aperte. Lo scrum dell'11/11/2026 le chiude: le decisioni sono riportate nei
requisiti con la loro citazione.

L'API la usa la skill Claude: «Detto ciò, la usa la skill di cloud, ok?» (scrum 11/11/2026, Giuseppe
Bonfanti).

## Requisiti

Le decisioni senza fonte nel ticket o negli scrum sono state prese nel dialogo `wm-plan` del
03/11/2026 con Carla Cupani.

**Attività**

- [ ] Una tabella dedicata (`quote_activities`), costruita sul modello di `story_logs`. Ogni riga
      contiene: trattativa, task (se l'attività riguarda un task), utente, tipo di evento, valori
      prima e dopo, data e ora. Non si usa un pacchetto di activity log.
- [ ] Ogni riga conserva il titolo del task al momento dell'evento, così la riga resta leggibile
      anche se il task viene cancellato in seguito.
- [ ] **Trattativa**: si registrano la creazione e la modifica di ogni campo che una persona cambia a
      mano: titolo, nome, stato, cliente, servizi aggiuntivi, sconto, note, link Google Drive,
      motivo di perdita, contatti del cliente. Nei campi tradotti contano solo le lingue con un
      valore: le lingue vuote che Nova aggiunge salvando (`null`, `''` o `[]`) non sono una modifica.
      Nemmeno gli a capo che il browser riscrive come `\r\n` in un testo scritto via API con `\n`.
- [ ] **Prodotti**: si registrano l'aggiunta e la rimozione dei prodotti e dei prodotti ricorrenti
      della trattativa, da qualunque punto avvengano (API e Nova).
- [ ] Restano fuori i campi scritti dal sistema: `pdf_links`, `template`, `priority` (l'ordine
      delle Card nel Kanban Sales), `created_at`, `updated_at`.
- [ ] **Note della trattativa**: il campo `notes` resta com'è. La sua modifica si registra come
      qualunque altro campo, con il testo prima e dopo.
- [ ] **Task**: si registrano creazione, chiusura, riapertura, cancellazione e la modifica di ogni
      campo (titolo, note, scadenza, assegnatario).
- [ ] **Note dei task**: le note si aggiungono in cima al campo `notes` (`Task::appendNote`), che
      contiene quindi tutta la storia del task. Quando il nuovo testo è il vecchio con una nota in
      più in cima, l'attività salva **solo la nota aggiunta** («Giuseppe ha aggiunto la nota: …»).
      Se il campo viene riscritto a mano (testo vecchio corretto o tolto), si registra prima e dopo
      come per gli altri campi.
- [ ] **Cancellazioni**: le righe di `quote_activities` non spariscono quando si cancella ciò a cui si
      riferiscono. Ogni riga conserva una copia del titolo della trattativa, del titolo del task e
      del nome dell'utente; se trattativa, task o utente vengono cancellati, il collegamento si
      svuota e la riga resta leggibile con quei nomi.
- [ ] **Trattativa cancellata**: la cancellazione si registra come evento («Alessio ha cancellato
      la trattativa»). La trattativa cancellata compare nell'API con il titolo copiato,
      insieme alle attività precedenti alla cancellazione.
- [ ] Un'azione fatta senza utente autenticato (job, comando) si registra con utente vuoto, e nella
      lettura appare come «Sistema».

**API**

- [ ] Nuovo endpoint `GET` sotto `auth:sanctum`. Non esiste un endpoint di attività da estendere:
      prima di questo lavoro, in `routes/api.php` c'erano solo le rotte CRUD di `/quotes` e `/tasks`.
- [ ] **La data è un parametro, non è fissata nel codice.** Fonte: scrum del 04/11/2026, Giuseppe
      Bonfanti: «Gli si passa una data e dà i dati in base a quella data. […] Cioè la data diventa
      una variabile».
- [ ] Parametri, entrambi opzionali:
      - `from` (`AAAA-MM-GG`, stesso formato di `due_from`/`due_to` dei task): il primo giorno;
        senza `from` si usa **oggi**;
      - `days` (intero, minimo 1, default 1): quanti giorni a partire da `from`, `from` compreso.

      Esempi: nessun parametro → le attività di oggi; `from=2026-11-10` → solo il 10 novembre;
      `from=2026-11-10&days=5` → dal 10 al 14 novembre. Formato di `from` errato o `days` minore di 1
      → 422. Non c'è un parametro `to`, quindi non esiste il caso di un intervallo invertito.
      Fonte: scrum dell'11/11/2026, Giuseppe Bonfanti: «Se invece prendete una data e un range,
      questo problema non c'è», «lasciate from in realtà va bene e di default va a uno», «il from
      diventa opzionale e se non lo metti prende il giorno corrente».
- [ ] I giorni si calcolano sull'ora italiana (`Europe/Rome`): una modifica delle 00:30 appartiene al
      giorno in cui è avvenuta in Italia.
- [ ] Compaiono le trattative con almeno un'attività nell'intervallo, **tranne quelle che erano già
      chiuse** (`closed won` o `closed lost`) **prima dell'inizio dell'intervallo**. Lo stato si
      valuta su quel periodo, non su oggi: una trattativa chiusa stamattina compare nel riepilogo
      di ieri, e lo stesso riepilogo riletto una settimana dopo dà lo stesso risultato. La data di
      chiusura si ricava dal cambio di stato registrato fra le attività; una trattativa chiusa senza
      cambio di stato fra le attività (chiusa prima del rilascio) si considera chiusa da prima.
- [ ] La risposta è raggruppata per trattativa e si legge da sola: per ogni trattativa id, titolo,
      stato e cliente; per ogni attività data e ora, nome dell'utente, tipo di evento fisso, task
      coinvolto (id e titolo) e campi cambiati con valore prima e dopo.
- [ ] Autorizzazione uguale a quella delle API trattative esistenti (`QuotePolicy::viewAny`).
- [ ] Endpoint documentato in Scramble come gli altri endpoint Quote.

**Testi**

- [ ] Ogni testo restituito dall'API che non sia un dato (per esempio «Sistema» al posto
      dell'utente vuoto) ha la chiave in inglese con traduzione in `lang/it.json` (lingua di
      default `it`) e la stessa chiave in `lang/en.json`.

**Verifica**

- [ ] Test sul DB `orchestrator_test` che coprono: registrazione di ogni tipo di evento,
      esclusione dei campi tecnici, filtro aperte più chiuse nell'intervallo, default di `from`
      (oggi) e di `days` (1), intervallo di più giorni, 422 sui parametri errati, confine di
      mezzanotte sull'ora italiana, autorizzazione dell'endpoint.

## Rischi

- **Scritture che non passano dagli eventi del modello.** Un sistema basato sugli eventi Eloquent
  non vede: `QuotePdfLinks` (update SQL diretto su `pdf_links`), il declassamento dei template
  fratelli (`->update()` in `Quote::booted`), la riassegnazione dei task con `saveQuietly()` quando
  si cancella un utente, la cancellazione a cascata dei task quando si cancella una trattativa.
  *Mitigazione*: i primi due riguardano campi tecnici esclusi per scelta; gli altri due sono effetti
  automatici, non azioni di una persona, e non vengono registrati fra le attività (vedi Out of scope).
- **Prodotti.** Aggiungere o togliere un prodotto non modifica la trattativa, quindi non scatta
  nessun evento sulla Quote. *Mitigazione*: la registrazione va agganciata alla relazione con i
  prodotti, in modo che valga per ogni punto di scrittura (API, Nova, `DuplicateQuote`). Il piano
  deve verificarlo con un test per ciascun punto.
- **Campi tradotti.** `title` e `notes` sono salvati in JSON con una versione per lingua. Il prima e
  dopo va registrato in modo che resti leggibile, non come JSON grezzo.
- **Fuso orario.** L'app è in `Europe/Rome`, il server del database in UTC. Prima di costruire i
  filtri per giorno va verificato come sono salvati davvero i timestamp, altrimenti le attività
  vicino alla mezzanotte finiscono nel giorno sbagliato.
- **Volume di `quote_activities`.** La modifica delle note salva due volte un testo che può essere lungo.
  *Mitigazione*: la tabella è indicizzata per data e trattativa; il volume reale di produzione non è
  noto (in locale ci sono 123 trattative e 2 task).
- **Contratto dell'API.** La forma della risposta, una volta usata da un client esterno, non si
  cambia senza romperlo, e la skill Claude che la userà vive fuori da questo repo. *Mitigazione*:
  risposta autodescrittiva e tipi di evento fissi.

## Out of scope

- **La view Nova delle attività.** Va in un ticket a sé, dopo aver chiesto ad Alessio Piccioli allo
  scrum di lunedì 16/11/2026 se è una view nuova o la modifica di una esistente, e in quale `MenuSection`.
  Fonte: scrum dell'11/11/2026, Giuseppe Bonfanti: «si chiede ad Alessio se è una view completamente
  nuova o se vuole modificata una view già esistente»; «La vista è una cosa in più, quindi
  dividiamola».
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
- `app/Observers/QuoteObserver.php`, `app/Observers/TaskObserver.php` — registrazione delle attività
  di Quote e Task, registrati in `app/Providers/AppServiceProvider.php`
- `app/Models/ProductQuote.php`, `app/Models/QuoteRecurringProduct.php` — pivot custom che
  registrano aggiunta e rimozione dei prodotti; `app/Models/Quote.php` li usa con `->using(...)` e
  ha la relazione `activities()`
- `app/Http/Controllers/Api/QuoteActivityController.php` — nuovo endpoint attività
- `routes/api.php` — nuova rotta
- `tests/Feature/` — test della registrazione delle attività e dell'API
