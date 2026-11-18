> Ticket: oc:8724

# Piano — Trattative: API delle attività del giorno precedente

Tutto nel repo principale `orchestrator`, nessun submodule. Riferimento: [overview.md](overview.md).

Regole che valgono per ogni task:

- identificatori in inglese, commenti e docblock in italiano;
- i test girano sul DB `orchestrator_test` (`docker exec php81_orchestrator php artisan test
  --filter=…`), mai su `orchestrator`; i test nuovi usano `DatabaseTransactions`;
- **la registrazione delle attività non deve mai far fallire il salvataggio** di trattative, task e prodotti: ogni
  scrittura in `quote_activities` è in un `try/catch` che chiama `report()` e prosegue;
- nessun commit durante l'esecuzione: i commit sono indicati alla fine, si fanno dopo il
  review-gate.

## Fatti verificati prima di scrivere il piano

- **Fuso orario.** L'app è in `Europe/Rome` (`config/app.php:85`), PostgreSQL è in UTC, ma Laravel
  scrive i timestamp come stringa nell'ora dell'app in colonne `timestamp without time zone`: alle
  15:07 italiane `now()` vale `2026-11-11 15:07:53` e il DB `14:07:53+00` (tinker, locale). Quindi
  `created_at` di `quote_activities` è già in ora italiana: i confini di un giorno si calcolano con
  `Carbon::parse($from, 'Europe/Rome')->startOfDay()` e si confrontano direttamente, senza
  conversioni. Il test del confine di mezzanotte lo conferma.
- **Prodotti.** Le relazioni sono `belongsToMany(...)->withPivot('quantity')`
  (`app/Models/Quote.php:70-79`), tabelle `product_quote` e `quote_recurring_product`. Punti di
  scrittura: `QuoteController.php:300,316,332,348` (`syncWithoutDetaching`, `detach`),
  `DuplicateQuote.php:41,47` (`sync`), Nova `BelongsToMany` (`app/Nova/Quote.php:334,353`, attach e
  detach). Con una classe pivot dichiarata con `->using()`, Eloquent fa passare `attach`, `detach`
  e `sync` dal modello pivot, che quindi lancia `created` e `deleted`: un solo aggancio vale per
  tutti i punti.
- **Note dei task.** `Task::appendNote` (`app/Models/Task.php:81`) mette la nota nuova in cima:
  `notes` nuovo = nota formattata + `notes` vecchio.
- **Campi tradotti** della Quote (`$translatable`, `Quote.php:54`): `title`, `notes`,
  `additional_info`, `delivery_time`, `payment_plan`, `billing_plan`, `additional_services`.
- **Rotte.** Prima di questo lavoro non esisteva un endpoint di attività: in `routes/api.php` c'erano solo le rotte CRUD di `/quotes` e `/tasks`.

## Task 1 — Migration e modello `QuoteActivity`

File: `database/migrations/2026_11_11_120000_create_quote_activities_table.php`,
`app/Models/QuoteActivity.php`.

Tabella `quote_activities`:

| Colonna | Tipo | Note |
|---|---|---|
| `id` | bigint | |
| `quote_id` | FK nullable → `quotes`, `nullOnDelete` | |
| `task_id` | FK nullable → `tasks`, `nullOnDelete` | solo per gli eventi dei task |
| `user_id` | FK nullable → `users`, `nullOnDelete` | vuoto = «Sistema» |
| `quote_title` | string nullable | copia al momento dell'evento |
| `task_title` | string nullable | copia al momento dell'evento |
| `user_name` | string nullable | copia al momento dell'evento |
| `event` | string | uno dei tipi fissi sotto |
| `changes` | json nullable | `{campo: {old, new}}`, oppure `{note}` / `{product_id, product_name}` |
| `created_at`, `updated_at` | timestamps | |

Indici: `(created_at)` e `(quote_id, created_at)`.

Tipi di evento, come costanti `QuoteActivity::EVENT_*` (non un enum di stato: non serve `label()`):
`quote_created`, `quote_updated`, `quote_deleted`, `product_added`, `product_removed`,
`recurring_product_added`, `recurring_product_removed`, `task_created`, `task_updated`,
`task_completed`, `task_reopened`, `task_note_added`, `task_deleted`.

Modello: `$fillable` con tutte le colonne tranne `id` e i timestamp, cast `changes` → `array`,
relazioni `quote()`, `task()`, `user()`. Un metodo statico `record(string $event, ?Quote $quote, array $attributes = [])` che ricava
`quote_id` e `quote_title` da `$quote`, riempie `user_id`/`user_name` da `auth()->user()` se non
passati, scrive la riga e in caso di eccezione chiama `report()` senza rilanciare. Per i pivot dei
prodotti c'è `recordProduct(?int $quoteId, string $productClass, ?int $productId, string $event)`,
che non lancia mai, come `record()`.

Relazione `Quote::activities()` (`hasMany`).

Verifica: `php artisan migrate` su `orchestrator_test`
(`DB_DATABASE=orchestrator_test php artisan migrate`) e sul DB locale.

## Task 2 — Attività della trattativa

