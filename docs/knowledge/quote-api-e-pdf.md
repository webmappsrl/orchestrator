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

### Campi rich-text e validazione dell'HTML (oc:8631)
- `additional_info`, `delivery_time`, `payment_plan`, `billing_plan` sono esposti in lettura e
  scrittura da `/api/quotes` **solo nella lingua di default**. Elenco dei campi, limite di
  lunghezza (50.000), regole e numero massimo di voci nei messaggi stanno in un posto solo,
  `App\Services\Quotes\QuoteRichText`, usato da request, controller e comando.
- `null` e `""` fanno `forgetTranslation()` invece di salvare una stringa vuota. **La lettura usa
  lo stesso fallback del PDF** (`fallbackAny` in `AppServiceProvider`): se manca `it` ma c'è testo
  in un'altra lingua, l'API restituisce quello, perché è quello che il PDF stampa; un campo
  davvero vuoto torna `null`. Svuotare via API rimuove solo `it`, e non cancella testi scritti in
  Nova nelle altre tab.
- **Il valore accettato si salva byte per byte, senza ripulitura**: `SafeRichTextHtml` rifiuta con
  422, non corregge, e i quattro campi sono esclusi da `TrimStrings`. Motivo: una risposta che
  conferma un testo diverso da quello mandato è proprio il difetto che il ticket voleva eliminare.
- **Si rifiutano solo i tag pericolosi** (`RichTextHtmlInspector::DANGEROUS_TAGS`: script, frame,
  oggetti, style, link, meta, base, controlli di form, svg, math, media). I tag sconosciuti ma
  innocui (`<o:p>` di Word, `<section>`, `<center>`) passano: il Tiptap di Nova ha `editHtml`, e un
  campo letto e rimandato senza modifiche non deve prendere 422.
