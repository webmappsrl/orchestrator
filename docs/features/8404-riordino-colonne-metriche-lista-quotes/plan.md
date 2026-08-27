> Ticket: oc:8404

# Piano di implementazione — Riordino colonne e metriche lista Quotes

Riferimento: `docs/features/8404-riordino-colonne-metriche-lista-quotes/overview.md` (approvato).

Nota: la sub-skill `superpowers:writing-plans` non è installata in questa sessione — questo piano è stato scritto direttamente da `wm-plan`, come per oc:8402.

## Task 1 — Metric-card sulla stessa riga

File: `app/Nova/Quote.php` (`cards()`)

Cambia `(new DynamicPartitionMetric(...))->width('full')` in `->width('1/2')`. `NewQuotes` resta già `->width('1/2')`.

Commit: `feat(oc:8404): put Quotes metric cards on the same row`

## Task 2 — Riordino colonne index e hideFromIndex su Prodotti/Recurring

File: `app/Nova/Quote.php` (`fields()`)

1. Riordina l'array `fields()` in modo che l'ordine dei campi visibili sull'index (quelli senza `onlyOnDetail`/`onlyOnForms`/`hideFromIndex`) risulti: ID, Titolo (vedi Task 3 per il campo index-only), `Status::make('Status')` (badge, già `onlyOnIndex()`), `BelongsTo::make(__('Customer'), ...)`, `BelongsTo::make(__('Owner'), ...)`, campo Scadenza (Task 4), `Currency::make(__('Total'), ...)`, `Text::make('PDF')`. Gli altri campi (Google Drive Url, Template, Discount, Additional Services, IVA, Final Price, Documents, ecc.) restano dov'erano, con la loro visibilità invariata (`hideFromIndex`/`onlyOnDetail`/`onlyOnForms` già presenti).
2. Aggiungi `->hideFromIndex()` a tutti e 4 i campi: `BelongsToMany::make(__('Products'), 'products', ...)`, `BelongsToMany::make('Recurring Products')`, `Currency::make(__('Products'))`, `Currency::make(__('Recurring'), 'recurring')`. Restano invariati su form/dettaglio.

Commit: `feat(oc:8404): reorder Quotes index columns and hide products/recurring from index`

## Task 3 — Titolo a singola lingua sull'index

File: `app/Nova/Quote.php` (`fields()`)

1. Aggiungi `->hideFromIndex()` al blocco esistente `NovaTabTranslatable::make([Text::make(__('Title'), 'title')...])->setTitle(__('Title'))` — resta invariato su form/dettaglio (continua a mostrare/editare tutte le lingue via tab).
2. Aggiungi un nuovo campo separato, posizionato subito dopo l'ID nell'ordine dell'index (vedi Task 2):
   ```php
   Text::make(__('Title'), function () {
       $wrappedName = wordwrap($this->title, 50, "\n", true);
       return str_replace("\n", '<br>', $wrappedName);
   })->asHtml()->onlyOnIndex(),
   ```
   `$this->title` usa l'accessor Spatie (`HasTranslations`), locale-aware con fallback automatico su `app()->getLocale()` — stesso wordwrap/HTML del campo originale per coerenza visiva, nessuna nuova logica di formattazione da inventare.
3. **Non toccare** `Quote::title()` nel model (usato per il display-title della risorsa, `$this->name ?: $this->title` — resta un terzo punto di risoluzione indipendente, già noto e documentato in overview.md).

Commit: `feat(oc:8404): show single-locale title on Quotes index`

## Task 4 — Nuova colonna Scadenza

File: `app/Nova/Quote.php` (`indexQuery()` e `fields()`)

1. In `indexQuery()`, aggiungi l'eager loading filtrato e ordinato, che alimenta sia il calcolo della colonna sia previene N+1:
   ```php
   public static function indexQuery(NovaRequest $request, $query)
   {
       $whereNotIn = [
           QuoteStatus::Closed_Won->value,
           QuoteStatus::Closed_Lost->value,
       ];
       return $query
           ->whereNotIn('status', $whereNotIn)
           ->with(['tasks' => function ($q) {
               $q->where('status', \App\Models\Task::STATUS_TODO)->orderBy('due_date', 'asc');
           }]);
   }
   ```
