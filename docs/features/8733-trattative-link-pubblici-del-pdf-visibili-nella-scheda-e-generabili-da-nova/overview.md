> Ticket: oc:8733

# Trattative: link pubblici del PDF visibili nella scheda e generabili da Nova

## Cosa cambia

Ogni link pubblico firmato al PDF di un preventivo viene salvato sulla Quote, in un nuovo campo
`pdf_links` (jsonb) che ne conserva lo storico completo. Il link si può generare anche da Nova,
con una nuova action, oltre che dall'endpoint `POST /api/quotes/{quote}/pdf-link` già usato dalla
skill Claude. Nella tab principale della Quote, Admin e Manager vedono i link ancora attivi e lo
storico completo.

## Perché

Oggi il link firmato non viene salvato da nessuna parte (scelta di oc:8291, link stateless): una
volta generato «vive solo nelle mail» e, se serve di nuovo, «si va a cercare la mail» (Giuseppe
Bonfanti, scrum del 13/10/2026). I link possono essere più di uno, per esempio uno per lingua,
quindi vanno conservati tutti: «invece di fare una stringa facciamo un Jason B e ci mettiamo tutte
quelle che ci sono» (stessa call). Inoltre oggi un link si genera solo via API, cioè solo dalla
skill Claude: Nova deve restare utilizzabile da sola.

## Requisiti

**Dati**
- [ ] Migration che aggiunge a `quotes` la colonna `pdf_links` di tipo `jsonb`, nullable.
- [ ] Ogni elemento di `pdf_links` ha `url`, `lang`, `expires_at`, `created_at`, `created_by`
      (id utente). Gli elementi si aggiungono in coda, nessuno viene sovrascritto o rimosso.
- [ ] Nel model `Quote`: cast `array`; `pdf_links` **non** è in `$fillable` (non si scrive da
      `PATCH /api/quotes/{id}` né dal form Nova) e **non** è fra i campi `$translatable`.
- [ ] Duplicare una trattativa (action `DuplicateQuote`) crea una copia senza link: i link
      dell'originale aprono il PDF dell'originale e non devono comparire fra i Link attivi della
      copia. Oggi lo garantisce il fatto che `pdf_links` non è in `$fillable`
      (`app/Nova/Actions/DuplicateQuote.php:31-35` copia con `toArray()` + `Quote::create()`).

**Un solo punto di scrittura**
- [ ] Un metodo condiviso genera l'URL con `URL::temporarySignedRoute('quotes.pdf.public', …)` e
      aggiunge l'elemento a `pdf_links`. Lo usano sia l'endpoint API sia l'action Nova.
- [ ] L'aggiunta è atomica (append lato database), così due generazioni contemporanee non si
      perdono a vicenda, e non passa da `save()`: non scatena gli hook `saving` della Quote e non
      aggiorna `updated_at` (generare un link non è una modifica del preventivo).
- [ ] L'endpoint `POST /api/quotes/{quote}/pdf-link` mantiene identici autorizzazione,
      validazione e risposta (`201 {url, expires_at}`): cambia solo che il link viene salvato.

