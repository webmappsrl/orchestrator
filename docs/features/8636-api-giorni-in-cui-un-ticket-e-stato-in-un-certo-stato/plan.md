> Ticket: oc:8636

# Piano — API: giorni in cui un ticket è stato in un certo stato

Riferimento: [overview.md](overview.md). Tutti i file sono nel repo principale `orchestrator`,
nessun submodule. Branch: `feature/oc-8636-api-storia-stati` (già creato da `origin/develop`, senza
upstream). PR verso `develop`.

**Commit:** i commit indicati sotto sono istruzioni per la dev. Nessun `git add`, `git commit` o
`git push` viene eseguito durante l'implementazione: si committa solo dopo il review-gate e
l'approvazione esplicita.

**Test:** sempre dentro il container, sul DB `orchestrator_test` configurato in `phpunit.xml`; mai
`DB_DATABASE=orchestrator` (vedi `docs/howto/eseguire-i-test.md`).

---

## Task 1 — Scelta dei ticket veri

Allo scrum del 28/09 (14:08) Giuseppe ha chiesto che la dev scegliesse, insieme a Claude, i ticket
su cui costruire i test. Scelta, dai dati del DB locale (log di stato fra luglio 2024 e giugno 2025):

| Caso | Ticket | Perché |
|---|---|---|
| `waiting` ripetuti | oc:4043 | `new` → `waiting` → due promemoria `waiting` (18:00, utente di sistema) → `backlog`, stato finale coerente |
| righe non di stato fra due cambi + cambio d'ora 27/10/2024 | oc:2685 | righe `parent_id` e `user_id` fra `progress` e `testing`; `backlog` dal 24/10/2024 al 14/01/2025 |
| cambio d'ora 30/03/2025 | oc:5090 | `todo` dal 27/03 al 01/04/2025 |
| mezzanotte | oc:3846 | `new` dalle 23:08:28 del 26/08/2024 alle 05:32:22 del 27/08 |
| pezzo sotto il minuto | oc:5642 | `testing` per 49 secondi il 05/06/2025 |
| più rientri in `progress` | oc:4689 | tre periodi `progress` in due giorni; è anche un caso del limite noto (ultimo log `released`, stato `done`) |
| nessun log di stato | un ticket creato prima del 03/07/2024 senza log di stato (da scegliere, es. oc:836) | un solo periodo con lo stato attuale |
| giorno di creazione lontano dal primo cambio | oc:3133 | creato il 24/04/2024, primo cambio di stato il 04/07/2024 |

- [ ] Confermare la scelta con la dev; se cambia un ticket, aggiornare questa tabella e rifare solo
      la sua fixture

## Task 2 — Estrazione delle fixture dal DB locale

File: `tests/Fixtures/story-status-history/oc-<ID>.json` (nuovi; la cartella `tests/Fixtures/` va
creata).

- [ ] Estrarre in sola lettura dal DB locale `orchestrator`, per ogni ticket scelto:
  - `story`: `id`, `status`, `created_at`;
  - `logs`: tutte le righe di `story_logs` escluse quelle `watch`, con `id`, `created_at`,
    `changes`, in ordine `created_at, id`.

  Lo script di estrazione è usa e getta (scratchpad, via `php artisan tinker`), non entra nel repo.
- [ ] **Ripulire `changes` dai contenuti dei clienti**: le righe vere contengono `name`,
      `customer_request`, `description` con testo di clienti reali, che non deve finire nel repo.
      Si conserva il valore di `status`; per le altre chiavi si conserva solo il nome della chiave,
      con valore `null` (serve al caso «righe non di stato»)
- [ ] Formato:

  ```json
  {
    "source": "oc:3846, DB locale (dump di produzione, dati fino a giugno 2025)",
    "story": { "id": 3846, "status": "done", "created_at": "2024-08-26 23:08:28" },
    "logs": [
      { "id": 2372, "created_at": "2024-08-26 23:08:28", "changes": { "status": "new", "name": null } },
      { "id": 2381, "created_at": "2024-08-27 05:32:22", "changes": { "status": "progress", "user_id": null } }
    ]
  }
  ```

- [ ] Verifica: `grep -c '"customer_request": "' tests/Fixtures/story-status-history/*.json` deve
      restituire 0 per ogni file

## Task 3 — Test (prima dell'implementazione)

> ⚠️ L'implementazione ha deviato da questo task: [notes.md](notes.md#task-3-test)