File: `app/Observers/QuoteObserver.php`, registrato in `AppServiceProvider` accanto a
`Story::observe(...)` (`app/Providers/AppServiceProvider.php:54`).

- `created` → `quote_created`.
- `updated` → `quote_updated` se, tolti i campi esclusi, `getChanges()` non è vuoto. Esclusi:
  `pdf_links`, `template`, `created_at`, `updated_at` (overview, Requisiti). Per ogni campo:
  `old` da `getOriginal()`, `new` dal valore salvato.
- I campi in `$translatable` si registrano **decodificati**, come oggetto per lingua
  (`{"it": "…", "en": "…"}`), mai come stringa JSON: così restano leggibili.
- `deleted` → `quote_deleted`, con `quote_title` copiato.
- `quote_title` è sempre il titolo nella lingua dell'app
  (`getTranslation('title', app()->getLocale())`, con fallback; oggi `locale` è `it`).

Test (`tests/Feature/QuoteActivityLogTest.php`): creazione; modifica di stato, cliente, note
(prima/dopo leggibili); modifica dei soli `pdf_links` o `template` → nessuna riga; cancellazione
→ riga presente con titolo copiato e `quote_id` vuoto; utente autenticato → `user_name`; senza
utente → `user_id` vuoto.

## Task 3 — Attività dei prodotti

File: `app/Models/ProductQuote.php`, `app/Models/QuoteRecurringProduct.php` (estendono `Pivot`,
`$table` esplicita, `$incrementing = false` e `$timestamps = false`: le due tabelle hanno solo le
due FK e `quantity`, senza `id` né timestamp — `create_product_quote_table.php:14-18`), `Quote.php:70-79`
aggiunge `->using(...)` alle due relazioni.

- `created` del pivot → `product_added` / `recurring_product_added`, con `product_id` e nome del
  prodotto.
- `deleted` del pivot → `product_removed` / `recurring_product_removed`.
- `updated` (cambio di `quantity`) non si registra: l'overview chiede solo aggiunta e rimozione.

Prima di scrivere i modelli pivot, verificare con un test che `detach()` con `using()` passi
davvero dal modello (lo fa solo se la classe pivot è dichiarata): se non lancia `deleted`,
annotarlo in `notes.md` e agganciare la rimozione nei due punti di `QuoteController`.

Test (stesso file del task 2): un test per ogni punto di scrittura — API attach e detach di
prodotto e prodotto ricorrente, `DuplicateQuote`, attach Nova (via `attach()` sulla relazione,
come fa Nova). I totali esistenti (`QuoteGetTotalPriceTest`, `QuoteTotalsAttributesTest`) devono
restare verdi: `using()` non deve cambiare `pivot->quantity`.

## Task 4 — Attività dei task

> ⚠️ L'implementazione ha deviato da questo task: [notes.md](notes.md#task-4--test-dei-task-nello-stesso-file)

File: `app/Observers/TaskObserver.php`, registrato in `AppServiceProvider`.

- `created` → `task_created`.
- `updated`, da `getChanges()`:
  - `status` todo → completed → `task_completed`; completed → todo → `task_reopened`;
  - `notes`: se il valore nuovo finisce con il valore vecchio, la parte in più in testa è una nota
    aggiunta → `task_note_added` con `{note: strip_tags(parte aggiunta), trim}`; altrimenti
    rientra in `task_updated` con prima/dopo;
  - `title`, `due_date`, `assignee_id` → `task_updated` con prima/dopo;
  - `completed_at`, `creator_id` e i timestamp si ignorano (`completed_at` segue `status`,
    `creator_id` si fissa alla creazione).
  Più eventi dallo stesso `save()` producono più righe.
- `deleted` → `task_deleted`. La cancellazione a cascata con la trattativa avviene nel DB
  (`onDelete('cascade')`) e non passa dall'observer: resta fuori, come da Out of scope.
- `quote_id`, `quote_title`, `task_id`, `task_title` copiati dalla Quote e dal task.

Test (`tests/Feature/TaskActivityLogTest.php`): ogni evento; `appendNote` → solo la nota aggiunta,
senza HTML; riscrittura a mano delle note → `task_updated` con prima/dopo; task cancellato → riga
leggibile con `task_title`.

## Task 5 — Endpoint API

> ⚠️ L'implementazione ha deviato da questo task: [notes.md](notes.md#task-5--trattative-cancellate-sempre-incluse)

File: `app/Http/Controllers/Api/QuoteActivityController.php`, rotta in `routes/api.php` nel gruppo
`auth:sanctum`: `GET /quote-activities`. Un path a sé evita che `/quotes/{quote}` catturi
`activities` come id.

- `authorize('viewAny', Quote::class)`, come `QuoteController::index`.
- Validazione: `from` → `sometimes|nullable|date_format:Y-m-d`; `days` →
  `sometimes|nullable|integer|min:1`. Default: `from` = oggi, `days` = 1.
- Intervallo: da `Carbon::parse($from, 'Europe/Rome')->startOfDay()` a
  `->addDays($days)` escluso.
