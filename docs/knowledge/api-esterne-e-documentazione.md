# API REST esterne e documentazione OpenAPI

La superficie `/api/*` costruita per le skill Claude e per i client esterni, e come viene
documentata.

## Stato attuale

### Autenticazione e Story (oc:7961, oc:7974)
- `POST /api/auth/login` restituisce un token Sanctum personale; tutto il resto passa da
  `auth:sanctum`. Ogni scrittura resta quindi tracciata per autore.
- `GET /api/stories/{id}`, `POST /api/stories`, `PATCH /api/stories/{id}`: il `PATCH` aggiorna
  **solo i campi passati**, non fa mai un replace. La validazione è in `StoryApiRequest`, con regole
  enum per `status` e `type`; i campi CRUD sono allineati all'edit form developer di Nova.
- **Il `PATCH` non è un `fill($validated)` unico**: `StoryController.php:70-90` fa un
  `array_intersect_key` del validato contro la whitelist
  `['name','status','type','user_id','tester_id','creator_id','parent_id']` e passa **solo quella**
  a `fill()`; `estimated_hours` viene assegnato a parte, mentre `customer_request` e `description`
  hanno un path proprio — `addResponse()` e `addDevNote()`.
- **Attenzione: un `PATCH` su `customer_request` ha un effetto verso l'esterno.** Passa da
  `Story::addResponse()`, che **notifica il cliente**: non è una scrittura di campo reversibile.
  Non usarlo per prove, test manuali o correzioni di comodo su dati reali.
- Le scritture passano dagli observer del modello invece di replicare la logica Nova (così
  `attachAutoTags` scatta anche via API).
- Fuori scope e tuttora assenti: allegati e Media Library, endpoint per Epic/Milestone
  (i Customer erano nella stessa lista e sono arrivati dopo, vedi
  [Customer: API e validazione](customer-api-e-validazione.md)), revoca automatica dei token.
- **Il rate limiting invece c'è**: `app/Http/Kernel.php:46` applica `throttle:api` al gruppo `api`,
  e `RouteServiceProvider.php:48-50` lo definisce a **60 richieste/minuto**, per `user()->id` o in
  fallback per IP.
- `GET /api/me` restituisce id, name ed email dell'utente autenticato ed è una **closure inline in
  `routes/api.php`**, non un controller dedicato: accettato consapevolmente perché il progetto non
  usa `php artisan route:cache` in produzione.
- **Per revocare un token compromesso**: `$user->tokens()->delete()` via tinker, non esiste un
  endpoint dedicato. Il token emesso non ha scadenza né ability granulari, e questo dettaglio è
  intenzionalmente **fuori** dal docblock pubblico di `AuthController::login()`, che finisce su
  `/docs/api`: offrirebbe ricognizione gratuita a un attaccante.

### Lista Story, log delle modifiche e ordine degli stati (oc:8536)
- `GET /api/stories` è il primo `index` su Story: filtri **su due livelli distinti**, tenuti
  volutamente separati — proprietà della story (`status` multi-valore, `type`, `tag_id`, `user_id`
  come assegnatario attuale, `creator_id`, range `created_*`/`updated_*`) contro proprietà delle sue
  modifiche registrate (`changed_by` come autore, `changed_from`/`changed_to`). **Confondere
  `user_id` con `changed_by` è l'errore più probabile e il più silenzioso**: una persona modifica
  di continuo story assegnate ad altri, quindi il risultato resta plausibile anche se il filtro
  sbagliato viene applicato.
- **Un log in `story_logs` non è sempre una "modifica"**: la tabella contiene anche righe di
  tracciamento visualizzazione (`LogStory` middleware, `changes: {"watch": ...}`, ~38% delle righe
  su dati reali) e di attach/detach tag (`TagController`, `changes: {"tag_attached"/"tag_detached"}`).
  `changed_by`/`changed_from`/`changed_to` e `with=logs` le escludono sempre
  (`StoryController::LOG_IS_CHANGE_SQL`, via `jsonb_exists()` su Postgres). **Attenzione:** esiste
  già altrove (`Story.php:614`, `effectiveMinutesForStory()`) una diversa forma per lo stesso
  genere di problema (`changes::jsonb ?? 'status'`), e una quarta logica con criterio diverso
  (conteggio di chiavi, non chiavi specifiche) vive in `SendWaitingStoryReminder.php:75` — le tre
  non sono centralizzate, coincidono oggi solo perché nessun log ha chiavi miste.
- **Paginazione sempre attiva** (`{data, meta}`, default 25, clampata a `[1, 100]`) — a differenza
  di `QuoteController::index()` (paginazione opt-in, scelta per non rompere consumer esistenti) e
  di `TagController`/`TaskController::index()` (nessuna paginazione). Tre convenzioni diverse
  convivono nell'API oggi; per un endpoint nuovo la paginazione sempre attiva è stata preferita
  perché più semplice da consumare per un client automatico (skill Claude), non essendoci
  compatibilità da preservare.
