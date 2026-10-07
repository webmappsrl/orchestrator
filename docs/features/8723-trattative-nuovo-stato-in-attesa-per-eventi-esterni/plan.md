> Ticket: oc:8723

# Piano — Trattative: nuovo stato «in attesa» per eventi esterni

Spec: [overview.md](overview.md). Tutto nel repo principale, nessun submodule. Branch:
`feature/oc-8723-trattative-nuovo-stato-in-attesa-per-eventi-esterni` (PR in bozza #260).

I comandi PHP girano nel container `php81_orchestrator`; i test sul DB `orchestrator_test`, mai su
`orchestrator` (`docs/howto/eseguire-i-test.md`). I commit indicati sono istruzioni per il dev:
nessun `git commit` senza la sua conferma esplicita.

---

## Task 1 — Test sull'enum `QuoteStatus` (prima del codice)

> ⚠️ L'implementazione ha deviato da questo task: [notes.md](notes.md#task-1-e-3-chiavi-di-traduzione-in-tre-forme)

**File:** `tests/Feature/QuoteStatusTest.php` (nuovo), sul modello di
`tests/Feature/PendingReleaseStatusTest.php:50` e `:79`.

1. Classe `Tests\Feature\QuoteStatusTest extends TestCase`, senza DB (non serve
   `DatabaseTransactions`).
2. `test_on_hold_esiste_fra_presented_e_waiting_for_order`: `QuoteStatus::On_Hold->value === 'on hold'`
   e, negli indici di `QuoteStatus::cases()`, `On_Hold` sta subito dopo `Presented` e subito prima
   di `Waiting_For_Order` (fissa l'ordine delle colonne del Kanban).
3. `test_ogni_case_ha_label_e_colore`: per ogni case, `label()` stringa non vuota, `color()` che
   rispetta `/^#[0-9A-Fa-f]{6}$/`.
4. `test_on_hold_ha_il_colore_azzurro`: `QuoteStatus::On_Hold->color() === '#0EA5E9'`.
5. `test_ogni_case_ha_le_chiavi_di_traduzione_in_it_e_en`:
   - imposta una lingua inesistente, così `label()` restituisce la chiave grezza:
     ```php
     app()->setLocale('zz');
     app('translator')->setFallback('zz');
     ```
   - per ogni case: `$labelKey = $case->label()`, `$nameKey = $case->name`;
   - per `it` ed `en`, legge `lang/{$locale}.json` con `json_decode(..., true)` e verifica
     `assertArrayHasKey($labelKey, ...)` e `assertArrayHasKey($nameKey, ...)`, con messaggi che
     nominano chiave, case e file;
   - ripristina locale e fallback in `tearDown()` (o con `try/finally`), per non sporcare gli altri
     test;
   - un commento di due righe spiega perché si usa `zz` (decisione in `notes.md`).
6. Esegui: `docker exec php81_orchestrator php artisan test --filter=QuoteStatusTest`.
   **Atteso: fallisce** (`On_Hold` non esiste).

   Nota: se l'esecuzione rivela chiavi mancanti per stati **già esistenti**, è un difetto
   preesistente: annotalo in `notes.md` e chiedi al dev se correggerlo qui.

## Task 2 — Nuovo case, `label()` e `color()`

**File:** `app/Enums/QuoteStatus.php`

1. Aggiungi `case On_Hold = 'on hold';` fra `Presented` e `Waiting_For_Order`.
2. In `color()`: `self::On_Hold => '#0EA5E9',` fra le righe di `Presented` e `Waiting_For_Order`.
3. In `label()`: `self::On_Hold => __('On Hold'),` nella stessa posizione. Il `match` resta senza
   `default`.

## Task 3 — Traduzioni

> ⚠️ L'implementazione ha deviato da questo task: [notes.md](notes.md#task-1-e-3-chiavi-di-traduzione-in-tre-forme)

**File:** `lang/it.json`, `lang/en.json`

1. `lang/it.json`, accanto alle chiavi degli altri stati (zona `"Presented"` / `"Waiting For Order"`,
   righe ~389-391):
   ```json
   "On Hold": "In attesa di esito",
   "On_Hold": "In attesa di esito",
   ```
2. `lang/en.json`, nella zona corrispondente (righe ~642-644):
   ```json
   "On Hold": "On Hold",
   "On_Hold": "On Hold",
   ```
3. Controlla che le chiavi non esistano già altrove nei due file (`grep -n '"On Hold"\|"On_Hold"'`)
   e che i JSON restino validi (`php -r 'json_decode(file_get_contents("lang/it.json"), true, 512, JSON_THROW_ON_ERROR);'`, idem `en`).
4. Esegui `--filter=QuoteStatusTest`. **Atteso: passa.**

## Task 4 — `loadingWhen` dell'elenco trattative

**File:** `app/Nova/Quote.php` (righe ~147-153)

1. Aggiungi `QuoteStatus::On_Hold->value,` all'array di `loadingWhen`, dopo `Presented`.
2. Nessun'altra modifica in Nova: Select del form (`:174`), `QuoteStatusFilter`, `indexQuery` e
   Kanban prendono gli stati da `cases()` o escludono solo le chiuse.

## Task 5 — Test del filtro `status` di `GET /api/quotes` (prima del codice)

> ⚠️ L'implementazione ha deviato da questo task: [notes.md](notes.md#task-5-test-del-filtro-in-più-rispetto-al-piano)

**File:** `tests/Feature/Api/QuoteApiTest.php`, accanto a `index_filtra_per_piu_status` (`:418`).

1. `index_filtra_per_status_singolo`: crea una Quote `On_Hold` e una `Presented`;
   `GET /api/quotes?status=on hold` (con `http_build_query`) → 200, contiene la prima, non la seconda.
2. `index_con_status_sconosciuto_risponde_422`: `?status=xyz` → 422 con errore su `status`.
3. `index_con_status_in_elenco_sconosciuto_risponde_422`: `?status[]=new&status[]=xyz` → 422 con
   errore su `status.1`.
4. Il test esistente `index_filtra_per_piu_status` resta invariato e deve continuare a passare
   (protegge la forma elenco).
5. Esegui `--filter=QuoteApiTest`. **Atteso:** i due test 422 falliscono (oggi 200), gli altri
   passano.

## Task 6 — Validazione del filtro `status`

> ⚠️ L'implementazione ha deviato da questo task: [notes.md](notes.md#task-6-7-validazione-e-openapi-del-filtro-status)

**File:** `app/Http/Controllers/Api/QuoteController.php`, metodo `index()` (righe ~55-70)

1. All'inizio di `index()`, dopo `authorize`, valida:
   ```php
   $request->validate([
       'status'   => ['sometimes', 'nullable'],
       'status.*' => [Rule::enum(QuoteStatus::class)],
   ]);
   ```
   e la stringa singola con `Rule::enum` quando non è un array. Forma da scegliere in
   implementazione, verificando che:
   - `?status=on hold` passi, `?status=xyz` dia 422;
   - `?status[]=new&status[]=presented` passi, `?status[]=xyz` dia 422;
   - `?status=` vuoto continui a essere ignorato come oggi (`$request->filled('status')`).
2. Aggiungi gli `use` mancanti (`Illuminate\Validation\Rule`, `App\Enums\QuoteStatus` se assente).
3. Esegui `--filter=QuoteApiTest`. **Atteso: tutto passa.**

## Task 7 — Documentazione OpenAPI del filtro

> ⚠️ L'implementazione ha deviato da questo task: [notes.md](notes.md#task-6-7-validazione-e-openapi-del-filtro-status)

1. Verifica cosa genera Scramble per `status` di `GET /api/quotes`: l'attributo esistente
   `#[QueryParameter('status', ..., type: 'string|array<string>')]` (`:46`) potrebbe avere la
   precedenza sulla regola di validazione e nascondere l'elenco dei valori. Controllo:
   `docker exec php81_orchestrator php artisan scramble:export` (o la pagina `/docs/api`) e cerca il
   parametro `status`.
2. Se l'elenco dei valori **compare**: nessuna modifica all'attributo, oppure solo alla descrizione
   per dire che un valore sconosciuto risponde 422.
3. Se **non compare**: adatta l'attributo (per esempio togliendo `type` o rimuovendolo, se Scramble
   ricava tutto dalla validazione) finché l'elenco compare, **senza** scrivere i valori a mano
   (decisione di review). Annota in `notes.md` la soluzione adottata.

## Task 8 — Suite e verifica manuale

1. Suite completa: `docker exec php81_orchestrator php artisan test`. Ricorda che
   `StoryChildFieldTest` è flaky e non dipende da questo lavoro (`docs/howto/eseguire-i-test.md`).
2. Verifica manuale in Nova, in italiano e in inglese (cambio lingua utente):
   - Kanban `Sales`: colonna azzurra «In attesa di esito» / «On Hold» fra «Presentata» e «In
     attesa di ordine», con conteggio e somma; drag & drop di una trattativa dentro e fuori;
     larghezza delle 8 colonne su schermo da portatile;
   - elenco trattative: la trattativa spostata compare fra le aperte (non in archivio), con
     l'icona «in corso» e non quella verde di «completato»;
   - filtro per stato: voce tradotta, non `On_Hold`;
   - form: la Select propone il nuovo stato tradotto; scheda: stato tradotto.
3. API: `GET /api/quotes?status=on%20hold` → 200; `?status=xyz` → 422.

## Task 9 — Notes, conoscenza, ticket

1. `notes.md` (già creato, con il follow-up su `crm.go`): aggiungere deviazioni e decisioni prese
   in implementazione (forma della validazione, soluzione per Scramble).
2. `docs/knowledge/traduzioni-stati-enum.md`: aggiungere che `QuoteStatus` si traduce in **tre**
   forme — nome del case (`QuoteStatusFilter`), etichetta di `label()` (Kanban, elenco, scheda,
   form) e **valore salvato nel DB** (card «Quotes by Status», `DynamicPartitionMetric` con
   `__($key)`) — e che la forma `ucfirst()` del valore per `QuoteStatus` non serve. Riportare
   l'errore fatto in questo lavoro (forma del valore tolta perché ritenuta non letta) come
   motivazione. Proporre al dev, se serve, la precisazione della regola nel `CLAUDE.md`.
3. Nota per la PR e il ticket: **cambio di contratto dell'API** — `GET /api/quotes?status=<valore
   sconosciuto>` passa da 200 con elenco vuoto a 422.

## Commit (proposta, solo dopo conferma del dev)

- `feat(oc:8723): nuovo stato On_Hold delle trattative con traduzioni e colore` — Task 1-4
- `feat(oc:8723): validazione del filtro status di GET /api/quotes` — Task 5-7
- `docs(oc:8723): piano, note e conoscenza sulle traduzioni degli stati` — plan, notes, knowledge
