# Quote: API, PDF e viste Nova

Come si leggono e si scrivono i preventivi via API, come si genera il PDF e com'è
organizzata la Resource Nova.

## Stato attuale

### API Quote (oc:8286, oc:8291)
- `GET/POST/PATCH/DELETE /api/quotes`, attach/detach di `products` e `recurring-products`
  con `quantity`, liste read-only `/api/products` e `/api/recurring-products`. Accesso
  Admin/Manager/Developer.
- **Attach/detach è upsert, non idempotente puro**: un secondo `POST` sulla stessa coppia
  aggiorna la `quantity` (`syncWithoutDetaching` con array pivot) invece di ignorarla.
- **`name` è un fillable morto sul modello `Quote`**: `$fillable` lo include ma la colonna
  non esiste (c'è solo `title`). L'API usa `title`. Non corretto sul modello.
- **`title` è `$translatable`**: una stringa assegnata via `fill()` viene scritta solo sulla
  lingua corrente — coerente con la regola "solo lingua di default", nessuna gestione
  esplicita nel controller.
- **`QuotePolicy::before()` era codice morto** per `update()`/`delete()`: ritornava sempre un
  booleano netto, quindi short-circuitava la valutazione e il blocco su
  `closed_won`/`closed_lost` non veniva mai eseguito, né da Nova né dall'API. Ora nega subito
  i ruoli non autorizzati e ritorna `null` per quelli abilitati; `viewAny()`/`view()`/
  `create()` restituiscono `true` esplicitamente (prima erano vuoti e `create()` negava
  sempre, 403 su ogni `POST`).
- **`ProductController`/`RecurringProductController` non riusano `QuotePolicy`**: duplicano il
  check ruoli invece di passare da Gate — accettato per scope minimo (solo `index`
  read-only), da correggere se la lista ruoli cambia.
- **`company_name` nell'API Customer non è una colonna**: alias di sola lettura su
  `full_name`, calcolato nel controller.

### PDF del preventivo (oc:8291, oc:8413, oc:8047)
- `GET/POST /api/quotes/{quote}/pdf(-link)` più rotta pubblica firmata in `routes/web.php`
  (`quotes.pdf.public`), middleware `['signed', 'throttle:30,1']`. Il throttle serve perché la
  rotta vive fuori da `auth:sanctum` e quindi fuori dal `throttle:api`.
  **`->missing(fn () => abort(403))` è obbligatorio**: `SubstituteBindings` risolve il binding
  prima che `signed` verifichi la firma, quindi senza di esso un id inesistente darebbe 404 e
  una firma invalida su un id esistente 403 — permettendo a un anonimo di enumerare gli id.
- **`expires_in_days`**: `integer|min:1|max:90`, default 30. **Non esiste revoca del singolo
  link** prima della scadenza: solo la rotazione di `APP_KEY`, che invalida tutti i link
  firmati del progetto.
- **`QuotePdfService::stream(Quote $quote, string $lang, bool $persist = true)`** è condiviso
  fra rotta web Nova (`persist: true`), rotta API bearer e rotta pubblica firmata (entrambe
  `persist: false`). Senza `persist: false` ogni download di un preventivo `template=true`
  azzera silenziosamente `template` su tutti gli altri template dello stesso cliente (hook
  `Quote::booted()`).
- **`Quote::clearEmptyAdditionalServicesTranslations(bool $persist = true)`** normalizza
  **sempre** le traduzioni in memoria (Spatie considera "tradotta" anche una lingua con array
  vuoto, quindi senza normalizzazione non fa fallback) e salva su DB solo se `$persist`.
- **`additional_services` può essere `null`** ed è uno stato legittimo (KeyValue Nova vuoto,
  `nullable` in `QuoteApiRequest`): il template Blade normalizza `null`/stringa JSON/non-array
  a `[]` con la closure `$normalizeAdditionalServices` definita in testa e riusata in tutti e
  tre i punti di lettura. La coercizione vive nella vista, non sul modello: un quarto punto di
  lettura può reintrodurre lo stesso `TypeError`. **Nessun mutator `null → []` in scrittura**:
  `null` è documentato nel contratto API pubblico e `[]` è trattato come "traduzione da
  rimuovere". `QuoteFactory` popola sempre un array, quindi nei test lo stato `null` va forzato
  sulla colonna (`DB::table('quotes')->update([...])` + `refresh()`).
- **Immagini nei template PDF: sempre `file://` + path assoluto**, mai data URI base64 — con
  `barryvdh/laravel-dompdf ^3.0` i data URI non vengono renderizzati. Il protocollo `file://`
  è già in `config/dompdf.php` → `allowed_protocols`. I PNG vanno ridimensionati a ≤ 400-500px
  di larghezza: le immagini ad alta risoluzione non vengono renderizzate affatto.

### Lista e dettaglio Quote in Nova (oc:8404, oc:8407)
- Index: colonne ID (`ID::make()->sortable()`, `app/Nova/Quote.php:114`), Cliente, Titolo, Stato,
  Proprietario, Scadenza, Totale, PDF; Prodotti e Recurring nascosti. La colonna **Scadenza** mostra il task `todo` con `due_date` più vicina,
  **scaduta o futura** — nessun filtro "solo futuro": con `due_date` non nullable, quel vincolo
  avrebbe nascosto proprio i task scaduti, il caso più urgente.
- **`NovaTabTranslatable` non sa risolvere una sola lingua per-request**: genera N campi statici
  (uno per locale). Il titolo in index è un campo separato `onlyOnIndex()` che riusa l'accessor
  Spatie `$model->title`. Coesistono quindi tre punti di risoluzione del titolo (accessor,
  field form, field index-only): possono divergere su edge case futuri.
- **Eager loading filtrato per la colonna Scadenza, così da evitare l'N+1**: `Quote::indexQuery()`
  (`app/Nova/Quote.php:380-389`) chiama `->withNextTodoTask()`, e il `with(['tasks' => …])` filtrato
  su `status = 'todo'` con `orderBy('due_date')` vive nello scope `Quote::scopeWithNextTodoTask()`,
  non nel corpo di `indexQuery()`. L'indice `(due_date, status)` su `tasks` non è
  ottimale per questo pattern (`quote_id` + `status` + `MIN(due_date)`): debito noto, volume
  basso.
- Il dettaglio usa il **Tab nativo di Nova 4** (`Laravel\Nova\Tabs\Tab`), non
  `eminiarts/nova-tabs`, che pure è una **dipendenza diretta** del progetto
  (`composer.json:17`, `"eminiarts/nova-tabs": "^1.5"`) e non va rimosso dando per scontato che
  arrivi da altri. Il pattern del Tab nativo era già in uso in `Customer.php` e `App.php`.
- **`Tab::group` stila globalmente qualsiasi `.tab-item` dentro un `.tab-group`**, non solo i
  propri tab: `datomatic/nova-markdown-tui` usa la stessa classe in due sotto-componenti
  (switch Markdown/WYSIWYG e Scrivere/Anteprima). Ripristinato con due regole scoped in
  `public/nova-custom.css` — valgono per qualsiasi `MarkdownTui` nidificato in un tab, ovunque
  nel progetto.
- **`public/nova-custom.css` è l'unico CSS custom caricato** (`Nova::style(...)` in
  `NovaServiceProvider::boot()`). Esiste un secondo file quasi omonimo `public/css/nova-custom.css`,
  tracciato in git ma mai referenziato: modificarlo non ha alcun effetto.
