> Ticket: oc:8731

# Piano — Trattative: referente esterno della trattativa

**Obiettivo:** campo facoltativo `customer_contacts` sulla Quote, con le persone del cliente da
contattare nel formato `Nome Cognome <email>` separate da virgola, validato con un'unica Rule in
Nova e nell'API.

**Repo:** tutto in `orchestrator`, nessun submodule.

**Test:** sempre nel container, sul DB `orchestrator_test`
([docs/howto/eseguire-i-test.md](../../howto/eseguire-i-test.md)):
`docker exec php81_orchestrator php artisan test --filter=<Classe>`.

**Commit:** i comandi di commit indicati sono istruzioni per la dev, da eseguire solo dopo la sua
conferma esplicita e dopo il review-gate. Durante l'esecuzione non si fa nessun `git add`,
`git commit` o `git push`.

---

## Task 0 — Allineare il branch a `develop`

> ⚠️ L'implementazione ha deviato da questo task: [notes.md](notes.md#task-0-rebase-invece-di-merge)

Il branch è fermo a `d9b63ec`, cioè 6 commit dietro `origin/develop`. Fra quei commit ci sono
oc:8733 e oc:8815, che toccano `Quote.php`, `Nova/Quote.php`, `QuoteController.php` e
`docs/knowledge/quote-api-e-pdf.md`.

1. Con la conferma della dev: `git merge origin/develop`. Il merge crea un commit, quindi serve la
   sua approvazione.
2. Controllare che l'unico file del ticket già presente sul branch, la cartella `docs/features/8731-…`,
   non vada in conflitto: `develop` non la contiene.

## Task 1 — Colonna e modello

**File:**
- nuovo `database/migrations/2026_10_26_120000_add_customer_contacts_to_quotes_table.php`
- `app/Models/Quote.php`

1. Migration sul modello di `2026_10_12_120000_add_lost_reason_to_quotes_table.php`:
   `$table->text('customer_contacts')->nullable();` in `up()`, `dropColumn('customer_contacts')`
   in `down()`.
2. `'customer_contacts'` in `$fillable`, dopo `'lost_reason'`. Il campo non va in `$translatable` e
   non ha cast.
3. Eseguire la migration sui due DB:
   - `docker exec php81_orchestrator php artisan migrate`
   - `docker exec php81_orchestrator bash -c "DB_DATABASE=orchestrator_test php artisan migrate"`
4. Verificare la reversibilità: `migrate:rollback --step=1` seguito da `migrate`, **solo sul DB di
   test**.

## Task 2 — Rule `CustomerContacts`

> ⚠️ L'implementazione ha deviato da questo task: [notes.md](notes.md#task-2-posizione-del-test)

**File:**
- nuovo `app/Rules/CustomerContacts.php`
- nuovo `tests/Unit/CustomerContactsRuleTest.php`

Prima i test, poi la Rule.