- **L'HTML si analizza con `masterminds/html5`, lo stesso parser di DomPDF**, e gli URL si
  normalizzano come li legge il browser prima del controllo (tab e a capo tolti, `\` letto come
  `/`): senza, passavano `java\tscript:` e `/\host`. Ogni variante trovata è un caso in
  `RichTextHtmlInspectorTest`.
- **`style`**: rifiutato se contiene `url(`, `image-set(`, `expression(`, `@import`,
  `javascript:`, backslash o `/*`. Commenti ed escape servono solo a nascondere gli altri token:
  DomPDF toglie i commenti prima di leggere lo style e con `enable_remote => true` scarica ogni
  `url(` dal server.
- **Gli attributi di presentazione** (`align`, `width`, `bgcolor`, `face`, …) accettano solo valori
  semplici: DomPDF li traduce in CSS con `sprintf('text-align: %s;', $valore)` senza escape, e un
  `;` nel valore permetteva di aggiungere `background-image:url(…)` scaricato dal server.
- **Immagini solo sotto `/storage/`**, relative o su host e porta di `APP_URL`: un `src` sullo
  stesso host ma su un altro percorso (es. `/quote/218`) farebbe richiamare a DomPDF la rotta
  pubblica del PDF, in ricorsione. Conseguenza accettata: un'immagine da URL esterno inserita da
  Nova (`tt-mode="url"`) viene rifiutata dall'API, quindi il client deve mandare nel PATCH solo i
  campi che modifica.
- **Annidamento massimo 100 livelli**, con visita iterativa: la versione ricorsiva senza limite
  costava circa 2 s di CPU per campo con 16.000 `<b>` annidati.
- **`additional_services`** (`AdditionalServicesMap`): oggetto `{descrizione: prezzo}`, non lista;
  prezzo numero con al massimo due decimali o stringa `^-?\d+([.,]\d{1,2})?$`, senza separatore
  delle migliaia, cioè esattamente ciò che `number_format(str_replace(',', '.', …))` nel template e
  `getTotalAdditionalServicesPrice()` sanno leggere (`"1.234,56"` verrebbe stampato così com'è e
  darebbe 1.234 nel totale). Un oggetto con chiavi `"0"`, `"1"`… arriva al PHP come lista: il
  messaggio lo spiega. Nova applica la stessa regola al salvataggio della risorsa (oc:8734): vedi
  «Lista e dettaglio Quote in Nova».
- **I messaggi 422 dicono cosa è sbagliato e cosa fare**, col nome dell'attributo coinvolto,
  raggruppati per tipo con il numero di occorrenze e al massimo 10 voci per campo. I segnaposto del
  Validator (`:attribute`, `:input`…) scritti dall'utente vengono protetti con un word joiner
  (U+2060), altrimenti il Validator li sostituirebbe dentro il messaggio già composto.
- **Le regole sono descritte anche in `/docs/api`** (pagina pubblica): `RICH_TEXT_DESCRIPTION` in
  `Api\QuoteController`, applicata con `#[BodyParameter]` ai quattro campi di `store`/`update` e
  verificata da `QuoteApiDocsTest`. Quando la regola cambia va aggiornata insieme; elenca le
  categorie rifiutate, non come funziona il controllo.
- **`php artisan quotes:check-rich-text`** (sola lettura) passa dal `Validator` con le stesse Rule
  dell'API su tutti i preventivi e tutte le lingue: exit 1 se qualcosa verrebbe rifiutato, e come
  motivo il messaggio 422. Va lanciato sui dati di produzione prima di rilasciare una modifica alla
  regola; esce con 1 anche per prezzi testuali già presenti, non solo per l'HTML.

### PDF del preventivo (oc:8291, oc:8413, oc:8047, oc:8631, oc:8734, oc:8733)
- `GET/POST /api/quotes/{quote}/pdf(-link)` più rotta pubblica firmata in `routes/web.php`
  (`quotes.pdf.public`), middleware `['signed', 'throttle:30,1']`. Il throttle serve perché la
  rotta vive fuori da `auth:sanctum` e quindi fuori dal `throttle:api`.
  **`->missing(fn () => abort(403))` è obbligatorio**: `SubstituteBindings` risolve il binding
  prima che `signed` verifichi la firma, quindi senza di esso un id inesistente darebbe 404 e
  una firma invalida su un id esistente 403 — permettendo a un anonimo di enumerare gli id.
- **`expires_in_days`**: `integer|min:1|max:90`, default 30. **Non esiste revoca del singolo
  link** prima della scadenza: solo la rotazione di `APP_KEY`, che invalida tutti i link
  firmati del progetto.
- **Ogni link generato resta in `quotes.pdf_links`** (oc:8733), scritto solo da
  `QuotePdfLinks::generate()` (endpoint e action Nova). Generarli e vederli è solo per Admin e
  Manager (`QuotePdfLinks::canShare()`), perché l'URL apre il PDF senza login; per lo stesso motivo
  il campo non esce dall'API (`formatQuote` elenca i campi uno per uno). Non è in `$fillable`, quindi
  `DuplicateQuote` non copia i link nella copia.
- **Nova:** action «Genera link pubblico» solo dal dettaglio, anche sulle trattative chiuse (è
  registrata anche su `ArchivedQuotes`; `canRun` evita `QuotePolicy::update()`, che le rifiuta);
  l'URL compare nel messaggio di conferma, che è una notifica temporanea. Nella tab Main «Link
  attivi» e «Storico dei link» mostrano l'indirizzo per intero. **`->help()` non si vede nel
  dettaglio Nova**, solo nei form: l'avvertenza «il link apre sempre la versione attuale» è nel
  contenuto dello Storico. I link generati prima di oc:8733 non sono mai stati salvati.
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
- **Piano di fatturazione nel PDF** (oc:8631): `billing_plan` si compilava in Nova ma il template
  non lo stampava. Ora è subito dopo il Piano di pagamento, con la stessa classe `payment-plan`, e
  il titolo usa la chiave `"Billing plan"`, distinta dall'etichetta Nova `"Billing Plan"` (chiavi
  JSON duplicate: l'ultima vince).
- **Prezzo non valido già salvato** (oc:8734): la riga di un servizio aggiuntivo formatta il prezzo
  solo se passa `AdditionalServicesMap`; altrimenti stampa il valore così com'è, senza €, invece di
  mandare il PDF in 500. Il totale non cambia: «da definire» vale 0, «1.500» vale 1,5.

### Lista e dettaglio Quote in Nova (oc:8404, oc:8407, oc:8734)
- Index: colonne ID (`ID::make()->sortable()`, `app/Nova/Quote.php:114`), Cliente, Titolo, Stato,
  Proprietario, Scadenza, Totale, PDF; Prodotti e Recurring nascosti. La colonna **Scadenza** mostra il task `todo` con `due_date` più vicina,
  **scaduta o futura** — nessun filtro "solo futuro": con `due_date` non nullable, quel vincolo
  avrebbe nascosto proprio i task scaduti, il caso più urgente.
- **`NovaTabTranslatable` non sa risolvere una sola lingua per-request**: genera N campi statici
  (uno per locale). Il titolo in index è un campo separato `onlyOnIndex()` che riusa l'accessor
  Spatie `$model->title`. Coesistono quindi tre punti di risoluzione del titolo (accessor,
  field form, field index-only): possono divergere su edge case futuri.
- **Nessuna regola-oggetto su un campo dentro `NovaTabTranslatable`** (oc:8734): il package passa
  ogni regola (`rules`, `creationRules`, `updateRules`) a `strpos()` e riconosce come oggetto solo il
  vecchio `Illuminate\Contracts\Validation\Rule`. Una `ValidationRule` o una closure dà `TypeError`
  e 500 su tutta la risorsa, elenco compreso. Per questo i servizi aggiuntivi si validano con
  `AdditionalServicesMap` in `Quote::afterValidation()`, non con `->rules()` sul `KeyValue`.
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

### Trattative aperte senza task attivi (oc:8729)
- **Definizione unica** in `Quote::scopeOpenWithoutActiveTasks()`: stato diverso da `closed won`/
  `closed lost` e nessun task `todo`. Passa da `Quote::tasks()`, che non filtra per utente: conta il
  task di qualunque assegnatario o creatore. La usano sia l'API sia la Lens, che restituiscono le
  stesse trattative.
- **API:** `GET /api/quotes?without_active_tasks=1` (o `true`), combinabile con `customer_id`,
  `status`, `sort` e paginazione; senza `customer_id` vale per tutti i clienti. La validazione è
  `in:0,1,true,false`, non `boolean`: quella di Laravel rifiuta la stringa `true` che arriva da una
  query string.
- **Nova:** Lens «Trattative senza task attivi» (`QuotesWithoutActiveTasks`), solo su `Nova\Quote`:
  `ArchivedQuotes` e `QuoteNoFilter` la estendono e ridefiniscono `lenses()` vuoto. La Lens non passa
  da `indexQuery()`, quindi l'esclusione delle chiuse le arriva dallo scope. Ordina per `id`
  decrescente se l'utente non sceglie una colonna: senza `ORDER BY` PostgreSQL non tiene lo stesso
  ordine fra le pagine.
- **Come si usa:** Preventivi → menu a tendina delle Lens in cima all'elenco → «Trattative senza task
  attivi». Dalla Lens non c'è un comando per toglierla: si torna con «Preventivi» nella barra laterale
  o con il pulsante indietro del browser (comportamento standard delle Lens Nova).
- **Non escluse le Quote `template`**: un modello aperto e senza task compare fra le trattative
  scoperte, come compare nell'elenco Preventivi. Non verificato su dati reali.

### Motivo della chiusura persa (oc:8732)
- Colonna `lost_reason` (text, nullable) su `quotes`, non traducibile. Esiste solo sulle trattative
  in `closed lost`: un secondo listener `saving` in `Quote::booted()` la azzera per ogni altro stato,
  da ogni canale che passa da `save()` (form Nova, API, Kanban `Sales`). Non scatta su
  `Quote::query()->update()`, che non lancia gli eventi del model. È un listener a sé perché quello
  del template esce subito se la Quote non è un template.
- **Va scritto nello stesso salvataggio che chiude la trattativa**: dopo, `QuotePolicy::update()`
  blocca le modifiche sia in Nova sia via API.
- Nova: Textarea solo nei form, subito dopo la Select «Status», nascosta di default; con
  `dependsOn(['status'])` compare ed è `required|max:2000` solo con «Chiuso Perso». Nova applica
  `dependsOn` anche lato server, in validazione (`PerformsValidation`) e nel fill (`FillsFields`).
  Nel dettaglio una seconda Textarea in sola lettura, visibile solo se la trattativa è persa e il
  motivo è compilato (le trattative perse prima di oc:8732 non ce l'hanno); `ArchivedQuotes` la
  eredita.
- API: `lost_reason` in `POST`/`PATCH` e in tutte le risposte di `formatQuote()`. **Obbligatorio
  (422) quando la stessa richiesta porta `status` a `closed lost`**: cambio di contratto per la
  skill commerciale, che deve chiedere il motivo all'utente invece di inventarlo. Mandato con un
  altro stato viene azzerato dall'hook del model.
- **Il Kanban `Sales` rifiuta il drop in «Chiuso Perso»**: lì non c'è dove scrivere il motivo,
  quindi una trattativa persa si chiude solo dal form. È un'opzione del componente condiviso,
  `KanbanCard::deniedDropStatuses([stato => chiave del messaggio])`, attiva solo in
  `app/Nova/Dashboards/Sales.php`: `KanbanController::updateStatus()` risponde 422 con il messaggio
  tradotto e `card.js` lo mostra al posto di «Error updating status.». Il drop *fuori* da
  «Chiuso Perso» resta permesso, e l'hook del model azzera il motivo. Come `deniedUpdateRoles`,
  l'opzione arriva al server dentro il `config` mandato dal browser.

### Contatti del cliente sulla trattativa (oc:8731)
- Colonna `customer_contacts` (text, nullable) su `quotes`, non traducibile: le persone del cliente
  da contattare **per quella trattativa**, quando non coincidono con il referente del Customer
  (`customers.email`, che resta com'era). Il dato è della singola Quote, non del Customer.
- Formato `Nome Cognome <email>` separati da virgola, controllato da `App\Rules\CustomerContacts`,
  la stessa Rule in Nova e nell'API; il nome può stare fra virgolette (`"Rossi, Mario" <…>`).
  I casi rifiutati sono nella Rule e nei suoi test. La Rule va preceduta da `string` e `max:2000`:
  ignora i valori che non sono stringhe.
- Il valore valido si salva com'è scritto, senza riscriverlo; fa eccezione `TrimStrings`, che toglie
  spazi e a capo ai bordi dell'intero valore.
- Nova: `Textarea` nella tab Main subito sotto il Cliente, nascosta nell'elenco, con `alwaysShow()`
  e un testo di aiuto sul formato. **Nova rende l'help come HTML** (`v-html`): il testo passa da
  `e()`, altrimenti `<email>` sparisce come tag sconosciuto.
- API: `customer_contacts` in `POST`/`PATCH` (`sometimes` + `nullable`, 422 se malformato) e in
  tutte le risposte di `formatQuote()`. **Una chiave che l'orchestrator non conosce viene ignorata
  con 200** (`store()`/`update()` salvano solo `validated()`): l'orchestrator va rilasciato prima di
  attivare il campo nella skill commerciale, altrimenti il dato si perde senza errori.
- L'azione Nova «Duplica» copia il campo nella trattativa nuova, come ogni altro campo.

## Come ci siamo arrivati

- **`Address::create()` di Symfony come unica validazione dei contatti** (oc:8731, overview):
  scartato. La sua regex non è ancorata: accetta testo dopo `>`, ignora le righe dopo la prima e
  restituisce un nome vuoto senza errore. `Address::fromString()` non esiste più in
  `symfony/mime` 7.4. Per questo c'è una Rule nostra, e Symfony controlla solo l'email.
- **Trattative scoperte ricavate dal client** confrontando `/api/quotes` e `/api/tasks` (oc:8729,
  call del 30/09/2026): superato allo scrum del 20/10/2026 dal parametro `without_active_tasks`.
  `/api/tasks` restituisce solo i task di chi chiede (`forUser`), quindi una trattativa seguita da un
  collega risultava scoperta. I filtri di `/api/tasks` sono rimasti per altri usi
  (vedi [task-e-quote.md](task-e-quote.md)).
- **Link pubblici non salvati** (oc:8291): superato da oc:8733. Il link firmato «viveva solo nelle
  mail» e la scheda non diceva se il preventivo era già stato mandato; ora ogni link resta in
  `pdf_links`.
- **Elenco chiuso dei tag ammessi** (oc:8631, prima versione): superato. Rifiutava anche tag
  innocui prodotti con `editHtml` o incollati da Word (`<o:p>`, `<section>`), facendo prendere 422 a
  un campo letto e rimandato senza modifiche. Ora si rifiutano solo i tag pericolosi.
- **Commenti CSS tolti prima del controllo sullo `style`** (oc:8631, dopo la prima review):
  superato. La rimozione con una regex si aggirava con un commento dentro una stringa CSS
  (`font-family:'/*';background:url(…);x:'*/'`), che il browser in Nova avrebbe caricato. Ora `/*`
  è rifiutato.
- **Lettura dei campi rich-text senza fallback** (oc:8631, prima versione): superata. L'API
  rispondeva `null` mentre il PDF stampava il testo di un'altra lingua. Scartata anche
  l'alternativa di cancellare tutte le lingue allo svuotamento: avrebbe eliminato senza avviso
  testi scritti in Nova.

- **`is_array($v) && count($v) > 0` come guardia su `additional_services`** (proposta nelle note
  di oc:8413): scartata. Avrebbe reso una stringa JSON "presente" per il check «No items
  available» del template e "assente" per lista servizi e tabella costi — un PDF che dichiara
  servizi aggiuntivi ma ne omette il costo. Su un documento commerciale è peggio del 500: il 500
  si vede e viene segnalato, un totale mancante no.
  **Eccezione (oc:8734):** un prezzo non valido già salvato non manda più il PDF in 500, ma viene
  stampato così com'è. Si accetta sapendo che il totale non lo conta («da definire» vale 0,
  «1.500» vale 1,5), e che prima la riga stessa stampava «1,50 €»: in compenso il preventivo torna
  scaricabile e la riga rende visibile il valore anomalo. Per trovare i preventivi con prezzi non
  validi c'è `php artisan quotes:check-rich-text`.
- **`AdditionalServicesMap` che legge anche la stringa JSON di Nova, applicata con `->rules()`**
  (oc:8734, prima versione dell'overview): scartata. Dentro `NovaTabTranslatable` qualsiasi
  regola-oggetto manda in 500 la risorsa; decodificando in `afterValidation()` la regola resta
  quella dell'API, invariata.
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
- **Action «Chiudi come persa», come il respingimento di Forestas (oc:8567)** (oc:8732): scartata,
  scelta confermata in review. In Orchestrator lo stato si cambia con la Select del form, non con
  Action: avrebbe obbligato a togliere «Chiuso Perso» dalla Select, con due modi diversi di
  cambiare stato nella stessa schermata, e l'API avrebbe comunque avuto bisogno della sua regola.
