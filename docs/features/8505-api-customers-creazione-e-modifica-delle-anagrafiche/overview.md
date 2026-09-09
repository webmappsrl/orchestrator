> Ticket: oc:8505

# API Customers: creazione e modifica delle anagrafiche

## Cosa cambia

- Nuovi endpoint `POST /api/customers` e `PATCH /api/customers/{customer}`, complemento delle GET già rilasciate in oc:8291 (che restano invariate).
- `name` (lo slug tipo `parco_miniere_altavalsugana`) diventa **opzionale** in POST: se omesso viene generato da `company_name` (`Str::slug` con underscore invece di trattino, coerente con lo stile già presente in DB), con suffisso numerico incrementale in caso di collisione.
- `contact_emails` in scrittura si comporta su due modalità mutuamente esclusive nello stesso payload PATCH:
  - `contact_emails`: array completo, sostituisce l'intero contenuto della colonna `email`.
  - `contact_emails_add`: stringa (o array) di indirizzi da aggiungere a quelli esistenti, senza richiedere una lettura preventiva — copre il caso tipico "aggiungi il decisore che manca".
- Validazione lato server:
  - `phone`: riusa `Customer::normalizePhoneString()` + la stessa logica di plausibilità strutturale già in `App\Nova\Customer::isValidPhoneFragment()` (oc:8412), portata lato API. **Aggiornamento post-review:** la prima implementazione riusava la logica di plausibilità ma non la normalizzazione — un numero con NBSP (tipico da copia-incolla) veniva accettato da Nova ma rifiutato dall'API. Corretto richiamando `Customer::normalizePhoneString()` in testa a `phoneValidationError()`, come originariamente richiesto da questo stesso paragrafo.
  - `contact_emails`/`contact_emails_add`: ogni frammento validato come email (formato).
  - `vat`: 11 cifre numeriche, stesso pattern già assunto da `BackfillCustomerVatFromHeading` (oc:8291) per distinguere una P.IVA da un Codice Fiscale.
  - `status`: `Rule::in()` sui valori di `App\Enums\CustomerStatus` (`unknown`, `opportunity`, `active`, `lost` — enum già esistente, nessuna modifica).
- Nessun vincolo di unicità sulla P.IVA (comportamento esplicitamente voluto: la stessa P.IVA può appartenere a più schede legittimamente). `POST` restituisce però un campo `warnings` nel body della risposta 201 quando la P.IVA coincide con quella di uno o più customer esistenti, elencandoli (id + nome) senza mai bloccare la creazione.
- Autorizzazione: **Admin/Manager only**, invariata rispetto al `GET` già esistente (stesso `CustomerController::authorizeRole()` riusato, nessuna modifica alla policy). Il campo `owner` (`user_id`) resta puramente informativo — nessun controllo di ownership, coerente col fatto che solo il 31% dei customer attuali (45/145) ne ha uno valorizzato.
- Fix mirato del punto 6 del ticket, **ridimensionato dopo una verifica più accurata**: la prima analisi (fatta interrogando erroneamente la chiave JSON `/api/quotes` invece di `/quotes` — Scramble strippa il prefisso `/api`) aveva concluso che `requestBody` fosse sistematicamente `null`. Rifatta la verifica con la chiave corretta (`php artisan scramble:export` + `jq '.paths["/quotes"].post.requestBody'`): Scramble **documenta già correttamente** il request body di `POST`/`PATCH /quotes` (schema `QuoteApiRequest` con tutte le proprietà, `required` condizionale su create/update) — nessun bug sistemico, nessun workaround `#[BodyParameter]` necessario. L'unico problema reale, confermato sullo schema effettivo: `additional_services` è documentato come `{"type": ["array","null"], "items": {"type": "string"}}` (array di stringhe), mentre il formato reale scritto/letto dal codice è un **oggetto** `{descrizione: importo}`. Fix verificato empiricamente: `#[Dedoc\Scramble\Attributes\BodyParameter('additional_services', type: 'object', description: '...')]` su `QuoteController::store()`/`update()` produce uno schema `allOf` che compone il `$ref` esistente (`QuoteApiRequest`) con l'override mirato solo su questa proprietà — nessuna modifica alle regole di validazione, nessun impatto sulle altre proprietà già corrette.

