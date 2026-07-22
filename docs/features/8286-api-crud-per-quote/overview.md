> Ticket: oc:8286

# API CRUD per Quote

## Cosa cambia
Vengono aggiunti endpoint REST CRUD per il modello `Quote` (`GET /api/quotes`, `GET /api/quotes/{quote}`, `POST /api/quotes`, `PATCH /api/quotes/{quote}`, `DELETE /api/quotes/{quote}`), più endpoint dedicati per l'attach/detach di `products` e `recurringProducts` con quantità (`POST/DELETE /api/quotes/{quote}/products/{product}` e equivalente per `recurring-products`). Segue il pattern già validato per l'API Tag (oc:8155): `Form Request` dedicato, autenticazione Sanctum, autorizzazione via `QuotePolicy` (fix incluso, vedi sotto).

Fix collaterale necessario: `QuotePolicy::before()` oggi ritorna sempre un booleano netto (mai `null`), quindi short-circuita **sempre** la valutazione — i metodi `update()`/`delete()` (incluso il blocco su `closed_won`/`closed_lost`) non vengono mai realmente eseguiti, né da Nova né potenzialmente dall'API. Viene corretto in un `if` che nega subito i ruoli non autorizzati e lascia proseguire la valutazione (ritorno `null`) per i ruoli abilitati, così il blocco su stato chiuso diventa realmente applicato — sia in Nova sia nell'API, che userà `$this->authorize()` invece di reimplementare la regola nel controller.

## Perché
I preventivi sono oggi gestibili solo da Nova. Serve un'interfaccia programmatica per creare/leggere/aggiornare/eliminare preventivi, con gli stessi permessi già definiti in `QuotePolicy`.

