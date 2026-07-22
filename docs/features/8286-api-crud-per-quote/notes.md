> Ticket: oc:8286

# Notes — API CRUD per Quote

## Deviazioni dal piano

- **`name` → `title`**: il piano (e l'overview iniziale) assumevano, in base al `$fillable` del modello `Quote`, che `name` fosse una colonna reale. Verificato con `Schema::getColumnListing('quotes')` durante l'esecuzione del Task 3: la tabella non ha colonna `name`, solo `title`. `name` è un fillable "morto" nel modello (probabilmente residuo storico, non toccato da questo ticket perché fuori scope). L'intera API (Form Request, controller, test) usa `title` come campo obbligatorio in creazione, coerentemente in tutti i punti. `overview.md` è stato corretto a posteriori per riflettere questo.
- **`title` è anch'esso `$translatable`** (oltre a `additional_services`/`notes`, già previsti): non era stato notato in fase di design. L'assegnazione di una stringa a `title` tramite `fill()` viene intercettata da `Spatie\Translatable\HasTranslations` e scritta solo sulla lingua corrente (`config('app.locale')`) automaticamente — comportamento già coerente con la regola "solo lingua di default" decisa per gli altri campi translatable, quindi nessun fix necessario, ma il controller non lo gestisce esplicitamente (si affida al comportamento di default del trait). Nessun bug: verificato con test dedicati (`store_crea_quote`, `update_aggiorna_quote_aperto`) che leggono il valore tramite l'accessor del modello anziché la colonna grezza.
- **`QuotePolicy::viewAny()`/`view()`/`create()`** erano metodi vuoti (mai implementati) e non previsti esplicitamente nel piano come modifiche — scoperti necessari durante il Task 3 perché il fix a `before()` (Task 1) li rende ora realmente valutati: un metodo vuoto ritorna `null`, negato di default. Implementati con `return true;` (il controllo per ruolo resta in `before()`).

## Bug trovati

- **`store` restituiva 403 invece di 201`** dopo il fix a `QuotePolicy::before()`: causato da `create()` vuoto (mai valutato prima del fix). Risolto nello stesso Task 3, prima del commit.
- **Test iniziali su `title` fallivano** perché il valore atteso in `assertDatabaseHas` era la stringa piatta, mentre in DB è salvato come JSON per-locale (`{"it": "..."}"`) essendo `title` translatable. Corretto usando l'accessor (`$quote->fresh()->title`) invece della colonna grezza.

## Decisioni

- **`ProductController`/`RecurringProductController` non riusano `QuotePolicy`**: duplicano `abort_unless(hasRole(...))` invece di passare da una Policy dedicata. Scelta consapevole per scope minimo (solo endpoint `index` read-only, nessuna logica di stato coinvolta) — segnalato dalla review come cleanup, non corretto in questo ciclo.
- **Validazione `quantity` inline (`$request->validate()`) su attach, non un Form Request dedicato**: inconsistenza minore col resto dell'API, accettata per non introdurre due Form Request aggiuntivi per un solo campo.

## Follow-up

- Estrarre il check ruoli duplicato in `ProductController`/`RecurringProductController` in un trait condiviso o in una Policy dedicata, se in futuro la lista ruoli abilitati cambia (rischio di drift tra 3 punti: `QuotePolicy::before()`, `ProductController`, `RecurringProductController`).
- Valutare se `QuotePolicy::restore()`/`forceDelete()` (rimasti stub vuoti, ora sempre negati per effetto del fix a `before()`) vadano implementati esplicitamente se in futuro `Quote` adotta `SoftDeletes` — oggi inerte, nessun impatto verificato.
- Race condition check-then-act su `update`/`delete` (due richieste concorrenti sullo stesso quote potrebbero entrambe superare il controllo di stato prima che una lo chiuda) — rischio accettato per il volume di traffico atteso, non mitigato in questo ciclo.
