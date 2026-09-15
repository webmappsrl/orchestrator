> Ticket: oc:8536

# API: lista story con filtri, log delle modifiche e ordine degli stati

## Cosa cambia

L'API di Orchestrator espone oggi le Story solo per ID singolo (`show`, `store`, `update`). Questa
feature aggiunge:

- **Task 1** — `GET /api/stories`: lista paginata sotto `auth:sanctum`, con filtri sulle proprietà
  della story (`status` multi-valore, `type`, `tag_id`, `user_id`, `creator_id`, range date
  `created_from`/`created_to`, `updated_from`/`updated_to`) e, distintamente, filtri sulle modifiche
  registrate in `story_logs` (`changed_by`, `changed_from`/`changed_to`). Risposta paginata sempre
  con `{data, meta}` (default 25 per pagina), ordinabile per `created_at`, `updated_at`, `status`
  (default: `created_at` discendente, `id` come tie-breaker). Resource dedicata alla lista, senza
  `description`/`customer_request` salvo `with=description`; tag espansi con `id`/`name`.
  `with=logs` annida i log filtrati dagli stessi `changed_*`.
- **Task 2** — `GET /api/stories/{story}/logs`: storico paginato di una singola Story, stessa
  resource dei log del Task 1, nessun filtro `changed_*`.
- **Task 3** — metodo sull'enum `StoryStatus` che restituisce la posizione nel flusso, usato da
  `sort=status`. Ordine proposto (**da confermare col CTO**, vedi Rischi): `backlog`, `new`,
  `assigned`, `todo`, `progress`, `testing`, `tested`, `pending_release`, `released`, con `waiting`,
  `done` e `rejected` come terminali fuori sequenza.
- **Documentazione OpenAPI** via `dedoc/scramble`: annotazioni `@response` e
  `#[QueryParameter(...)]` su ogni metodo, seguendo il modello di `TaskController.php`.

**Entrambi gli endpoint di log (Task 1 con `with=logs` e Task 2) escludono di default le voci di
`story_logs` che non rappresentano un cambio di campo**: le voci di tracciamento visualizzazione
scritte da `LogStory` middleware (`changes: {"watch": ...}`) e le voci relazionali di
attach/detach tag scritte da `TagController` (`changes: {"tag_attached"/"tag_detached": ...}`).

## Perché