## Requisiti
- [ ] `GET /api/quotes` — lista preventivi, con filtro opzionale per `customer_id` e `status`; risposta include i totali calcolati (`total`, `net_total`/`getQuoteNetPrice`) oltre ai campi grezzi; query con eager loading esplicito (`with(['products', 'recurringProducts'])`) per evitare N+1 sul calcolo totali
- [ ] `GET /api/quotes/{quote}` — dettaglio preventivo, stessa struttura risposta della lista (campi + totali calcolati)
- [ ] `POST /api/quotes` — crea preventivo, solo sui campi realmente esistenti in DB (`title`, `status`, `priority`, `additional_services`, `customer_id`, `google_drive_url`, `discount`, `notes`, `template`); `name` è elencato in `$fillable` sul modello ma non esiste come colonna (dead fillable, verificato durante l'esecuzione — vedi `notes.md`), quindi non è esposto via API; `customer_id` validato con `exists:customers,id`
- [ ] `PATCH /api/quotes/{quote}` — aggiorna preventivo; il controller chiama `$this->authorize('update', $quote)`, che blocca se `status` è `closed_won` o `closed_lost` (regola resa realmente attiva dal fix a `QuotePolicy::before`)
- [ ] `DELETE /api/quotes/{quote}` — elimina fisicamente il preventivo (nessun soft delete sul modello); il controller chiama `$this->authorize('delete', $quote)` (nuovo blocco su stato chiuso da aggiungere anche in `QuotePolicy::delete`, oggi vuoto)
- [ ] `QuotePolicy::before()` corretto: `if (!hasRole(...)) return false;` altrimenti nessun return esplicito (`null`), per lasciar valutare `update()`/`delete()` sui ruoli abilitati
- [ ] `QuotePolicy::delete()` implementato con la stessa regola di `update()` (blocco su `closed_won`/`closed_lost`), oggi è un metodo vuoto
- [ ] Accesso limitato ai ruoli **Admin, Manager, Developer** (stessi ruoli di `QuotePolicy::before`), verificati via Policy/Gate, non con `abort_unless` duplicato nel controller
- [ ] I campi translatable coinvolti (`additional_services`, `notes`) vengono scritti via API solo sulla lingua di default (`config('app.locale')`); le altre traduzioni esistenti non vengono toccate
- [ ] `POST /api/quotes/{quote}/products/{product}` — attach prodotto al preventivo con `quantity` (pivot); upsert: se la coppia esiste già, aggiorna `quantity` (`syncWithoutDetaching`/`updateExistingPivot`), altrimenti crea il collegamento — nessuna riga duplicata
- [ ] `DELETE /api/quotes/{quote}/products/{product}` — detach prodotto; se la coppia non esiste, risposta `404`
- [ ] `POST /api/quotes/{quote}/recurring-products/{recurringProduct}` — attach recurring product con `quantity` (pivot); stesso comportamento upsert del caso `products`
- [ ] `DELETE /api/quotes/{quote}/recurring-products/{recurringProduct}` — detach recurring product; se la coppia non esiste, risposta `404`
- [ ] `GET /api/products` — lista dei `Product` esistenti (sola lettura, nessun CRUD), per permettere al consumer di scoprire cosa collegare al preventivo
- [ ] `GET /api/recurring-products` — lista dei `RecurringProduct` esistenti (sola lettura, stesso scopo)
- [ ] `DELETE /api/quotes/{quote}` logga (`Log::info`) `user_id`, `quote_id`, timestamp su ogni cancellazione riuscita — tracciabilità minima, non un sistema di audit completo (il modello non ha soft delete)
- [ ] Test Feature per ogni endpoint (successo, 403 per ruolo non autorizzato, 422 per validazione, blocco su status chiuso)

## Rischi
- **Rigidità del contratto API sull'attach**: `POST/DELETE /api/quotes/{quote}/products/{product}` usa l'id prodotto come path-param (non una risorsa pivot con id proprio). Se in futuro servisse gestire più righe dello stesso prodotto sullo stesso preventivo, sarebbe un breaking change da versionare (`/api/v2/...`). Accettato consapevolmente per mantenere lo scope minimo, coerente con l'approccio già usato per l'API Tag (oc:8155).
- **Nessun rate-limiting/throttling dedicato** sugli endpoint di scrittura. Rischio limitato: accesso già ristretto a soli Admin/Manager/Developer autenticati Sanctum, nessun endpoint pubblico. Non introdotto in questo ciclo, valutabile in futuro se emergono abusi.
- **Nessun kill-switch/feature flag** per disattivare rapidamente gli endpoint in caso di bug in produzione — il rollback resta un deploy che rimuove le route. Accettato: il volume di traffico atteso è basso e il rischio è mitigato dal log minimo su `DELETE` e dal blocco su stato chiuso via Policy.
- **`GET /api/products`/`GET /api/recurring-products`** non filtrano prodotti disattivati/archiviati (se il concetto esiste in Nova) — da verificare in Fase: write-plan leggendo i modelli `Product`/`RecurringProduct`; se serve un filtro va aggiunto lì senza impatto su questo overview.

## Out of scope
- Gestione multilingua completa di `additional_services`/`notes` via API (resta a carico di Nova)
- Generazione PDF preventivo via API (già gestita altrove, `resources/views/quote-pdf.blade.php`)
- Paginazione avanzata o filtri oltre `customer_id`/`status` (aggiungibili in futuro senza breaking change)
- CRUD completo su `Product`/`RecurringProduct` (creazione, update, delete) — restano gestibili solo da Nova; l'API espone solo lettura in lista

## Moduli toccati
- `app/Policies/QuotePolicy.php` (fix `before()`, implementazione `delete()`)
- `app/Http/Controllers/Api/QuoteController.php` (nuovo)
- `app/Http/Controllers/Api/ProductController.php` (nuovo, solo `index`)
- `app/Http/Controllers/Api/RecurringProductController.php` (nuovo, solo `index`)
- `app/Http/Requests/Api/QuoteApiRequest.php` (nuovo)
- `routes/api.php`
- `tests/Feature/Api/QuoteApiTest.php` (nuovo)
- `tests/Feature/Api/ProductApiTest.php` (nuovo)