2. Aggiungi il nuovo campo in `fields()`, posizionato prima di `Currency::make(__('Total'), ...)` (vedi ordine Task 2):
   ```php
   Text::make(__('Due date'), function () {
       $nextTask = $this->tasks->first();
       return $nextTask ? $nextTask->due_date->format('d/m/Y') : '—';
   })->onlyOnIndex(),
   ```
   `$this->tasks` usa la collection già eager-caricata e filtrata/ordinata da `indexQuery()` — `->first()` non genera query aggiuntiva. Codice difensivo: `$nextTask` è null-safe, nessuna eccezione se la Quote non ha task todo collegati.
3. **Non toccare** la definizione della relazione `HasMany::make(__('Tasks'), 'tasks', \App\Nova\Task::class)` nel sub-panel (Task 6 del piano oc:8402 già garantisce che il sub-panel Task dentro Quote resti invariato — il sub-panel fa una query Nova relatable separata, non riusa la eager-load di `indexQuery()`).

Commit: `feat(oc:8404): add computed due date column to Quotes index`

## Task 5 — Test

File: `tests/Feature/QuoteNovaResourceTest.php` (nuovo)

Segui lo stesso pattern di `tests/Feature/TaskNovaResourceTest.php` (oc:8402): `NovaRequest::create('/')` (+ `setUserResolver` dove serve un utente), chiamando i metodi statici/istanza della risorsa Nova direttamente, senza instradare l'HTTP reale.

Casi da coprire:
1. **`Quote::cards()`** — entrambe le card hanno `width` `1/2` (verifica sulla proprietà pubblica esposta dalla card, o sull'array risultante da `jsonSerialize()`/`meta()` a seconda di cosa espone la classe base Nova).
2. **Ordine campi index** — `fields()` con `NovaRequest::create('/')`: verifica che i field visibili sull'index (quelli senza `onlyOnDetail`/`onlyOnForms`/`hideFromIndex`) risultino nell'ordine Cliente, Titolo, Stato, Proprietario, Scadenza, Totale, PDF.
3. **Titolo singola lingua** — crea una Quote con traduzioni multiple (`setTranslation('title', 'it', '...')`, `setTranslation('title', 'en', '...')`), verifica che il nuovo campo index-only ritorni solo la stringa nella locale attiva (`app()->setLocale('en')` poi verifica).
4. **Prodotti/Recurring nascosti dall'index** — verifica `hideFromIndex` sui 4 field interessati (property `showOnIndex` a `false`).
5. **Colonna Scadenza — task todo futuro**: Quote con un task todo con `due_date` futura → colonna mostra quella data.
6. **Colonna Scadenza — task todo scaduto**: Quote con un task todo con `due_date` passata (nessun altro task) → colonna mostra comunque quella data (non `—`) — copre esplicitamente la correzione fatta in Fase: challenge.
7. **Colonna Scadenza — più task todo**: Quote con 2 task todo, uno più vicino nel tempo dell'altro (indipendentemente da passato/futuro) → colonna mostra la `due_date` più vicina in assoluto.
8. **Colonna Scadenza — nessun task todo collegato** (nessun task, o solo task completati): colonna mostra `—`.
9. **`indexQuery()` eager loading**: verifica che l'accesso a `$quote->tasks` dopo `Quote::indexQuery(...)->get()` non generi query aggiuntive (es. con `DB::enableQueryLog()` prima/dopo, o asserzione sul numero di query totali per N Quote con task).

Commit: `test(oc:8404): cover Quotes index reorder, single-locale title, due date column`

## Task 6 — Verifica manuale

1. `php artisan test --filter=QuoteNovaResourceTest` e le suite Quote esistenti (`grep -rl "class.*Quote" tests/Feature/` per identificarle) — verifica nessuna regressione.
2. Da Nova UI: apri Resources → Quotes, verifica le due metric-card sulla stessa riga, ordine colonne, Titolo in una sola lingua (cambia lingua utente per controllare che segua), colonne Prodotti/Recurring assenti dall'index ma presenti nel dettaglio, colonna Scadenza popolata correttamente (inclusi casi con task scaduti).
3. Apri il form di modifica di una Quote: verifica che il tab multilingua del Titolo mostri ancora tutte le lingue ed sia editabile normalmente.
4. Apri il dettaglio di una Quote con task collegati: verifica che il sub-panel Task sia invariato.

Nessun commit associato a questo task (solo verifica).
