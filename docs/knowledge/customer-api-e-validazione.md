# Customer: API delle anagrafiche e validazione dei telefoni

## Stato attuale

### API Customers (oc:8505, oc:8291)
`GET /api/customers` e `GET /api/customers/{customer}` read-only, più `POST /api/customers` e
`PATCH /api/customers/{customer}` (il `PATCH` è sempre sulla singola risorsa). Autorizzazione
Admin/Manager invariata rispetto al resto dell'API.
- **Whitelist anti mass-assignment per assegnazione esplicita, campo per campo: mai `fill()` né
  `$request->all()`.** `Customer::$fillable` espone molti campi (`hs_id`, `wmpm_id`, `domain_name`,
  `associated_user_id`, `subscription_*`, `score_*`, `contract_*`) che l'API non deve poter
  scrivere. `CustomerController::applyWritableFields()` legge da `$request->validated()` — a sua
  volta vincolato dalla whitelist stretta di `CustomerApiRequest::rules()` — e assegna un attributo
  alla volta: nessun path di scrittura raggiunge quei campi, qualunque cosa contenga il payload.
- **`name` è opzionale in POST** e viene generato da `company_name` con
  `Str::slug($company_name, '_')` (underscore, coerente con lo stile già a DB), con suffisso
  numerico incrementale su collisione verificata da una query esplicita. Nessun lock né
  transazione dedicata: la race fra richieste concorrenti è accettata consapevolmente (rischio già
  presente su Nova, scenario raro su questo endpoint).
- **Anche un `name` fornito esplicitamente è protetto dai duplicati**, con
  `Rule::unique('customers', 'name')` (e `->ignore($this->route('customer'))` su `PATCH`, per non
  far fallire l'auto-conferma dello stesso nome). È una garanzia **applicativa**: `customers.name`
  non ha alcun indice unique reale a DB.
- **`contact_emails` (replace-all) e `contact_emails_add` (append) sono mutuamente esclusivi** nello
  stesso payload `PATCH`, validati in `CustomerApiRequest::withValidator()`. La colonna `email`
  resta testo comma-separated, coerente con `Customer::getContactEmailsAttribute()`.
- **Warning P.IVA duplicata solo su `POST`, mai su `PATCH`**: non esiste vincolo di unicità (la
  stessa P.IVA può appartenere legittimamente a più schede, es. una DMC), quindi la creazione
  restituisce un campo `warnings` non bloccante coi customer che la condividono. Un `PATCH` che
  rende una P.IVA duplicata non segnala nulla — asimmetria intenzionale, coerente col testo del
  ticket, non estesa a `update()`.
- **Comando `customers:backfill-vat`**: dry-run di default (il dato è derivato da una regex su testo
  libero `heading`), `--apply` per scrivere, report sempre salvato in
  `storage/app/customer-vat-backfill/` **prima** di ogni apply. La regex distingue la Partita IVA
  (11 cifre) dal Codice Fiscale di persona fisica (16 alfanumerici), che non va **mai** scritto in
  `vat`. Eseguito realmente sul DB principale: 39/52 customer con `heading` aggiornati (rilevamento
  di oc:8505; non ricostruibile oggi — in locale i customer sono 147, di cui 73 con `heading`:
  locale, 2026-09-12).
- I campi Nova `vat` e `address` sono stati aggiunti a posteriori su richiesta esplicita. Attenzione:
  la chiave di traduzione `"VAT"` era già usata per l'etichetta IVA del PDF preventivo, quindi
  l'etichetta del campo usa `"VAT Number"` — in un JSON con chiavi duplicate vince l'ultima, e la
  sovrascrittura sarebbe silenziosa.

### Validazione di `phone` e `mobile_phone` (oc:8412, oc:8505)

Entrambi i campi passano dalla stessa validazione **nel form Nova**; via API è scrivibile e
validato **solo `phone`**.
- **Validazione nativa, nessuna dipendenza esterna**: `App\Nova\Customer::isValidPhoneFragment()` fa
  un controllo strutturale di plausibilità — whitelist di caratteri più conteggio cifre (8-15 con
  prefisso `+` esplicito, secondo i limiti E.164 generali; 6-11 senza prefisso, assumendo IT). Non è
  una validazione semantica per paese: limite noto e accettato.
- **`Customer::normalizePhoneString()` è `public` e va richiamata prima di validare**: gestisce lo
  spazio non-breaking e gli altri separatori Unicode `\p{Z}`, che la vecchia regex tollerava. È
  l'unica fonte di verità fra persistenza (mutator `setPhoneAttribute`/`setMobilePhoneAttribute`) e
  validazione pre-save.
- **La validazione si applica solo se il valore è davvero cambiato** rispetto al DB (confronto su
  valori normalizzati, non sulla stringa raw): un Customer con un numero legacy che non passerebbe
  la nuova regola resta modificabile su altri campi. Necessario perché Nova valida l'intero form a
  ogni save, non solo i campi toccati.
- **I frammenti vuoti dopo lo split per virgola** (virgola finale, doppia virgola, valore di soli
  separatori) vengono scartati **senza errore**: intenzionale, evita errori criptici su input
  sporchi da copia-incolla, coperto da test espliciti in `CustomerPhoneValidationTest`.
- **Via API si valida solo `phone`**: `CustomerApiRequest` ne duplica la logica di plausibilità
  invece di condividerla con la Nova Resource — scelta deliberata per tenere minimo il diff di
  oc:8505. **`mobile_phone` non compare in `CustomerApiRequest`**: non è né validato né scrivibile
  via API, esiste solo nel form Nova.

## Come ci siamo arrivati

- **Prima versione della normalizzazione dentro la Nova Resource** (oc:8412): non gestiva NBSP e
  separatori Unicode, regressione rispetto alla vecchia regex. Emersa in seconda review; invece di
  duplicare la logica si riusa il metodo del modello.
- **Lo stesso bug si è ripresentato in oc:8505**: la prima implementazione aveva dimenticato la
  chiamata a `normalizePhoneString()`, causando un 422 su numeri con NBSP che Nova invece accetta.
  Trovato in review formale (`wm-review-ticket`), corretto richiamandola in testa a
  `phoneValidationError()`.
- **Trovato in review formale anche il `name` esplicito duplicato** (oc:8505): la prima
  implementazione copriva solo il path di auto-generazione.
- **Bug critico trovato nei test manuali, non dalla review** (oc:8505): `phone` inviato come array
  causava un `TypeError` non gestito (500). Laravel invoca ogni regola di validazione
  indipendentemente dai fallimenti precedenti (nessun `bail`), quindi la closure con
  `phoneValidationError(?string $value)` veniva comunque chiamata con un array. Raggiungibile da
  **qualsiasi utente autenticato**, perché la FormRequest gira prima del controllo di ruolo nel
  controller. Fix: guardia di tipo in testa alla closure.
- **Duplicazione boilerplate fra i campi `phone` e `mobile_phone` in `fields()`**: segnalata due
  volte in review, non risolta per tenere minimo il diff — candidata a un helper `phoneField()` in
  un ticket di manutenzione.
