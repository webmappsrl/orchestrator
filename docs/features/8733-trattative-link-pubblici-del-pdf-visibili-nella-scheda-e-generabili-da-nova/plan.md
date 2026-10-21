> Ticket: oc:8733

# Piano — Trattative: link pubblici del PDF visibili nella scheda e generabili da Nova

Tutto nel repo principale `orchestrator`, sul branch già esistente
`feature/oc-8733-trattative-link-pubblici-del-pdf-visibili-nella-scheda-e-generabili-da-nova`
(nessun submodule). Requisiti e motivazioni in [overview.md](overview.md), decisioni in
[notes.md](notes.md).

**Commit:** i commit indicati sotto sono istruzioni per la dev, non azioni da eseguire in autonomia.
Nessun `git add`/`git commit`/`git push` durante l'esecuzione.

**Test:** sempre sul DB `orchestrator_test`, dentro il container
(`docker exec php81_orchestrator php artisan test --filter=<Classe>`), mai con
`DB_DATABASE=orchestrator` — vedi [docs/howto/eseguire-i-test.md](../../howto/eseguire-i-test.md).
La migration va applicata anche al DB di test:
`docker exec php81_orchestrator bash -c "DB_DATABASE=orchestrator_test php artisan migrate"`.

**Merge con oc:8729:** quel ticket aggiunge `lenses()` vuoto in `app/Nova/ArchivedQuotes.php`, accanto
ad `actions()` che qui si modifica: al merge in `develop` il conflitto, se c'è, si risolve tenendo
entrambi i metodi.

---

## Task 1 — Colonna `pdf_links`

**File:** nuovo `database/migrations/2026_10_20_120000_add_pdf_links_to_quotes_table.php`,
`app/Models/Quote.php`

1. Migration: `$table->jsonb('pdf_links')->nullable();` su `quotes`, con `down()` che la rimuove.
2. In `Quote`: `'pdf_links' => 'array'` in `$casts`. Non aggiungerla a `$fillable` né a
   `$translatable`.
3. Applicare la migration al DB locale e al DB di test.

## Task 2 — Punto unico di scrittura

**File:** nuovo `app/Services/Quotes/QuotePdfLinks.php`, nuovo
`tests/Feature/QuotePdfLinksTest.php`

1. Test (con `DatabaseTransactions`, Quote create come in `QuotePdfApiTest`):
   - `generate()` restituisce `url` firmato verso `quotes.pdf.public` ed `expires_at`, e aggiunge un
     elemento a `pdf_links` con `url`, `lang`, `expires_at`, `created_at`, `created_by`;
   - due generazioni successive → due elementi, il primo invariato;
   - `updated_at` della Quote non cambia;
   - su una Quote `template = true`, il `template` delle Quote sorelle dello stesso cliente non cambia
     (nessun `save()`, come `link_pubblico_firmato_non_modifica_updated_at_della_quote`).
2. Servizio `QuotePdfLinks::generate(Quote $quote, string $lang, int $days, User $user): array`:
   - `$expiresAt = now()->addDays($days)`;
   - URL con `URL::temporarySignedRoute('quotes.pdf.public', $expiresAt, ['quote' => $quote->id, 'lang' => $lang])`;
   - append atomico con una sola istruzione SQL, senza `save()`:
     `DB::update("update quotes set pdf_links = coalesce(pdf_links, '[]'::jsonb) || ?::jsonb where id = ?", [json_encode([$entry]), $quote->id])`;
   - restituisce `['url' => …, 'expires_at' => $expiresAt]`.
3. Eseguire `--filter=QuotePdfLinksTest`: verde.

## Task 3 — L'endpoint usa il servizio

**File:** `app/Http/Controllers/Api/QuoteController.php`, `tests/Feature/Api/QuotePdfApiTest.php`

1. Test: `POST /api/quotes/{quote}/pdf-link` salva il link in `pdf_links` con `created_by` uguale
   all'utente; risposta, autorizzazione e validazione invariate (i test esistenti restano verdi).
2. In `pdfLink()`: sostituire la costruzione dell'URL con `QuotePdfLinks::generate()`, lasciando
   invariati `authorize`, il controllo Admin/Manager, `validate` e la forma `201 {url, expires_at}`.
   Aggiornare il docblock: il link ora viene salvato in `pdf_links`.
3. Eseguire `--filter=QuotePdfApiTest`: verde.

## Task 4 — Action Nova «Genera link pubblico»

