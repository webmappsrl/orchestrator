> Ticket: oc:8536

# Piano — API: lista story con filtri, log delle modifiche e ordine degli stati

Riferimento: `docs/features/8536-api-lista-story-filtri-log-ordine-stati/overview.md` (approvata).

**Nota sui commit**: ogni step sotto corrisponde a un commit separato con scope `feat(oc:8536): ...`
(o `fix(oc:8536)`/`refactor(oc:8536)` se applicabile). I commit sono istruzioni testuali per lo
sviluppatore — nessun commit viene eseguito automaticamente durante l'esecuzione del piano.

**Decisione trasversale (da Fase: challenge, applicata a tutti gli step sui log)**: un log in
`story_logs` è considerato "modifica" solo se il campo `changes` **non** contiene nessuna delle
chiavi `watch`, `tag_attached`, `tag_detached`. Questa esclusione si applica sia alla formattazione
dei log restituiti sia al filtro `changed_by`/`changed_from`/`changed_to` (una story con solo log di
tipo "watch" in un intervallo non deve comparire come "modificata" in quell'intervallo).
Implementazione: `whereRaw("NOT (changes::jsonb ?| array['watch','tag_attached','tag_detached'])")`
senza bindings (il `?` dell'operatore Postgres va lasciato letterale, senza parametri posizionali
nella stessa query raw, altrimenti Laravel lo interpreta come placeholder — verificato in Fase:
reverse-interaction).

---

## Step 1 — `GET /api/stories`: filtri sulle proprietà della story, paginazione, sort

**File**: `routes/api.php`, `app/Http/Controllers/Api/StoryController.php`

1. Route `Route::get('/stories', [StoryController::class, 'index']);` nel gruppo `auth:sanctum`.
2. `StoryController::index(Request $request)`:
   - `$this->authorize('viewAny', Story::class)` in apertura (allinea Story alla convenzione già
     usata da `Tag`/`Task`/`Quote`, `StoryPolicy::viewAny()` ritorna già `true`).
   - Query base `Story::query()->with('tags')`.
   - Filtri story: `status` (accetta singolo valore o array — `whereIn` se array, `where` se
     scalare), `type`, `user_id`, `creator_id`, `created_from`/`created_to` (su `created_at`),
     `updated_from`/`updated_to` (su `updated_at`). Nessun filtro applicato se il parametro è
     assente — nessuna esclusione implicita di stati.
   - Filtro `tag_id`: `whereHas('tags', fn ($q) => $q->where('tags.id', $request->integer('tag_id')))`.
   - Sort: `$request->get('sort')` in `['created_at', '-created_at', 'updated_at', '-updated_at',
     'status', '-status']`; per `status`/`-status` **fallback silenzioso su `created_at` discendente**
     se il metodo d'ordine di `StoryStatus` non esiste ancora (Task 3 bloccato — vedi Step 6); per
     qualunque altro valore (incluso omesso) fallback su `created_at` discendente + `id` discendente
     come tie-breaker.
   - Paginazione sempre attiva: `$request->integer('per_page', 25)`, `$query->paginate($perPage)`.
   - Risposta `{data: [...], meta: {current_page, per_page, total, last_page}}` — costruita a mano
     dal `LengthAwarePaginator` (non il default Laravel, che aggiungerebbe anche `links`).
3. `formatStoryListItem(Story $story): array` (privato, stesso pattern di `formatStory()` già
   esistente): `id`, `name`, `type`, `status`, `created_at`, `updated_at`, `user_id`, `creator_id`,
   `hours`, `tags` (`{id, name}`). Nessun `description`/`customer_request` di default.
4. Parametro `with=description`: se presente, aggiungi `description`/`customer_request` all'item
   formattato (nessun eager-load aggiuntivo necessario, sono colonne dirette del modello).

**Verifica manuale**: `GET /api/stories`, `GET /api/stories?status[]=todo&status[]=progress`,
`GET /api/stories?per_page=5&page=2`, `GET /api/stories?with=description` contro il container
`php81_orchestrator`.

---

## Step 2 — Filtri sulle modifiche (`changed_by`, `changed_from`, `changed_to`) e `with=logs`

**File**: `app/Http/Controllers/Api/StoryController.php`

1. Filtro `changed_by`/`changed_from`/`changed_to`: `whereHas('storyLogs', function ($q) use
   ($request) { /* esclusione watch/tag_attached/tag_detached */ if ($request->filled('changed_by'))
   ...; if ($request->filled('changed_from')) $q->whereDate('created_at', '>=', ...); if
   ($request->filled('changed_to')) $q->whereDate('created_at', '<=', ...); })`. Applica sempre
   l'esclusione (vedi nota trasversale in cima al piano), anche quando nessun `changed_*` è passato,
   così `with=logs` senza filtri non mostra comunque le voci "watch"/tag.
2. Parametro `with=logs`: se presente, `$story->storyLogs` filtrato dagli **stessi** `changed_*`
   (stessa esclusione + stessi bound temporali) e mappato a `{at: created_at formattato "Y-m-d H:i",
   user_id, changes}`, ordinato cronologicamente. Se `with=logs` è assente, nessuna chiave `logs`
   nella risposta (non null, proprio assente — evita di suggerire un contratto vuoto).
3. Verifica: senza `with=logs` nessuna query aggiuntiva su `story_logs` per riga (evitare N+1 quando
   il parametro non è richiesto).

**Test (esplicito dal ticket)**: story assegnata a A, modificata da B → `changed_by=B` la
restituisce, `user_id=B` no.

**Test (esplicito dal ticket)**: story modificata in due giorni diversi, richiesta con
`changed_from`/`changed_to` su un solo giorno e `with=logs` → solo i log di quel giorno nella
risposta, nessuno fuori intervallo.

**Test aggiuntivo (da Fase: challenge)**: una story con solo log "watch"/tag nell'intervallo
richiesto non compare nei risultati di `changed_by`/`changed_from`/`changed_to`, e non compare nei
suoi `logs` annidati.

---

## Step 3 — `GET /api/stories/{story}/logs`

**File**: `routes/api.php`, `app/Http/Controllers/Api/StoryController.php`

1. Route `Route::get('/stories/{story}/logs', [StoryController::class, 'logs']);`.
2. `StoryController::logs(Request $request, Story $story)`:
   - `$this->authorize('view', $story)`.
   - Query sui log della story, stessa esclusione watch/tag della nota trasversale, **nessun**
     filtro `changed_*` (non è lo scopo di questo endpoint).
   - Paginazione sempre attiva (stesso default 25, stesso formato `{data, meta}`).
   - Ordine cronologico (`created_at` ascendente — storico, non lista di eventi recenti).
   - Stessa formattazione log di Step 2: `{at, user_id, changes}`.

**Verifica manuale**: `GET /api/stories/{id}/logs`, `GET /api/stories/{id}/logs?per_page=5&page=2`.

---

## Step 4 — Documentazione Scramble

**File**: `app/Http/Controllers/Api/StoryController.php`

1. Blocco `@response` su `index()` e `logs()` con la forma completa `{data: array<...>, meta:
   array{current_page: int, per_page: int, total: int, last_page: int}}`, seguendo il modello di
   `TaskController.php`.
2. `#[QueryParameter(...)]` per **ogni** parametro di `index()`: `status`, `type`, `tag_id`,
   `user_id`, `creator_id`, `created_from`, `created_to`, `updated_from`, `updated_to`, `changed_by`,
   `changed_from`, `changed_to`, `sort`, `per_page`, `page`, `with`. Nella `description` di
   `user_id`/`changed_by` va esplicitata la differenza (assegnatario attuale vs autore della
   modifica) — è il punto che il ticket segnala come più a rischio di fraintendimento.
3. `#[QueryParameter(...)]` per `per_page`/`page` su `logs()`.
4. Verifica che `StoryController` resti sotto `App\Http\Controllers\Api\` (già lo è) perché Scramble
   lo documenti (`AppServiceProvider.php:62-74`).

**Verifica manuale**: `/docs/api` mostra i due nuovi endpoint con tutti i parametri annotati.

---

## Step 5 — Test suite

**File**: `tests/Feature/Api/StoryApiTest.php` (o file dedicato, es. `StoryIndexApiTest.php` /
`StoryLogsApiTest.php` se il file esistente è già corposo — verificare a inizio step)

Coprire, oltre ai due test già indicati negli step precedenti:

- Filtro `status` singolo valore e multi-valore (array)
- Filtro `type`, `user_id`, `creator_id`, `tag_id`
- Filtro `created_from`/`created_to`, `updated_from`/`updated_to`
- Nessun filtro applicato → nessuna esclusione implicita (compaiono anche `done`/`rejected`/`released`)
- Paginazione: `per_page`, `page`, forma `{data, meta}` con valori corretti
- Sort: `created_at`, `-created_at`, `updated_at`, `-updated_at`, default, valore sconosciuto →
  fallback su default
- `with=description` aggiunge i campi, assente di default
- `GET /api/stories/{story}/logs`: storico paginato, ordine cronologico, esclusione watch/tag
- Autorizzazione: richiesta senza token → 401 su entrambi gli endpoint

---

## Step 6 — Task 3: metodo d'ordine su `StoryStatus` (BLOCCATO — richiede conferma CTO)

**Non eseguire questo step finché non arriva conferma esplicita dal CTO** sull'ordine proposto
nell'overview (`backlog, new, assigned, todo, progress, testing, tested, pending_release, released`
+ `waiting`/`done`/`rejected` terminali) — in particolare sulla posizione di `done` rispetto a
`released`, e sulle due contraddizioni trovate in Fase: challenge (Kanban mette `waiting` in
sequenza; `StoryMetricsCalculator::FORWARD_STATUSES` mette `done` dopo `released`).

**File**: `app/Enums/StoryStatus.php`, `app/Http/Controllers/Api/StoryController.php`

1. Metodo `sortOrder(): int` su `StoryStatus`, `match` esaustivo (stesso pattern di `label()`/
   `color()`), secondo l'ordine confermato dal CTO.
2. In `index()`, quando `sort=status`/`sort=-status`: ordina in memoria dopo il fetch (l'ordine non
   è un valore di colonna ordinabile via SQL diretto) oppure con una `CASE WHEN` SQL costruita dai
   valori dell'enum — scegliere in base al volume atteso per pagina (con paginazione a 25 per
   pagina, l'ordinamento SQL via `CASE WHEN` è preferibile per correttezza cross-pagina; l'ordinamento
   in memoria post-fetch romperebbe la paginazione).
3. Rimuovere il fallback silenzioso introdotto nello Step 1 per `sort=status`, ora che il metodo
   esiste.

**Test**: `sort=status` restituisce le story nell'ordine di flusso confermato, non alfabetico;
`sort=-status` inverte l'ordine.

---

## Checklist finale

- [ ] Step 1-5 completati e testati (indipendenti da Task 3)
- [ ] Step 6 eseguito solo dopo conferma CTO esplicita, altrimenti resta bloccato e documentato come
      tale in `notes.md`
- [ ] Tutti i nuovi metodi annotati Scramble, verificati su `/docs/api`
- [ ] Nessun commit eseguito automaticamente — commit singoli per step, a conferma dello sviluppatore