File: `tests/Feature/Api/StoryStatusHistoryApiTest.php` (nuovo).

- [ ] Struttura: `use DatabaseTransactions`; `setUp()` crea un utente Developer
      (`User::factory()->create(['roles' => [UserRole::Developer]])`); `Sanctum::actingAs` nei test
      autenticati; `tearDown()` con `Carbon::setTestNow()`
- [ ] Helper privato `storyFromFixture(string $name): Story`:
  - legge il JSON;
  - `Story::factory()->create(['status' => …, 'created_at' => …, 'user_id' => null])` (con uno
    sviluppatore assegnato `Story::boot()` trasformerebbe `new` in `assigned`);
  - per ogni log: `new StoryLog(['story_id' => $story->id, 'user_id' => $this->user->id,
    'viewed_at' => $t, 'changes' => …])`, poi `$log->created_at = $t; $log->save();`
    (`created_at` non è in `$fillable`);
  - **mai** cambiare stato con `save()` sulla story
- [ ] Helper `storyWithLogs(string $createdAt, string $status, array $logs): Story` per i casi
      sintetici (esempio del ticket)
- [ ] Casi, ognuno con i valori attesi scritti a mano nel test (non ricalcolati con lo stesso
      algoritmo):
  1. esempio del ticket (sintetico, `setTestNow('2026-09-23 12:00:00')`): risposta identica ai due
     JSON del ticket, senza filtro e con `?status=progress`
  2. nessun log di stato (fixture): un periodo con lo stato attuale, da `created_at` a `null`
  3. oc:4043: un solo periodo `waiting` nonostante i promemoria
  4. oc:2685: le righe `parent_id`/`user_id` non spezzano il periodo `progress`
  5. oc:3846: `2024-08-26` → `new: 51`, `2024-08-27` → `new: 332` (23:08:28→00:00 = 51 min 32 s;
     00:00→05:32:22 = 332 min 22 s)
  6. oc:2685: `2024-10-27` → `backlog: 1500`; oc:5090: `2025-03-30` → `todo: 1380`
  7. `?status=` con stato mai raggiunto: 200, `intervals: []`, `days: []`
  8. `?status=pippo`: 422, `errors.status` con `Stato non valido. Valori ammessi: backlog, new, …`
  9. senza token: 401; story inesistente: 404
  10. oc:5642: `2025-06-05` contiene `testing: 0`
  11. oc:3133: il primo elemento di `days` è `2024-04-24` (giorno di creazione) con `new`
  12. oc:4689: tre periodi `progress`; ultimo periodo `released` con `to: null` e
      `current_status: "done"` (limite noto, comportamento accettato)
- [ ] Eseguire `docker exec php81_orchestrator php artisan test --filter=StoryStatusHistoryApiTest`:
      i test devono fallire (endpoint inesistente → 404 sui casi autenticati)

## Task 4 — Service: periodi (`intervals`)

File: `app/Services/StoryStatusHistoryService.php` (nuovo).

- [ ] Classe con `forStory(Story $story, ?StoryStatus $status = null): array`
- [ ] Lettura: `$story->storyLogs()->whereRaw("jsonb_exists(changes::jsonb, 'status')")
      ->orderBy('created_at')->orderBy('id')->get()` (non usare `?` nell'SQL: PDO lo
      interpreterebbe come binding)
- [ ] Costruzione dei periodi, come da overview:
  - nessuna riga → `[{status: stories.status, from: created_at, to: null}]`;
  - altrimenti primo periodo `new` da `created_at`; riga uguale allo stato aperto → ignorata;
    diversa → chiude (`to` = `created_at` della riga) e apre
- [ ] Formato date: `->timezone('Europe/Rome')->toIso8601String()`
- [ ] Risposta: `story_id`, `timezone`, `current_status`, `intervals`, `days`
- [ ] Eseguire i test: passano i casi su `intervals` (2, 3, 4, 12)

## Task 5 — Service: giorni (`days`)

- [ ] Per ogni periodo: `$end = $to ?? now()`; ciclo da `$from` spezzando a
      `$cursor->copy()->addDay()->startOfDay()` (in `Europe/Rome`), pezzo = `min(mezzanotte, $end)`
- [ ] Minuti del pezzo: `intdiv($fine->getTimestamp() - $inizio->getTimestamp(), 60)`, calcolati
      sui timestamp e non con `diffInMinutes` sulle ore locali, così il cambio d'ora dà 1380/1500
