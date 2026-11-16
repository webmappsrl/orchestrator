# Attività delle trattative: registrazione e API

## Come funziona oggi

- **Ogni azione di una persona su una Quote, sui suoi prodotti o sui suoi Task diventa una riga di
  `quote_activities`** (modello `QuoteActivity`), con chi l'ha fatta, quando e cosa è cambiato
  (`changes`, la cui forma dipende da `event`). Le righe le scrivono `QuoteObserver` e
  `TaskObserver`, registrati in `AppServiceProvider::boot()`, e i pivot custom `ProductQuote` e
  `QuoteRecurringProduct`, che `Quote::products()` e `Quote::recurringProducts()` usano con
  `->using(...)` (oc:8724).
- **Le attività esistono dal rilascio di oc:8724**: prima non c'era nessun tracciamento, e i giorni
  precedenti restituiscono un elenco vuoto.
- **`GET /api/quote-activities`** (`QuoteActivityController::index`) restituisce le attività
  raggruppate per Quote. I giorni seguono il calendario di `config('app.timezone')`, cioè
  Europe/Rome. Le Quote già chiuse prima dell'inizio del periodo sono escluse: lo stato a inizio
  periodo si ricostruisce dalle attività, e una Quote chiusa senza cambi di stato registrati conta
  come chiusa da prima.
- **Le righe sopravvivono alle cancellazioni**: le FK sono `nullOnDelete` e ogni riga conserva una
  copia di `quote_title`, `task_title` e `user_name`. Le Quote cancellate si raggruppano per
  titolo copiato.
- **Una scrittura fallita in `quote_activities` non deve far fallire il salvataggio**:
  `QuoteActivity::record()` è in un `try/catch` che chiama `report()`.

**Cosa non passa dagli observer, e quindi non diventa un'attività:** `saveQuietly()`, gli update
in massa (`->update()` su una query) e le query SQL dirette, `detach()` senza id espliciti, il
cambio di `quantity` su un prodotto già presente (i pivot ascoltano solo `created` e `deleted`), la
cancellazione a cascata dei Task quando si cancella una Quote (`onDelete('cascade')` nel DB), e i
campi in `IGNORED_FIELDS` dei due observer. Chi aggiunge un punto di scrittura su Quote, Task o
prodotti deve passare da Eloquent, altrimenti l'API non lo vede.

## Perché così

- **Tabella dedicata invece di ricavare le attività da `tasks` e `quotes`** (oc:8724): i timestamp
  esistenti non dicono quale campo è cambiato né chi l'ha cambiato, che è quanto il ticket chiede.
- **Observer invece di un override di `save()`** (oc:8724): le scritture arrivano da Nova, dall'API,
  dal Kanban e dalle action, e l'observer le intercetta tutte senza toccare i singoli chiamanti.
- **Pivot custom sui prodotti** (oc:8724): `attach`/`detach`/`sync` su una `belongsToMany` normale
  non lanciano eventi Eloquent; con `->using(...)` passano da un modello che li lancia.
- **Per le note dei Task si salva solo il testo aggiunto** (oc:8724): `Task::appendNote` mette la
  nota nuova in cima a `notes`, che contiene tutta la storia. `TaskObserver::addedNote()` toglie il
  testo vecchio, l'HTML e l'intestazione «<nome> ha aggiunto una nota il: <data>», perché autore e
  ora sono già nella riga.
- **`priority` è ignorata** (oc:8724): spostando una Card di colonna nel Kanban Sales,
  `KanbanController::updateStatus()` la ricalcola insieme allo stato, e non è una modifica decisa
  dalla persona.

## Come ci siamo arrivati

- **Nessuna view Nova delle attività** (oc:8724): la chiedeva il ticket, è uscita dallo scope ed è
  prevista in un ticket a sé.