- **Ogni filtro numerico o data non validato risponde 422**, non un 500 né un risultato vuoto
  silenzioso: `validatedIntFilter()`/`validatedDateFilter()` in `StoryController` sono il pattern da
  imitare per i prossimi filtri di query string che non passano da una `FormRequest` (gli endpoint
  `index`/`show`-like non ne usano una, a differenza di `store`/`update`).
- **`GET /api/stories/{story}/logs`** risponde alla domanda "cosa è successo a questa story" (storico
  completo, nessun filtro `changed_*`) — diversa da `with=logs` su `GET /api/stories`, che risponde
  "chi ha modificato cosa in una finestra di tempo" per più story insieme.
- **L'ordine canonico degli stati (`sort=status`) resta bloccato**: `StoryStatus` non ha ancora un
  metodo che restituisca la posizione nel flusso, in attesa di conferma esplicita del CTO — la
  Kanban (`Kanban.php:86-92`) tratta `waiting` come stato in sequenza, `StoryMetricsCalculator::FORWARD_STATUSES`
  tratta `done` come successivo a `released`: due precedenti nel codice che non concordano fra loro
  sulla posizione di questi stati. `sort=status`/`-status` ricade silenziosamente sul default
  (`-created_at`) finché il metodo non esiste.

### Accesso a Nova (oc:8161)
- **Il wm-package resta fail-closed**: il listener condiviso `EnforceNovaAccessOnLogin` continua a
  negare il login web quando `can('access-nova')` è falso.
- **La deroga vive solo in Orchestrator**: `App\Models\User::can()` intercetta l'ability
  `access-nova` e ritorna sempre `true`; tutte le altre ability passano da Spatie/Gate standard.
  Nessuna modifica nel package, nessun cambio ai seed di ruoli e permessi.
  Coperto da `UserAccessNovaOverrideTest`.

### Documentazione OpenAPI con Scramble (oc:8287, oc:8291)
Doc pubblica su `/docs/api`, generata da `dedoc/scramble`, con link nel menu Nova.
- **Scramble documenta di default tutta la superficie `/api/*`**, incluse le route registrate da
  `wm-package` (mobile app v1/v2/v3, ec/poi, ec/track, ugc, wallet, elasticsearch, export).
  Limitata con `Scramble::routes()` in `AppServiceProvider::boot()`: filtro per action-name
  (`App\Http\Controllers\Api\*`) più whitelist esplicita per `AppController::config` e la closure
  `/me`. Il filtro per prefisso URI **non basta**, perché `wm-package` registra proprie route
  `auth/*` che collidono col prefisso di `/auth/login`.
- **`security_strategy` è disabilitato nel config pubblicato**: va abilitato esplicitamente
  (`MiddlewareAuthSecurityStrategy`) perché le operazioni sotto `auth:sanctum` ottengano il
  requisito Bearer a livello di documento.
- **"Try it out" solo sulle GET**: le operazioni mutanti hanno `security: []` esplicito via
  `Scramble::afterOpenApiGenerated()`, così un Bearer trapelato non può eseguire scritture reali
  dalla doc pubblica.
- **La security è a livello di documento, non di operazione**: le GET ereditano `$spec.security`
  senza ripetere la chiave — in un test non si verifica `operation.security`, si verifica l'assenza
  della chiave e la presenza della security globale.
- **Non eliminare i docblock `@response` duplicati** confidando nell'inferenza automatica su un
  metodo privato condiviso (es. `formatQuote()`): testato, produce tipi sbagliati per gli attributi
  `Spatie\Translatable` (`array` invece di `string`), per i valori computati (`string` invece di
  `float`) e marca come sempre `required` chiavi presenti solo con `?include=`.
- **Gli alias `@phpstan-type` non funzionano nei tag `@response`** (Scramble v0.13.35): producono
  silenziosamente uno schema errato. Per prevenire la deriva doc↔runtime si scrive un test che
  ispeziona `/docs/api.json` (vedi `tests/Feature/Api/QuoteApiDocsTest.php`), non si deduplicano i
  docblock via alias di tipo.
- **Un tipo union nel `@response`** (`array<...>|array{data: ..., meta: ...}`) è invece risolto
  correttamente come `anyOf`: è il pattern da usare quando un endpoint ha più forme di risposta
  (es. paginazione opt-in).
- **Scramble non infra tutti i query param**: riconosce solo `$request->method('key')` con metodo
  mappato (`integer`/`float`/`boolean`/`enum`/`query`/`string`/`str`/`input`/`get`/`post`) in
  posizione AST diretta — non dentro un confronto `===`, né annidata in un cast o in una funzione.
  Per ogni param letto in modo non standard si usa l'attributo PHP
  `#[QueryParameter(...)]` di `Dedoc\Scramble\Attributes` sul metodo, letto via reflection.