- [ ] Pezzo di durata zero → saltato; sotto il minuto → voce con `0`
- [ ] Accumulo in un array ordinato `[data => [stato => minuti]]`, sommando lo stesso stato nello
      stesso giorno e mantenendo l'ordine di comparsa; poi `ksort` per data e conversione in
      `[{date, statuses}]`
- [ ] Eseguire i test: passano i casi 1 (senza filtro), 5, 6, 10, 11

## Task 6 — Service: filtro

- [ ] Se `$status` è presente, dopo il calcolo completo: `intervals` filtrati per stato
      (`array_values`); per ogni giorno si tiene solo la chiave dello stato, e si eliminano i giorni
      che non la contengono
- [ ] `current_status` invariato
- [ ] Eseguire i test: passano 1 (con filtro) e 7

## Task 7 — Controller e route

File: `app/Http/Controllers/Api/StoryController.php`, `routes/api.php`.

- [ ] Metodo `statusHistory(Request $request, Story $story): JsonResponse`, subito dopo `logs()`:
  1. `$this->authorize('view', $story)`;
  2. `$request->validate(['status' => ['sometimes', Rule::enum(StoryStatus::class)]],
     ['status.*' => 'Stato non valido. Valori ammessi: ' . implode(', ', array_column(StoryStatus::cases(), 'value'))])`;
  3. `app(StoryStatusHistoryService::class)->forStory($story, $request->has('status') ? StoryStatus::from($request->query('status')) : null)`;
  4. `return response()->json($risultato)`
- [ ] Import di `StoryStatus`, `Rule`, `StoryStatusHistoryService`
- [ ] Route `Route::get('/stories/{story}/status-history', [StoryController::class, 'statusHistory']);`
      subito sotto quella di `/logs`
- [ ] Eseguire i test: passano tutti, compresi 8 e 9

## Task 8 — Documentazione Scramble

- [ ] Docblock in inglese su `statusHistory()`, nello stile di `logs()`: a cosa serve (in quali
      giorni un ticket è stato in quale stato), minuti di calendario in `Europe/Rome`, e la frase
      esplicita che lo stato del primo periodo è **assumed** `new`, not recorded
- [ ] `@response array{story_id: int, timezone: string, current_status: string, intervals: array<array{status: string, from: string, to: string|null}>, days: array<array{date: string, statuses: array<string, int>}>}`
- [ ] `#[QueryParameter('status', description: '…', type: 'string')]`
- [ ] Verifica: l'endpoint compare nella specifica generata da Scramble (`/docs/api.json`), con il
      parametro `status` e la forma della risposta

## Task 9 — Documentazione interna

File: `docs/knowledge/api-esterne-e-documentazione.md`.

- [ ] Nella sezione degli endpoint Story, voce `GET /api/stories/{story}/status-history`
      (oc:8636): a quale domanda risponde, la differenza con `/logs`, il `new` presunto e il limite
      noto sui ticket chiusi prima di oc:8137

## Task 10 — Suite completa

- [ ] `docker exec php81_orchestrator php artisan test --filter=StoryStatusHistoryApiTest`
- [ ] `docker exec php81_orchestrator php artisan test`: nessun fallimento nuovo rispetto a
      `develop`

## Task 11 — Verifica manuale su ticket veri

- [ ] Con il DB locale attuale: chiamare l'endpoint (token Sanctum locale) su 2-3 dei ticket del
      Task 1 e confrontare `days` con la tab Logs di Nova
- [ ] **Dopo `db:sync`** (servono le credenziali AWS dei dump nel `.env`): ripetere su oc:8631 e
      su qualche ticket recente; ricontrollare che non esistano ticket incoerenti dopo il
      01/07/2026; segnalare a Giuseppe ciò che non torna
- [ ] Se le credenziali non arrivano, annotarlo in `notes.md` come verifica rimasta aperta

## Task 12 — Chiusura

- [ ] `notes.md` con divergenze, decisioni (tag rimandati alla chiusura, regola sull'ultimo periodo
      proposta e scartata, DB locale fermo a giugno 2025) e follow-up
- [ ] Review-gate: riepilogo del diff da subagente isolato, approvazione della dev
- [ ] Commit proposti (da eseguire solo dopo l'approvazione):
  - `feat(oc:8636): fixture da ticket veri e test dell'endpoint status-history`
  - `feat(oc:8636): endpoint GET /api/stories/{story}/status-history`
  - `feat(oc:8636): documentazione dell'endpoint status-history`
- [ ] PR verso `develop`