Le domande quotidiane sul lavoro del team ("quali ticket sono stati aperti oggi", "cosa ha
modificato un dev ieri", "quali story sono ferme in `pending_release` da quanto tempo") richiedono
oggi di leggere le Story una per una indovinandone gli ID — i dati esistono già (`story_logs`
traccia ogni modifica dal luglio 2024), manca solo l'esposizione via API. Il consumatore sono le
skill `wm-skills`, che oggi non hanno un modo efficiente di rispondere a queste domande.

## Requisiti

- [ ] `GET /api/stories` filtra su proprietà story: `status` (multi-valore), `type`, `tag_id`,
      `user_id` (assegnatario attuale), `creator_id`, `created_from`/`created_to`,
      `updated_from`/`updated_to` — nessun filtro applicato di default (nessuna esclusione
      implicita di `done`/`rejected`/`released`)
- [ ] `GET /api/stories` filtra distintamente su `story_logs`: `changed_by` (autore modifica),
      `changed_from`/`changed_to` (intervallo temporale della modifica) — una story compare se ha
      almeno un log corrispondente
- [ ] Risposta paginata sempre `{data, meta}`, default 25 per pagina, ordinabile per `created_at`,
      `updated_at`, `status` (via il metodo d'ordine di Task 3); default `created_at` discendente
      con `id` come tie-breaker
- [ ] Resource di lista dedicata: `id`, `name`, `type`, `status`, `created_at`, `updated_at`,
      `user_id`, `creator_id`, `hours`, `tags` (espansi `id`+`name`) — `description` e
      `customer_request` solo con `with=description`
- [ ] `with=logs` annida i log della story, filtrati dagli stessi `changed_*` della query, **esclusi**
      i log di tipo "watch" e "tag_attached"/"tag_detached"
- [ ] `GET /api/stories/{story}/logs`: storico paginato, stessa resource dei log, nessun filtro
      `changed_*`, stessa esclusione "watch"/"tag_attached"/"tag_detached"
- [ ] Metodo d'ordine su `StoryStatus` per `sort=status`, secondo l'ordine proposto sopra
      (**bloccato da conferma CTO**, vedi Rischi)
- [ ] `sort=status` prima che il metodo d'ordine esista (Task 3 non ancora confermato/implementato):
      fallback silenzioso sul default (`created_at` discendente), stessa logica già usata da
      `QuoteController::index()` per valori di `sort` non riconosciuti — Task 1/2 non dipendono dal
      completamento di Task 3
- [ ] Annotazioni Scramble (`@response`, `#[QueryParameter(...)]`) complete su ogni metodo nuovo,
      controller collocato sotto `App\Http\Controllers\Api\`
- [ ] Test: distinzione `user_id` (assegnatario) vs `changed_by` (autore modifica) — story assegnata
      ad A, modificata da B, verificare `changed_by=B` la restituisce e `user_id=B` no
- [ ] Test: log annidati rispettano i filtri `changed_from`/`changed_to` — nessuna riga fuori
      intervallo nella risposta

## Rischi

- **Ordine degli stati (Task 3) da confermare col CTO**, in particolare la posizione di `done`
  rispetto a `released` — richiesta esplicita del ticket ("chiedi prima di scrivere il metodo, non
  dopo"). Il piano procede col Task 3 bloccato finché non arriva conferma esplicita; Task 1 e 2 non
  dipendono da questo e procedono indipendentemente. **Trovate in Fase: challenge due contraddizioni
  da portare al CTO insieme alla domanda**: la Kanban (`app/Nova/Dashboards/Kanban.php:86-92`) mostra
  `waiting` **in sequenza** fra `Progress` e `Test`, non come terminale fuori sequenza come proposto
  qui; `StoryMetricsCalculator::FORWARD_STATUSES` (riga 18) tratta già `done` come **successivo** a
  `released`, l'opposto dell'assunzione più intuitiva.
- **`story_logs` non ha indici oltre alla chiave primaria** (verificato sul DB locale: 19.410 righe,
  nessun indice su `story_id`/`user_id`). I filtri `changed_by` e `changed_from`/`changed_to`
  introdotti da questa feature faranno scansioni complete della tabella. Segnalato e tracciato in
  **oc:8537**, non risolto in questo ciclo (una migration su una tabella già grande va programmata a
  parte).
- ~~`hours` nella resource di lista rischia N+1~~ — **verificato falso in Fase: challenge**: `hours`
  è una colonna persistita su `stories`, aggiornata da `Story::save()` (righe 99-104) ad ogni cambio
  di stato. La lista legge `$story->hours` così com'è, senza alcun ricalcolo — stesso comportamento
  già in uso da `StoryController::show()`.
- **Nessuna autorizzazione dichiarata sui nuovi endpoint**: `StoryController` oggi non ha alcun
  controllo (`show`/`store`/`update` non chiamano `authorize()`/`abort`), a differenza di
  `TagController`/`TaskController`/`QuoteController`. `index` aggiunge
  `$this->authorize('viewAny', Story::class)` (nessuna Story specifica in gioco); `logs` aggiunge
  `$this->authorize('view', $story)` (opera su un'istanza precisa, mirror di `show()`) — entrambe
  ritornano già `true` per ogni utente autenticato in `StoryPolicy`, quindi non cambia chi vede cosa
  oggi, ma evita che i nuovi endpoint restino gli unici scoperti. Nota a margine (fuori scope,
  nessun ticket aperto): i token Sanctum non scadono mai, quindi un endpoint di lista rende più
  comodo sfruttare un token vecchio o compromesso rispetto a dover indovinare gli ID uno per uno —
  rischio preesistente, non introdotto da questa feature, di cui il dev è consapevole.
- **`per_page` non validato poteva disabilitare la paginazione o superarla senza limite** — trovato
  in Fase: execution (review formale `wm-review-ticket`) e corretto: `per_page` è ora clampato a
  `[1, 100]` (`resolvePerPage()`), coprendo sia il caso negativo (Eloquent ignora un `LIMIT`
  negativo, restituendo l'intera tabella) sia l'assenza di un tetto massimo.
- **Filtri data non validati causavano un 500 non gestito** su input malformato (es.
  `created_from=not-a-date` arrivava non validato fino a Postgres) — trovato e corretto: ogni
  filtro data passa ora da `validatedDateFilter()`, che risponde 422 su formato non `Y-m-d`.
- **`tag_id`/`user_id`/`creator_id`/`changed_by` non numerici venivano convertiti silenziosamente a
  `0`** (nessuna riga corrispondente, nessun segnale d'errore) — trovato in review e corretto con
  `validatedIntFilter()`, stesso pattern del fix sulle date: risponde 422 su input non intero.
- **`with=logs` generava una query per ogni story della pagina** (N+1) — trovato in review e
  corretto: le righe di `story_logs` vengono ora eager-caricate una sola volta per l'intera pagina
  (`Collection::load()`, un `whereIn` unico) invece che per-story dentro `formatStoryListItem()`.
  Verificato empiricamente: il conteggio delle query resta costante al variare di `per_page`.
- **Quarta implementazione divergente della regola "cos'è un log di modifica vera"**, trovata in
  review: `SendWaitingStoryReminder.php:75` usa un conteggio di chiavi (`count($changes) > 1`)
  invece di un controllo su chiavi specifiche come in questo controller. Le due logiche coincidono
  oggi per coincidenza (nessun writer produce log con chiavi miste), ma **non centralizzate in
  questo ciclo** — richiederebbe toccare un comando estraneo allo scope di questo ticket con test
  dedicati. Lasciato come follow-up esplicito (vedi `notes.md`).
- **Confusione fra `user_id` (assegnatario) e `changed_by` (autore modifica) è l'errore più
  probabile**: una persona modifica story assegnate ad altri come caso normale. Mitigato dal test
  esplicito richiesto nei Requisiti.
- **`changed_from`/`changed_to` filtrano su `story_logs.created_at`, non su `viewed_at`**: verificato
  sui dati che `viewed_at`, per i log di cambio campo scritti da `Story::save()`, è troncato al
  minuto (`now()->format('Y-m-d H:i')`), mentre per i log di visualizzazione (`LogStory` middleware)
  ha precisione al secondo — stessa colonna, precisione incoerente a seconda di chi scrive la riga.
  `created_at` è automatico, sempre preciso al secondo, ed è già il campo che
  `StoryTimeService::getStoryProgressDaysMinutes()` usa per ordinare i log. Il nome `viewed_at`
  tradisce inoltre l'origine (tracciamento visualizzazioni), riusato per i cambi di campo.
- **Il contratto esterno, una volta pubblicato, è difficile da disfare senza coordinamento
  cross-repo** (nessun versionamento API nel repo, es. `/v2`): la paginazione sempre attiva con
  default 25 (renderla opt-in dopo sarebbe un breaking change), l'ordine di `sort=status` (se
  cambiato dopo la conferma CTO, altera silenziosamente cosa vede chi già lo consuma) e
  l'esclusione di default dei log "watch"/"tag_attached"/"tag_detached" (includerli in futuro
  richiede un parametro opt-in, non un cambio di default) sono tre decisioni da considerare
  definitive appena le skill `wm-skills` iniziano a consumarle — non un blocco per procedere, ma un
  promemoria per essere sicuri prima di pubblicare la documentazione Scramble, non dopo.

## Out of scope

- Aggiunta di indici su `stories`, `story_logs` e `taggables` (tracciato in oc:8537)
- Registrazione del valore precedente negli storici (`changes` registra solo il nuovo valore) —
  cambierebbe il comportamento di scrittura esistente, materia di un ticket a sé
- Contenuto reale delle note di sviluppo cambiate (`description` nel log resta `'change description'`
  letterale, comportamento voluto)
- Vincoli sulle transizioni di stato permesse (Task 3 introduce solo un ordine per l'ordinamento)
- Allegati/Media Library, endpoint Epic/Milestone, revoca automatica dei token (già fuori scope
  dell'API esterna in generale)

## Moduli toccati

- `routes/api.php` — nuove rotte `GET /api/stories`, `GET /api/stories/{story}/logs`
- `app/Http/Controllers/Api/StoryController.php` — nuovi metodi `index`, `logs`
- Nuova API Resource dedicata alla lista Story (e ai log), distinta da quella di `show`
- `app/Enums/StoryStatus.php` — nuovo metodo per l'ordine di flusso
- `config/scramble.php` / annotazioni nei controller — documentazione OpenAPI