**File:** nuovo `app/Nova/Actions/GenerateQuotePublicPdfLink.php`, `app/Nova/Quote.php`,
`app/Nova/ArchivedQuotes.php`, nuovo `tests/Feature/GenerateQuotePublicPdfLinkActionTest.php`

1. Test (sul modello di `ConvertStoryToTagActionTest`, chiamando `handle()` con `ActionFields`):
   - Admin genera un link su una Quote `new` → elemento in `pdf_links`, messaggio con URL e scadenza;
   - Admin genera un link su una Quote `closed won` → salvato;
   - `canSee` e `canRun` falsi per un Developer, veri per Admin e Manager;
   - l'action è presente in `Nova\Quote::actions()` e in `ArchivedQuotes::actions()`.
2. Action:
   - `fields()`: `Select` lingua (`it`, `en`, default `it`), `Number` giorni (min 1, max 90, default
     30, `rules('required','integer','min:1','max:90')`), `Heading` con l'avviso «I link già inviati
     restano validi fino alla loro scadenza: non esiste revoca».
   - `handle()`: `$quote = $models->sole()`; `QuotePdfLinks::generate(...)` con l'utente della
     richiesta; `return Action::message(...)` con URL e scadenza (`d/m/Y`).
   - nessuna coda (`ShouldQueue` no): l'URL deve tornare subito nel messaggio.
3. Registrazione, con `canSee` e `canRun` sul ruolo Admin/Manager e `->onlyOnDetail()` +
   `->showInline()` assente (una sola Quote alla volta):
   - `Nova\Quote::actions()` accanto a `DuplicateQuote`;
   - `ArchivedQuotes::actions()` accanto a `EditFields`.
4. Eseguire `--filter=GenerateQuotePublicPdfLinkActionTest`: verde.

## Task 5 — Link attivi e Storico nella tab Main

> ⚠️ L'implementazione ha deviato da questo task: [notes.md](notes.md#task-5--link-attivi-e-storico-nella-tab-main)

**File:** `app/Nova/Quote.php`, `lang/it.json`, `lang/en.json`, test nel file del Task 4 o in
`tests/Feature/QuoteNovaResourceTest.php`

1. Test:
   - un link scaduto non compare fra gli attivi ma compare nello storico;
   - i due campi hanno `canSee` falso per un Developer;
   - una copia con `DuplicateQuote` di una Quote con link ha `pdf_links` vuoto.
2. Nella tab `Main`, dopo `Google Drive Url`, due campi calcolati `Text::make(...)->asHtml()->onlyOnDetail()`
   con `canSee` sul ruolo Admin/Manager:
   - **«Active links»**: elementi con `expires_at` futura, «lingua, scade il gg/mm/aaaa» e URL
     cliccabile (`target="_blank"`), separati da « · »; «—» se non ce ne sono;
   - **«Links history»**: tutti gli elementi, dal più recente, con lingua, data di generazione, autore
     (nome dell'utente `created_by`, con un'unica query sugli utenti), scadenza e URL; `->help(__('The link always opens the current version of the quote.'))`.
   - URL e testi passati da `e()` prima di finire nell'HTML.
3. Traduzioni it/en per: nome dell'action, etichette dei campi, avviso, messaggio di conferma, help,
   «scade il».
4. Eseguire i test del task e `--filter=QuoteNovaResourceTest`: verdi.

## Task 6 — Suite completa e verifica manuale

1. `docker exec php81_orchestrator php artisan test`, sul DB di test.
2. In Nova locale, da Admin: generare un link da una Quote aperta e da una chiusa («Archived
   quotes»), aprire l'URL del messaggio in una finestra anonima, controllare Link attivi e Storico;
   da un utente Developer, verificare che action e campi non compaiano.

## Task 7 — Chiusura

1. Review gate di `wm-plan`.
2. `notes.md`: deviazioni, bug, decisioni.
3. Knowledge in `docs/knowledge/quote-api-e-pdf.md`: la scelta «link stateless» di oc:8291 passa in
   «Come ci siamo arrivati»; in «Stato attuale» `pdf_links`, il servizio unico, l'action e i campi.
4. Commit proposti (dopo il sì della dev):
   - `feat(oc:8733): storico dei link pubblici del PDF in pdf_links`
   - `feat(oc:8733): action Nova per generare il link e link attivi nella scheda`
   - `docs(oc:8733): piano, note e knowledge`
5. Aggiornamento del ticket su Orchestrator.