1. **Test** (`Validator::make(['customer_contacts' => $value], ['customer_contacts' => ['nullable', new CustomerContacts()]])`):
   - accettati:
     - `null` e `''`;
     - `Mario Rossi <m.rossi@comune.pisa.it>`;
     - due persone separate da `, `;
     - una persona per riga dopo la virgola, con spazi e a capo ai bordi;
     - `"Rossi, Mario" <m.rossi@comune.pisa.it>`, che conta come una persona sola;
     - un nome fra virgolette senza virgola;
   - rifiutati, con un messaggio che nomina la persona sbagliata:
     - `m.rossi@comune.pisa.it`, senza nome;
     - `<m.rossi@comune.pisa.it>`, con il nome vuoto;
     - `Mario Rossi <m.rossi@>`, con un'email non valida;
     - `Mario Rossi <m.rossi@comune.pisa.it> extra`, con testo dopo `>`;
     - un a capo dentro una persona (`Mario\nRossi <a@b.it>`);
   - rifiutati per la struttura dell'elenco:
     - una virgola finale, `Mario <a@b.it>,`, che crea una persona vuota;
     - due virgole di fila;
     - virgolette non chiuse, `"Rossi, Mario <a@b.it>`;
   - un valore che non è una stringa (un array dall'API) viene rifiutato.
2. **Rule**, che implementa `ValidationRule`, sullo stile di `SafeRichTextHtml`:
   - se il valore è `null` o `''`, la Rule esce subito: dell'obbligatorietà si occupa `nullable`;
   - se il valore non è una stringa, la Rule fallisce;
   - lo `split()` divide sulle virgole fuori dalle virgolette, scorrendo il testo carattere per
     carattere, e dentro le virgolette riconosce `\"` come virgoletta scritta e non come chiusura.
     Se alla fine le virgolette restano aperte, la Rule fallisce con il messaggio sulle virgolette
     non chiuse;
   - per ogni pezzo, si tolgono spazi e a capo ai bordi con `trim($piece, " \t\r\n")`:
     - se resta vuoto, la Rule fallisce con il messaggio della persona vuota e la sua posizione
       (1, 2, …);
     - poi si applica la regex ancorata `/\A(?<name>"(?:[^"\\]|\\.)*"|[^"<>\r\n]*?)[ \t]*<(?<email>[^<>\s]+)>\z/u`:
       se non corrisponde, il pezzo non è nella forma `Nome <email>` e la Rule fallisce. Questo
       copre anche il testo dopo `>` e l'a capo interno;
     - se il nome, tolte le virgolette, resta vuoto, la Rule fallisce con il messaggio del nome
       mancante;
     - se `new \Symfony\Component\Mime\Address($email)` solleva `RfcComplianceException`, l'email
       non è valida e la Rule fallisce;
   - nei messaggi la persona passa da `QuoteRichText::protectPlaceholders()`, perché un `:` nel nome
     non deve essere scambiato per un segnaposto del validator;
   - la Rule non modifica mai il valore.
3. `docker exec php81_orchestrator php artisan test --filter=CustomerContactsRuleTest`

## Task 3 — Testi

**File:** `lang/en.json`, `lang/it.json`

Le chiavi sono in inglese, come le altre, e vanno in tutti e due i file:

| Chiave | it |
|---|---|
| `Customer contacts` | `Contatti del cliente` |
| `People to contact at the customer for this quote, as Name Surname <email>, separated by commas.` | `Persone del cliente da contattare per questa trattativa, nel formato Nome Cognome <email>, separate da virgola.` |
| `The :attribute field must be a text.` | `Il campo :attribute deve essere un testo.` |
| `The :attribute field has an empty contact at position :position.` | `Il campo :attribute ha un contatto vuoto in posizione :position.` |
| `The :attribute field has an unclosed quote.` | `Il campo :attribute ha delle virgolette non chiuse.` |
| `The contact ":contact" is not in the format Name <email>.` | `Il contatto «:contact» non è nel formato Nome <email>.` |
| `The contact ":contact" has no name.` | `Il contatto «:contact» non ha un nome.` |
| `The contact ":contact" has an invalid email address.` | `Il contatto «:contact» ha un indirizzo email non valido.` |

Verificare che i due JSON restino validi: `jq empty lang/it.json lang/en.json`.

## Task 4 — API

> ⚠️ L'implementazione ha deviato da questo task: [notes.md](notes.md#task-4-descrizione-nel-body-api)

**File:**
- `app/Http/Requests/Api/QuoteApiRequest.php`
- `app/Http/Controllers/Api/QuoteController.php`
- `tests/Feature/Api/QuoteApiTest.php`
- `tests/Feature/Api/QuoteApiDocsTest.php`

1. **Test** in `QuoteApiTest`, con lo stile già usato per `lost_reason`:
   - `POST /api/quotes` con un `customer_contacts` valido: 201, il valore è nella risposta e nel DB
     identico a come è stato scritto;
   - `PATCH` di una Quote esistente: il valore cambia;
   - `PATCH` con `customer_contacts: null`: il valore si azzera;
   - `PATCH` senza la chiave: il valore resta quello di prima;
   - `POST` con un valore malformato: 422 con `assertJsonValidationErrors('customer_contacts')`;
   - `GET /api/quotes/{quote}`: la risposta contiene la chiave, anche quando vale `null`.
2. **Test** in `QuoteApiDocsTest`: lo schema di risposta di `GET /quotes/{quote}` documenta
   `customer_contacts`.
3. `QuoteApiRequest::rules()`: aggiungere
   `'customer_contacts' => ['sometimes', 'nullable', 'string', new CustomerContacts()]`, con un
   commento di una riga: «persone del cliente da contattare, solo per questa trattativa (oc:8731)».
4. `formatQuote()`: aggiungere `'customer_contacts' => $quote->customer_contacts,` dopo
   `lost_reason`.
5. Le 8 annotazioni `@response` del controller (righe 44, 132, 217, 242, 285, 301, 317 e 333 di
   `develop`): aggiungere `customer_contacts: string|null` dopo `lost_reason: string|null`. Va
   fatto in modo mirato, perché la riga 44 contiene due shape. Alla fine,
   `grep -c 'customer_contacts: string|null'` deve dare 9 occorrenze, come `lost_reason`.
6. Docblock di `store()` e `update()`: una riga che dica che `customer_contacts` vale solo per la
   trattativa e non è il referente del Customer.
7. `docker exec php81_orchestrator php artisan test --filter='QuoteApiTest|QuoteApiDocsTest'`

## Task 5 — Nova

**File:**
- `app/Nova/Quote.php`
- `tests/Feature/QuoteNovaResourceTest.php`

1. **Test** in `QuoteNovaResourceTest`:
   - con `flattenFields()` e `fieldPosition()`, il campo `customer_contacts` viene subito dopo il
     `BelongsTo` `customer`;
   - il campo non si vede nell'elenco (`isShownOnIndex` è falso) e si vede nel dettaglio e nei form;
   - nel dettaglio è un `Textarea` con `alwaysShow`;
   - la validazione Nova rifiuta un valore malformato e accetta un valore valido e uno vuoto. Per
     farlo si generalizza `validateQuoteUpdate()` con un parametro `$attribute`, che vale
     `'lost_reason'` per default così i test esistenti restano invariati.
2. **Campo**, subito dopo il `BelongsTo::make(__('Customer'), …)` nella tab Main:

   ```php
   // Persone del cliente da contattare per questa trattativa, non il referente del Customer (oc:8731).
   Textarea::make(__('Customer contacts'), 'customer_contacts')
       ->nullable()
       ->hideFromIndex()
       ->alwaysShow()
       ->rules('nullable', new CustomerContacts())
       ->help(__('People to contact at the customer for this quote, as Name Surname <email>, separated by commas.')),
   ```

3. `docker exec php81_orchestrator php artisan test --filter=QuoteNovaResourceTest`
4. Verifica a mano in Nova, sulla trattativa di sviluppo:
   - il campo compare sotto il Cliente;
   - un valore sbagliato mostra l'errore tradotto;
   - un valore valido si vede nel dettaglio senza «Show content».

## Task 6 — Pagina di conoscenza

**File:** `docs/knowledge/quote-api-e-pdf.md`

1. Nuova sottosezione `### Contatti del cliente sulla trattativa (oc:8731)` sotto
   `## Stato attuale`, sullo stile di «Motivo della chiusura persa». Deve dire:
   - colonna, formato e regole della Rule;
   - che il dato è della trattativa e non del Customer;
   - API `sometimes` + `nullable`;
   - che la chiave sconosciuta viene ignorata con 200, quindi l'orchestrator va rilasciato prima di
     attivare il campo nella skill;
   - che «Duplica» copia il campo.
2. In `## Perché così`, se la pagina ha questa sezione, il motivo della Rule nostra al posto di
   `Address::create()`.

## Task 7 — Verifica finale

1. `docker exec php81_orchestrator php artisan test --filter=Quote`
2. `docker exec php81_orchestrator php artisan test --filter=CustomerContactsRuleTest`
3. `git diff --stat`: deve contenere solo i file elencati in «Moduli toccati» dell'overview, più la
   nuova migration e il file di test.

## Commit (dopo il review-gate, con la conferma della dev)

```
feat(oc:8731): contatti del cliente sulla trattativa, in Nova e nell'API
```

Poi push del branch e PR verso `develop`. Va decisa una cosa: aggiornare la PR #271, che oggi
contiene solo la documentazione, oppure aprirne una nuova.