- Trattative incluse: quelle con almeno una riga nell'intervallo (anche cancellate, `quote_id`
  vuoto: si raggruppano per `quote_id` o, se vuoto, per riga), **escluse quelle chiuse prima
  dell'inizio**. Stato all'inizio dell'intervallo:
  1. l'ultimo `quote_updated` con `status` prima dell'inizio → il suo `new`;
  2. se non c'è, il primo `quote_updated` con `status` dall'inizio in poi → il suo `old`;
  3. se non c'è nessun cambio di stato fra le attività, lo stato attuale.
  Chiusa = `closed won` o `closed lost` (`QuoteStatus`).
- Risposta:

  ```json
  {
    "from": "2026-11-10",
    "days": 1,
    "data": [
      {
        "quote": {"id": 12, "title": "Preventivo Comune di X", "status": "presented",
                  "customer": {"id": 3, "name": "Comune di X"}, "deleted": false},
        "activities": [
          {"at": "2026-11-10T10:14:00+01:00", "event": "task_created",
           "user": {"id": 5, "name": "Alessio Piccioli"},
           "task": {"id": 40, "title": "Richiamare il cliente"},
           "changes": null}
        ]
      }
    ]
  }
  ```

  Utente vuoto → `"user": {"id": null, "name": "Sistema"}` (`__('System')`). Trattativa
  cancellata → `quote.id` null, `title` dalla copia, `status` e `customer` null, `deleted: true`.
  Trattative ordinate per l'attività più recente (dalla più recente), attività in ordine di ora.
- Scramble: `#[QueryParameter]` per `from` e `days` e docblock `@response`, come in
  `QuoteController::index`.

Test (`tests/Feature/Api/QuoteActivityApiTest.php`): senza parametri → oggi; `from` → solo quel
giorno; `days=3` → tre giorni; `from` errato e `days=0` → 422; confine di mezzanotte (attività alle
23:59 e alle 00:01); trattativa chiusa prima dell'intervallo esclusa; trattativa chiusa dentro
l'intervallo inclusa; trattativa cancellata presente con `deleted: true`; non autenticato → 401;
utente senza `viewAny` → 403. Verificare che `ApiDocsTest`/`QuoteApiDocsTest` restino verdi.

## Task 6 — Testi

> ⚠️ L'implementazione ha deviato da questo task: [notes.md](notes.md#task-6--nessuna-chiave-nuova)

`lang/it.json` e `lang/en.json`: chiave `System` → «Sistema» / «System». Nessun'altra stringa:
i tipi di evento sono codici fissi, non testi.

## Task 7 — Verifica finale

> ⚠️ L'implementazione ha deviato da questo task: [notes.md](notes.md#task-7--prova-manuale-non-eseguita)

- Suite completa su `orchestrator_test`; `StoryChildFieldTest` è flaky noto
  (`docs/howto/eseguire-i-test.md`).
- Prova manuale in locale: modificare una trattativa e un task da Nova, aggiungere un prodotto,
  chiamare `GET /api/quote-activities` con un token e leggere la risposta.

## Task 8 — Correzione dalla review del 17/11/2026 (campi tradotti)

> ⚠️ L'implementazione ha deviato da questo task: [notes.md](notes.md#task-8--lingue-vuote-anche-come-array-e-ordine-delle-lingue)

Bloccante della review sul commit `2f6eb5c`: `NovaTabTranslatable` salva tutte e cinque le lingue
(`it, en, fr, es, de`) e quelle vuote restano nel JSON come `null`. Il primo salvataggio da Nova di
una trattativa creata via API cambia quindi la stringa di `title`, `notes` e dei campi Tiptap anche
se il testo è identico, e `QuoteObserver` registra un `quote_updated` con prima e dopo uguali.

File: `app/Observers/QuoteObserver.php` (`fieldChanges`).

- Per i campi in `$translatable`, confrontare prima e dopo **dopo aver tolto le lingue con valore
  `null` o `''`**; se coincidono, il campo non si registra. Se restano diversi, si registra come
  oggi (oggetto per lingua decodificato).
- `TaskObserver` usa la stessa funzione, ma `Task` non ha campi tradotti: nessun effetto.

Test (`tests/Feature/QuoteActivityLogTest.php`): trattativa creata con `{"it": …}` su `title` e
`notes`, poi le altre lingue impostate a `null` come fa Nova, cambiando solo `status` → un solo
`quote_updated`, con il solo campo `status`. Prima il test va visto fallire, poi la correzione.

Documentazione: `overview.md`, Requisiti — togliere «priorità» dai campi registrati e aggiungere
`priority` ai campi scritti dal sistema (è in `IGNORED_FIELDS` dalla review del 16/11/2026).

Verifica: `QuoteActivityLogTest` e `QuoteActivityApiTest`, poi suite completa su
`orchestrator_test`.

Commit: `fix(oc:8724): campi tradotti registrati come modificati senza modifica`.

## Commit (dopo il review-gate, su conferma)

- `feat(oc:8724): registrazione delle attività su trattative, prodotti e task`
- `feat(oc:8724): API delle attività delle trattative per giorno`
- `docs(oc:8724): piano, note e pagina di conoscenza`
