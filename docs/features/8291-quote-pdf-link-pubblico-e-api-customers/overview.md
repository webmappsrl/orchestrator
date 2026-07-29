> Ticket: oc:8291

# API Quotes: PDF via API con link pubblico firmato ed endpoint customers

## Cosa cambia

Il ciclo dei preventivi (consultazione, invio al cliente, cambio stato) diventa eseguibile interamente via API, senza passare dal backend Nova nel browser:

1. **PDF del preventivo via API**: `GET /api/quotes/{quote}/pdf?lang=` (bearer auth, stream diretto) e `POST /api/quotes/{quote}/pdf-link` (genera un link pubblico firmato e temporaneo, da inserire in un'email).
2. **Endpoint pubblico `/api/customers`**: lista e dettaglio, con `contact_emails[]` come array strutturato invece di testo libero.
3. **PATCH parziale sui quotes**: già implementato in oc:8286 — questo ciclo aggiunge solo un test di regressione che lo verifica esplicitamente.
4. **Timestamps, ordinamento, paginazione, filtro multi-status** sulla lista quotes.
5. **Dettaglio quote con relazioni** (`?include=customer,products,recurringProducts`) e campi `iva`/`final_price`.
6. **Coerenza dei tipi** nei docblock OpenAPI di `QuoteController` (Scramble genera la doc pubblica da questi).
7. **Documentazione** di come ottenere un Personal Access Token Sanctum per l'uso da skill.

Il punto "invio email" del ticket (POST /quotes/{quote}/send) è **fuori scope** per decisione esplicita (vedi Out of scope).

## Perché

Durante la sessione di lavoro del 22/07/2026 (Alessio Piccioli) sono stati riscontrati limiti concreti che impedivano di gestire i preventivi da skill Claude senza aprire il browser: il PDF era raggiungibile solo da rotta web con sessione, non c'era modo di leggere le email di contatto di un cliente via API, e la lista quotes non esponeva timestamp/paginazione per orientarsi.

## Requisiti

### 1. PDF via API + link pubblico
- [ ] `GET /api/quotes/{quote}/pdf?lang=` — stream PDF autenticato (bearer), riusa la logica di generazione già esistente in `QuoteController@generatePdf` (estratta in un servizio condiviso tra rotta web e rotta API)
- [ ] `lang` accetta qualsiasi valore; se la traduzione non esiste fa fallback silenzioso sul `fallback_locale` dell'app (comportamento Laravel standard, nessuna validazione bloccante — decisione esplicita: il progetto supporta oggi solo `it`/`en`, `fr`/`es`/`de` sono cartelle vuote in `lang/`)
- [ ] `POST /api/quotes/{quote}/pdf-link` — body `{ lang, expires_in_days }`, `expires_in_days` validato `integer|min:1|max:90`, default 30gg se omesso — risponde `{ url, expires_at }`
- [ ] Link generato con `URL::temporarySignedRoute` (nessuna migration, nessuna revoca singola prima della scadenza — decisione esplicita, vedi Rischi)
- [ ] Rotta pubblica dedicata (`middleware(['signed', 'throttle:30,1'])`, nessun `auth:sanctum`) che verifica la firma e restituisce il PDF con filename `Preventivo_WEBMAPP_{cliente}.pdf` — throttle dedicato perché la rotta vive fuori dal gruppo `auth:sanctum` e quindi fuori dal `throttle:api` esistente
- [ ] Filename sanitizzato (rimozione caratteri non alfanumerici/spazi/underscore/trattini, troncamento a lunghezza ragionevole) prima di essere passato a `Content-Disposition` — applicato su tutte le rotte che generano il PDF (web esistente, API, pubblica), dato che `full_name`/`name` del customer è testo libero non validato
- [ ] Nessun limite sul numero di link pubblici generabili per lo stesso quote (decisione esplicita — link stateless, rischio di superficie considerato accettabile, vedi Rischi)

### 2. Endpoint Customers
- [ ] Migration: aggiunta `vat` e `address` (nullable) su `customers` — **non** `company_name` (vedi sotto)
- [ ] `company_name` nella response API è un **alias di `full_name`** (già popolato per 124/136 customer, contiene il nome legale — nessuna nuova colonna)
- [ ] `contact_emails[]` derivato da `email` (stessa regex già usata da Nova: `preg_split('/[\s,]+/', ...)`, trim, filtro vuoti — coerenza tra API e Nova, nessuna gestione di `;` come separatore in assenza di casi reali osservati in DB) — nessuna nuova colonna
- [ ] `GET /api/customers?status=&search=` e `GET /api/customers/{customer}` → `{ id, name, company_name, vat, address, contact_emails[], phone, status, owner, notes }`
- [ ] `owner` = relazione `user_id` esistente (`{ id, name }`)
- [ ] Ruoli abilitati: `Admin`/`Manager`/`Developer` — stessi di Quote (check inline `abort_unless`, stesso pattern di `ProductController`, nessuna Policy dedicata)
- [ ] Comando Artisan di backfill `vat` da `heading` via regex (`P.IVA`/`Partita IVA`/`C.F.`), con modalità dry-run che produce un report prima/dopo per revisione manuale, e un flag `--apply` per l'update reale — il report viene sempre salvato su file (`storage/app/...`) prima di ogni `--apply`, come rete di sicurezza minima in assenza di un vero rollback automatico
- [ ] `address` resta `null` dopo la migration (colonna nuova, nessun backfill automatico) — popolamento manuale successivo, `heading` non viene toccato/rimosso

### 3. PATCH parziale sui quotes
- [ ] Verifica: `PATCH /api/quotes/{id} {"status": "..."}` senza `title`/`customer_id` funziona già (regola `sometimes` già presente in `QuoteApiRequest`)
- [ ] Aggiunto test di regressione esplicito che lo copre (oggi non testato)

### 4. Timestamps, ordinamento, paginazione
- [ ] `created_at`/`updated_at` esposti in `formatQuote()` (colonne già esistenti, mai serializzate)
- [ ] `GET /api/quotes?sort=-created_at` (e `created_at` per asc)
- [ ] `GET /api/quotes?per_page=&page=` — **opt-in**: senza questi parametri la risposta resta un array semplice come oggi (nessun breaking change per client esistenti, incluso `QuoteApiTest` attuale)
- [ ] Filtro `status[]` multiplo (in aggiunta al filtro singolo `status` già esistente)

### 5. Dettaglio quote con relazioni
- [ ] `GET /api/quotes/{id}?include=customer,products,recurringProducts` — espande le relazioni richieste nella response
- [ ] `iva` (`net_total * 0.22`, stessa aliquota hardcoded già usata in `app/Nova/Quote.php`) e `final_price` (`net_total + iva`) sempre presenti in `formatQuote()`

### 6. Coerenza tipi OpenAPI (Scramble)
- [ ] Corretti i docblock `@response` di `Api/QuoteController` dove il tipo dichiarato non corrisponde al runtime: `additional_services` (dichiarato stringa, in realtà array — cast + translatable), `priority` (dichiarato stringa in alcuni punti, cast `int`), `template` (dichiarato `string|null`, cast `bool`)

### 7. Documentazione autenticazione per skill
- [ ] Docblock di `AuthController::login` aggiornato per spiegare che il token emesso è long-lived e full-access (nessuna ability granulare in questo ciclo — vedi Out of scope), pensato per l'uso da skill Claude

## Rischi

- **Link pubblico non revocabile singolarmente**: con `temporarySignedRoute` un link firmato resta valido fino a scadenza; l'unica revoca è ruotare `APP_KEY`, che invaliderebbe *tutti* i link firmati del progetto (non solo quello del preventivo in questione). Mitigazione: `expires_in_days` validato (`max:90`), scadenza breve di default (30gg), throttle dedicato sulla rotta pubblica.
- **Rotta web `/quote/{id}` esistente non ha alcuna autorizzazione** (`QuoteController@show` fa `findOrFail` nudo, protetta solo dal middleware `web` generico) — gap pre-esistente, non introdotto da questa feature. Il refactor verso `QuotePdfService` condiviso lo eredita senza risolverlo. **Fuori scope per decisione esplicita** — segnalato in `notes.md` come follow-up per un ticket dedicato.
- **Fallback lingua silenzioso**: richiedere `lang=fr` su un progetto senza traduzioni francesi restituisce un PDF in `en` senza errore visibile. Mitigazione: comportamento esplicitamente documentato e coerente con lo standard Laravel; se in futuro emergono richieste reali per `fr`/`es`/`de` andranno aggiunte le traduzioni.
- **Backfill `vat` da regex su `heading`**: testo libero non strutturato, il regex può fallire su formati non previsti (es. p.iva scritta diversamente). Mitigazione: report dry-run obbligatorio prima di ogni `--apply`, nessun update automatico silenzioso.
- **`company_name` (alias `full_name`, nessuna colonna propria) assente per 12/136 customer**: quei record avranno `company_name: null` in risposta API finché `full_name` non viene compilato manualmente — `address` è invece sempre `null` alla creazione perché è una colonna nuova senza backfill automatico.
- **Paginazione opt-in duplica la logica di risposta** (array semplice vs oggetto paginato) nello stesso endpoint — più complessità nel controller, ma evita un breaking change su un endpoint già in produzione (oc:8286).

## Out of scope

- Endpoint `POST /api/quotes/{quote}/send` per l'invio email — il link pubblico (punto 1) è sufficiente, l'invio resta gestito dalla skill via Gmail. **Confermato in sede di scrum** (non solo scelta tecnica di questo ciclo): (1) la gestione dell'invio email è responsabilità della skill Claude, già in grado di farlo in modo più appropriato; (2) inviare dal backend userebbe l'indirizzo dell'utente loggato come mittente, impedendo una gestione efficace delle risposte (servirebbe un indirizzo no-reply/di sistema dedicato) — problema che la skill non ha; (3) si preferisce evitare implementazioni frammentate, rimandando l'intera gestione email-su-ticket a una discussione più ampia e strutturata in futuro.
- Enforcement granulare delle abilities Sanctum (`quotes:read`, `quotes:write`, `customers:read`) — richiederebbe modifiche coerenti a *tutti* i controller API (non solo Quote/Customer) per evitare un modello di sicurezza a metà; questo ciclo produce solo documentazione
- Traduzioni `fr`/`es`/`de` per il PDF preventivo — le cartelle restano vuote, fallback su `en`
- Nessuna colonna `company_name` viene creata: nella response API è un alias di sola lettura su `full_name` (calcolato nel controller), non un campo persistito nel DB
- Backfill automatico di `address` — troppo inaffidabile su testo libero multi-riga, popolamento manuale
- Revoca singola del link pubblico prima della scadenza

## Moduli toccati

- `app/Services/QuotePdfService.php` (nuovo) — logica di generazione PDF condivisa tra rotta web e rotta API
- `app/Http/Controllers/QuoteController.php` — refactor `generatePdf()` per usare il servizio condiviso
- `app/Http/Controllers/Api/QuoteController.php` — nuove azioni `pdf()`, `pdfLink()`; `index()` con sort/paginazione opt-in/filtro multi-status; `show()` con `include`; `formatQuote()` con `created_at`/`updated_at`/`iva`/`final_price`; fix docblock `@response`
- `app/Http/Controllers/QuotePublicController.php` (nuovo) — rotta pubblica firmata per il download PDF
- `app/Http/Controllers/Api/CustomerController.php` (nuovo) — `index()`, `show()`
- `app/Http/Controllers/Api/AuthController.php` — docblock aggiornato
- `routes/api.php` — nuove rotte quotes (pdf, pdf-link) e customers
- `routes/web.php` o `routes/api.php` — rotta pubblica firmata (nome rotta per `temporarySignedRoute`)
- `database/migrations/xxxx_add_vat_and_address_to_customers_table.php` (nuovo)
- `app/Console/Commands/BackfillCustomerVatFromHeading.php` (nuovo)
- `app/Models/Customer.php` — accessor `contact_emails` (derivato da `email`)
- `tests/Feature/Api/QuoteApiTest.php` — nuovi test (pdf, pdf-link, sort/paginazione, include, patch status-only)
- `tests/Feature/Api/CustomerApiTest.php` (nuovo)
- `tests/Feature/Console/BackfillCustomerVatFromHeadingTest.php` (nuovo)