- **`App\Nova\QuoteNoFilter` eredita `fields()` da `Quote`** senza override, quindi la struttura
  a tab si propaga al sub-panel "Preventivi" del dettaglio Customer. Scelta voluta: un override
  forkerebbe la UI in due definizioni da sincronizzare a mano.
- Il blocco `NovaTabTranslatable` è stato diviso in due chiamate per rispettare l'ordine campi
  richiesto: due selettori lingua indipendenti nel tab Main invece di uno.
- **Overflow della toolbar del campo Note**: difetto preesistente del componente
  `datomatic-nova-markdown-tui` (toolbar non responsive, richiede ~993-1023px), solo reso più
  visibile dal restringimento imposto da `Tab::group`. Da ticket dedicato, riguarda tutto il
  progetto.

## Come ci siamo arrivati

- **`is_array($v) && count($v) > 0` come guardia su `additional_services`** (proposta nelle note
  di oc:8413): scartata. Avrebbe reso una stringa JSON "presente" per il check «No items
  available» del template e "assente" per lista servizi e tabella costi — un PDF che dichiara
  servizi aggiuntivi ma ne omette il costo. Su un documento commerciale è peggio del 500: il 500
  si vede e viene segnalato, un totale mancante no.
- **`Quote::normalizedAdditionalServices()` sul modello** (oc:8413): scartato per tenere minimo
  il diff su un bugfix urgente; centralizzazione annotata come follow-up in
  `docs/features/8413-quote-generazione-pdf/notes.md`.
- **Endpoint `POST /api/quotes/{quote}/send`** (invio email col PDF, oc:8291): confermato fuori
  scope in sede di scrum, non solo come scelta tecnica. L'invio è responsabilità della skill
  Claude; dal backend il mittente sarebbe l'utente loggato, rendendo ingestibili le risposte
  (servirebbe un indirizzo no-reply dedicato). Se un ticket futuro lo riapre, va affrontato in
  modo organico per tutte le entità, non come piccola aggiunta a `Api/QuoteController.php`.
- **Rotta web `/quote/{id}` senza alcuna autorizzazione** (`QuoteController@show` fa un
  `findOrFail` nudo): gap preesistente, lasciato fuori scope in oc:8291, da ticket dedicato.