## Perché

Le API di Orchestrator permettono di leggere i clienti ma non di scriverli — l'intero resto del ciclo commerciale (preventivi, prodotti, ricorrenti, PDF, task) è già automatizzabile via API, ma l'anagrafica resta l'unico anello manuale. Effetto misurato su una settimana di lavoro commerciale: 7 schede su 11 toccate erano incomplete, con impatti concreti già in produzione (offerta da 47.000 € partita senza email in anagrafica, PDF con ragione sociale copiata dal capitolato per errore, contatti chiave presenti solo dentro un PDF e non in scheda, preventivi presentati per 15.780 € su una scheda a zero contatti). L'obiettivo è poter creare/aggiornare le anagrafiche dallo stesso flusso automatico che già gestisce preventivi e task.

## Requisiti

- [ ] `POST /api/customers` crea un customer con i campi scrivibili: `name` (opzionale, auto-generato da `company_name` se omesso), `company_name` (scrive su `full_name`), `vat`, `address`, `contact_emails` (scrive su `email`), `phone`, `status`, `notes`
- [ ] `PATCH /api/customers/{customer}` modifica parziale degli stessi campi
- [ ] `contact_emails` (replace-all) e `contact_emails_add` (append) mutuamente esclusivi nello stesso payload PATCH
- [ ] Validazione server-side: `phone` (riuso `normalizePhoneString`/`isValidPhoneFragment`), email (formato, per ogni frammento di `contact_emails`/`contact_emails_add`), `vat` (11 cifre numeriche), `status` (`Rule::in(CustomerStatus)`)
- [ ] Nessun vincolo di unicità sulla P.IVA; `POST` restituisce `warnings` con i customer esistenti che condividono la stessa P.IVA (id + name), mai bloccante
- [ ] `name` auto-generato da `company_name` (slug con underscore) quando omesso in POST, con suffisso numerico su collisione — verificato esplicitamente con query di unicità (`Customer::where('name', $slug)->exists()`) prima di assegnare, coerente con `unique:customers,name` di Nova pur restando un vincolo solo applicativo
- [ ] `CustomerApiRequest::rules()` è whitelist stretta sui soli campi del ticket (`name`, `company_name`, `vat`, `address`, `contact_emails`/`contact_emails_add`, `phone`, `status`, `notes`); il controller usa sempre `$request->validated()` per il `fill()`, mai `$request->all()` — nessun campo di integrazione (`hs_id`, `wmpm_id`, `associated_user_id`, ecc.) scrivibile via questi endpoint
- [ ] Autorizzazione Admin/Manager only (riuso `CustomerController::authorizeRole()` esistente), `owner` resta informativo, nessun gate di ownership
- [ ] `#[BodyParameter('additional_services', type: 'object', description: '...')]` su `QuoteController::store()`/`update()`, verificato con `php artisan scramble:export` che lo schema generato (`allOf`) riflette il tipo `object` con la descrizione

## Rischi

Emersi dalla Fase: challenge (subagente adversariale + integrazione), in ordine di criticità:

1. **[Blind spot] Degrado silenzioso di `AlignTagsCommand`**: `app/Console/Commands/AlignTagsCommand.php:56` fa `Customer::where('email', $user->email)->first()` — match esatto sulla colonna `email`, già fragile con più indirizzi. `contact_emails_add` aumenterà strutturalmente il numero di customer con email multiple, degradando ulteriormente e silenziosamente questo comando (nessun errore, solo mancati allineamenti tag nel tempo). **Non mitigato in questo ciclo** (fuori scope) — tracciato come debito noto, ticket dedicato da aprire a fine workflow con spiegazione completa.
2. **[Assunzione fragile] Incoerenza di garanzie sul campo `name` tra Nova e API**: Nova impone `unique:customers,name` solo a livello di validazione form (nessun vincolo DB reale — l'unica unique key su `customers` è `wmpm_id`). Mitigazione: la generazione automatica dello slug (quando `name` è omesso) verifica esplicitamente l'unicità con una query (`Customer::where('name', $slug)->exists()`) prima di assegnare, con suffisso incrementale. **Aggiornamento post-review (wm-review-ticket, Finder 5):** la prima implementazione copriva solo questo path automatico — un `name` fornito esplicitamente dal chiamante veniva scritto senza alcun controllo di unicità, lasciando aperta l'incoerenza con Nova per il caso d'uso più realistico (uno script esterno che POSTa un `name` esplicito). Corretto aggiungendo `Rule::unique('customers', 'name')` in `CustomerApiRequest` (con `->ignore($this->route('customer'))` su `PATCH`, per non far fallire l'auto-conferma dello stesso nome) — ora un `name` esplicito duplicato è rifiutato con 422 sia in creazione sia in modifica, stessa garanzia di Nova su entrambi i path. Race condition tra richieste concorrenti sul path di auto-generazione resta accettata senza lock/transazione dedicata (rischio già esistente e accettato su Nova, scenario raro per questo tipo di endpoint).
3. **[Rischio architetturale] Codice senza precedente collaudato**: `Customer::create()` non ha mai un utilizzo esistente nel codebase (solo Nova/factory finora) e `Str::slug(..., '_')` non è mai stato usato con separatore underscore. Mitigazione: nessun cambiamento architetturale, ma buffer di novità di dominio all'estremo alto in stima, e test espliciti su mass-assignment corretto e collisione slug.
4. **[Worst case] Mass-assignment oltre la whitelist del ticket**: `Customer::$fillable` include campi di integrazione (`hs_id`, `wmpm_id`, `domain_name`, `associated_user_id`, ecc.) mai menzionati dal ticket. Mitigazione: `CustomerApiRequest::rules()` è whitelist stretta sui soli campi richiesti, controller usa sempre `$request->validated()` per il `fill()` (mai `$request->all()`), test dedicato che verifica l'ignoring silenzioso di un campo fuori whitelist.
5. **[Rollback] Basso rischio**: nessuna migration, nessuna modifica a endpoint esistenti (il fix `#[BodyParameter]` su Quote è solo metadata di documentazione). Rollback = revert del commit + rimozione rotte. Unico rischio: dipendenza precoce di uno strumento esterno dai nuovi endpoint.

## Out of scope

- `DELETE /customers` (dismissione gestita via `status`, come da ticket)
- `mobile_phone`: non è nella whitelist "campi scrivibili" del ticket e non è mai stato esposto nemmeno dalla `GET` esistente — resta editabile solo da Nova
- Hook/validazione lato `Quote` al passaggio di stato a `presented` (es. warning se il customer collegato non ha email): il ticket lo cita solo come motivazione business, non come requisito tecnico — resta responsabilità di uno strumento esterno (skill/script) che userà questi endpoint al momento giusto
- Vincolo di unicità a livello DB su `vat` o `name` (nessuno richiesto: solo warning applicativo su `vat`, deduplica applicativa su `name`)
- Endpoint dedicato annidato per aggiungere/rimuovere singole email (scelto invece il parametro `contact_emails_add` su `PATCH` esistente)
- Qualsiasi modifica a `dedoc/scramble` o al suo config: non è un bug della libreria, solo un limite noto di inferenza tipo su una regola `array` generica quando il contenuto reale è una mappa chiave/valore — risolto con un override mirato, non un fix strutturale
- Fix del degrado di `AlignTagsCommand` su match esatto di `email` per customer con più indirizzi (emerso in Fase: challenge, non richiesto da questo ticket — tracciato come debito noto, ticket dedicato da aprire a fine workflow)
- Vincolo DB `unique` su `customers.name` (deduplica resta solo applicativa lato API, nessuna modifica allo schema)

## Moduli toccati

- `app/Http/Controllers/Api/CustomerController.php` — nuovi metodi `store()`/`update()`, gestione `contact_emails`/`contact_emails_add`, generazione `name`, `warnings` su P.IVA duplicata
- `app/Http/Requests/Api/CustomerApiRequest.php` — nuovo, regole di validazione (email, phone, vat, status)
- `app/Http/Controllers/Api/QuoteController.php` — solo aggiunta `#[BodyParameter('additional_services', type: 'object', ...)]` su `store()`/`update()`, nessuna modifica di logica
- `routes/api.php` — nuove rotte `POST /customers`, `PATCH /customers/{customer}`
- `tests/Feature/Api/CustomerApiTest.php` — nuovo