**Action Nova «Genera link pubblico»**
- [ ] Sulla risorsa Quote, visibile ed eseguibile solo da Admin e Manager (stesso controllo
      dell'endpoint), **anche sulle trattative chiuse** (`closed won`, `closed lost`). Servono sia
      `canSee` sia `canRun` con il controllo sul ruolo: senza `canRun` Nova ricade su
      `QuotePolicy::update()`, che rifiuta le trattative chiuse
      (`vendor/laravel/nova/src/Actions/ActionModelCollection.php:28-31`,
      `app/Policies/QuotePolicy.php:47-52`).
- [ ] L'action è registrata anche sulla risorsa `ArchivedQuotes` (menu «Archived quotes»), da cui
      si aprono le trattative chiuse: l'elenco «Quotes» le esclude (`app/Nova/Quote.php:405-412`) e
      `ArchivedQuotes::actions()` sostituisce le action ereditate con il solo «Edit»
      (`app/Nova/ArchivedQuotes.php:39-44`). Così Nova si comporta come l'endpoint, che genera link
      anche sulle trattative chiuse.
- [ ] L'action agisce su una sola Quote alla volta (`->sole()`): non si lancia su una selezione
      multipla dall'elenco.
- [ ] Campi del popup: lingua (`it`/`en`, default `it`), durata in giorni (1–90, default 30), e
      un avviso che i link già inviati restano validi fino alla loro scadenza (non esiste revoca).
- [ ] Alla conferma salva il link tramite il metodo condiviso e mostra l'URL da copiare, con la
      scadenza, nel messaggio di conferma di Nova (`Action::message`); il link compare poi fra i
      Link attivi della tab principale.

**Visualizzazione nella tab principale della Quote (solo Admin e Manager, solo nel dettaglio)**
- [ ] **Link attivi:** i link non scaduti, con lingua, scadenza e URL cliccabile
      (es. «it, scade il 06/11 · en, scade il 07/11»).
- [ ] **Storico:** l'elenco completo, scaduti compresi, con data di generazione e autore, e la
      frase d'aiuto «Il link apre sempre la versione attuale del preventivo».
- [ ] Chi non è Admin o Manager non vede nessuno di questi campi.

**Traduzioni e test**
- [ ] Le stringhe nuove hanno la chiave inglese e la traduzione in `lang/it.json` e
      `lang/en.json`.
- [ ] Test: l'endpoint salva il link; l'action salva il link, anche su una Quote `closed won` aperta da `archived-quotes`; un Developer non vede l'action né i
      campi; un link scaduto non compare fra gli attivi ma resta nello storico; la copia di
      una trattativa con link ha `pdf_links` vuoto.

## Rischi

- **I link generati prima del rilascio non compaiono.** Non sono mai stati salvati e non si
  possono ricostruire: lo storico parte dal deploy.
- **Gli URL salvati sono credenziali di accesso al PDF.** Chi legge il database o un suo backup
  può aprire i PDF dei link non ancora scaduti. Mitigazione: in Nova li vedono solo Admin e
  Manager, come oggi l'endpoint; il campo non è esposto nell'API.
- **Il link apre sempre la versione attuale del preventivo, non quella mandata.** Il PDF è
  generato al click dai dati correnti della Quote (`QuotePublicController.php:21-25`, già così da
  oc:8291): se il preventivo viene corretto dopo l'invio, il cliente vede la versione nuova. Lo
  storico registra quando e da chi è stato generato un link, non quale versione è stata inviata.
  Mitigazione: sotto lo «Storico» in Nova una frase d'aiuto lo dichiara («Il link apre sempre la
  versione attuale del preventivo»).
- **Hook `saving` della Quote.** Un `save()` su una Quote con `template = true` aggiorna le Quote
  sorelle dello stesso cliente. Mitigazione: la scrittura del link è un update diretto della sola
  colonna, senza `save()`.

## Out of scope

- **Esposizione dei link via API** (`GET /api/quotes/{id}?include=pdfLinks`). Il ticket non la
  chiede e oggi nessun consumatore la usa. Se una skill ne avrà bisogno, si aggiunge `pdfLinks` a
  `ALLOWED_INCLUDES` di `QuoteController::show()`, con il filtro Admin/Manager, il docblock
  `@response` di `show` aggiornato e un test in `QuoteApiDocsTest`. È un'aggiunta a richiesta, che
  non rompe i client esistenti.
- **Revoca del singolo link** prima della scadenza.
- **Bottone copia** sull'URL, nel popup di conferma o nella scheda: il ticket non lo chiede. Se
  l'action verrà usata spesso al posto della skill, si può aggiungere un popup dedicato
  (`Action::modal()` con un componente in `nova-components/`) senza toccare i dati salvati.

## Moduli toccati

Tutto nel repo principale `orchestrator`; nessun submodule coinvolto.

- `database/migrations/<data>_add_pdf_links_to_quotes_table.php` — nuovo
- `app/Models/Quote.php` — cast e metodo condiviso (o service dedicato in `app/Services/`)
- `app/Http/Controllers/Api/QuoteController.php` — `pdfLink()` usa il metodo condiviso
- `app/Nova/Actions/GenerateQuotePublicPdfLink.php` — nuovo
- `app/Nova/Quote.php` — action e campi nella tab Main
- `app/Nova/ArchivedQuotes.php` — l'action accanto a «Edit»
- `lang/it.json`, `lang/en.json`
- `tests/Feature/Api/QuotePdfApiTest.php`, nuovo test per l'action e la visibilità Nova
- `docs/knowledge/quote-api-e-pdf.md` — la scelta «link stateless» di oc:8291 è superata
