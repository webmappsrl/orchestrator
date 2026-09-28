> Ticket: oc:8636

# API: giorni in cui un ticket è stato in un certo stato

## Cosa cambia

Nuovo endpoint `GET /api/stories/{story}/status-history` (gruppo `auth:sanctum`). Dato un ticket,
restituisce:

- `story_id`: l'identificatore del ticket;
- `timezone`: sempre `"Europe/Rome"`, il fuso in cui sono espresse date e giorni;
- `current_status`: lo stato attuale (`stories.status`);
- `intervals`: i periodi di stato, dalla creazione a oggi, ciascuno con `status`, `from`, `to`
  (l'ultimo ha `to: null`);
- `days`: per ogni giorno di calendario, `date` (`AAAA-MM-GG`) e `statuses`, cioè i minuti passati
  in ciascuno stato.

Filtro facoltativo `?status=<stato>`: restano solo i periodi e i giorni di quello stato, calcolati
sempre sulla storia completa.

La logica sta in un service nuovo, `StoryStatusHistoryService`; il controller autorizza, valida e
risponde.

## Perché

La skill `wm-plan` (agente `wm-transcript-research`, repo `claude-marketplace`) cerca nelle
trascrizioni degli scrum cosa si è detto su un ticket, e ogni giorno di scrum è un notebook
NotebookLM separato. Sapere in quali giorni il ticket è stato in quale stato le permette di leggere
solo i giorni utili — per esempio solo quelli in cui il ticket era in `progress` — invece di aprire
un notebook per ogni giorno fra creazione e chiusura. L'endpoint esiste solo per questo scopo, non
per misurare il tempo lavorato. Ticket creato il 23/09/2026 e presentato da Giuseppe Bonfanti
allo scrum del 28/09/2026, dove ha aggiunto che potrebbe servire anche ad altri consumatori.

## Requisiti

### Endpoint, controller e service

- [ ] Branch `feature/oc-8636-api-storia-stati` da `develop`, commit con scope
      `feat(oc:8636): …`, PR verso `develop`
- [ ] Route `Route::get('/stories/{story}/status-history', [StoryController::class, 'statusHistory']);`
      in `routes/api.php`, subito sotto quella di `/stories/{story}/logs`, dentro `auth:sanctum`
- [ ] `StoryController::statusHistory(Request $request, Story $story): JsonResponse`, accanto a
      `logs()`, che fa in quest'ordine: `$this->authorize('view', $story)`; validazione di
      `status`; chiamata al service; `return response()->json($risultato)`
- [ ] `StoryStatusHistoryService::forStory(Story $story, ?StoryStatus $status = null): array`
      restituisce l'array della risposta e contiene tutto il calcolo

### Periodi (`intervals`)

- [ ] Si leggono solo le righe di `story_logs` della story con chiave `status` in `changes`,
      ordinate per `created_at` crescente e, a parità, per `id` crescente; le altre righe (watch,
      tag, user_id…) sono ignorate
- [ ] La cronologia parte sempre da `stories.created_at`, mai dalla prima riga di log
- [ ] Nessuna riga di stato (story mai cambiata di stato, o creata prima del 03/07/2024 quando
      `story_logs` non esisteva) → un solo periodo con `stories.status`, da `created_at` a `null`
- [ ] Con righe di stato → primo periodo `new` (assunto) da `created_at`; poi, per ogni riga in
      ordine: se lo stato è uguale a quello del periodo aperto la riga si ignora (promemoria
      `waiting`, o primo cambio verso `new`); se è diverso, il periodo aperto si chiude con
      `to` = `created_at` della riga e se ne apre uno nuovo con `from` = `created_at` della riga
- [ ] L'ultimo periodo ha sempre `to: null`
- [ ] Lo stesso stato può comparire in più periodi: nessun raggruppamento per stato
- [ ] `from`/`to` nel fuso `Europe/Rome`, ISO 8601 con offset (`Carbon::toIso8601String()`, es.
      `2026-09-21T09:00:00+02:00`)
- [ ] `current_status` è sempre `stories.status`, anche con il filtro
- [ ] Nessuna paginazione: tutta la storia in una risposta

### Giorni (`days`)

- [ ] Per ogni periodo si usa `to`, oppure `now()` se `to` è `null`
- [ ] Il periodo si divide alle mezzanotti di `Europe/Rome`; ogni pezzo va al suo giorno
- [ ] Minuti del pezzo = secondi / 60 arrotondato per difetto; minuti di calendario, 24 ore su 24,
      weekend compresi, niente orari di lavoro
- [ ] Nello stesso giorno i minuti dello stesso stato si sommano: ogni stato compare una volta sola
      in `statuses`
- [ ] Un pezzo di durata zero non genera nulla; un pezzo sotto il minuto genera la voce con `0`
- [ ] `days` in ordine di data crescente; dentro `statuses` le chiavi nell'ordine di comparsa nel
      giorno
- [ ] I giorni del cambio d'ora valgono 1380 o 1500 minuti

### Filtro `?status=`

- [ ] Facoltativo; se presente, `intervals` contiene solo i periodi di quello stato, `days` solo i
      giorni in cui compare, e **in `statuses` resta solo la sua chiave**
- [ ] Il filtro si applica dopo il calcolo sulla storia completa: i periodi restano identici, con i
      loro `from`/`to` originali
- [ ] Stato mai raggiunto → 200 con `intervals: []` e `days: []`

### Errori

- [ ] Senza token → 401 (gruppo `auth:sanctum`); story inesistente → 404 (route model binding)
- [ ] Stato non valido → 422 nel formato standard di validazione Laravel, regola
      `['sometimes', Rule::enum(StoryStatus::class)]`, messaggio
      `Stato non valido. Valori ammessi: <elenco>` con l'elenco costruito da
      `implode(', ', array_column(StoryStatus::cases(), 'value'))`

### Documentazione

- [ ] Scramble, sul metodo del controller: docblock in inglese con descrizione e `@response`, più
      `#[QueryParameter('status', description: '…', type: 'string')]`; la descrizione dichiara
      esplicitamente che lo stato del primo periodo è **assunto** `new`, non registrato
- [ ] Endpoint aggiunto in `docs/knowledge/api-esterne-e-documentazione.md`, fra gli endpoint Story,
      distinto da `/logs`

### Test

- [ ] Nuovo file `tests/Feature/Api/StoryStatusHistoryApiTest.php`, sul modello di
      `tests/Feature/Api/StoryApiTest.php`, con queste differenze:
  - `DatabaseTransactions`, **non** `RefreshDatabase`;
  - autenticazione con `Sanctum::actingAs($user)`;
  - story create con `Story::factory()->create([...])` passando **sempre** `status` e `created_at`
    espliciti e `user_id => null` (con uno sviluppatore assegnato `Story::boot()` trasforma `new`
    in `assigned`);
  - **mai** cambiare stato con `save()`: le righe di stato si creano a mano con `new StoryLog([...])`,
    assegnando `created_at` a parte (non è in `$fillable`);
  - ora fissata con `Carbon::setTestNow(...)` per i periodi aperti
- [ ] I 9 casi del ticket, ognuno con i valori attesi esatti:
  1. l'esempio completo del ticket, con risposta identica ai due JSON del ticket (senza filtro e
     con `?status=progress`). Scenario: story creata il 21/09/2026 alle 08:00; righe di stato
     `progress` il 21 alle 09:00, `waiting` il 21 alle 17:00, `waiting` il 22 alle 10:00
     (promemoria, da ignorare), `progress` il 23 alle 09:30; ora fissata alle 12:00 del 23;
     `stories.status = progress`. Attesi in `days`: 21/09 `new` 60, `progress` 480, `waiting` 420;
     22/09 `waiting` 1440; 23/09 `waiting` 570, `progress` 150;
  2. nessuna riga di stato, `stories.status = todo` → un solo periodo `todo` da `created_at` a
     `null`;
  3. righe `waiting` consecutive → un solo periodo `waiting`;
  4. righe non di stato (`watch`, `tag_attached`, `user_id`) fra due cambi → ignorate;
  5. periodo dalle 23:30 alle 00:45 → 30 minuti sul primo giorno, 45 sul secondo;
  6. 25/10/2026, periodo che copre l'intera giornata → 1500 minuti;
  7. `?status=` con stato mai raggiunto → 200, `intervals: []`, `days: []`;
  8. `?status=pippo` → 422 con l'elenco dei valori ammessi;
  9. senza token → 401; story inesistente → 404
- [ ] Esecuzione con `docker exec php81_orchestrator php artisan test --filter=StoryStatusHistoryApiTest`,
      poi la suite completa con `docker exec php81_orchestrator php artisan test`, sempre sul DB
      `orchestrator_test` configurato in `phpunit.xml` (mai `DB_DATABASE=orchestrator`)

### Come si verifica che è fatto

- [ ] Tutti i test sopra passano e la suite completa non ha nuovi fallimenti
- [ ] L'endpoint compare nella specifica OpenAPI di Scramble, con il parametro `status` e la forma
      della risposta
- [ ] Su un ticket vero in locale, i giorni in `days` coincidono con quelli in cui la tab Logs di
      Nova mostra i cambi di stato

## Rischi

- **Lo stato iniziale `new` è un'assunzione, non un dato.** Una story può nascere in un altro stato
  (via `POST /api/stories` con `status`, o `assigned` da `Story::boot()` se ha già uno
  sviluppatore) e alla creazione non viene scritto alcun log. Nel DB locale, delle ~2142 story con
  log di stato, il primo cambio registrato è `new` solo in 980 casi. Mitigazione: come da ticket,
  la documentazione OpenAPI dichiara l'assunzione. Per lo scopo della skill (trovare i giorni in
  `progress`) l'etichetta del primo periodo conta poco.
- **Limite noto, accettato: ultimo passaggio non registrato sui ticket vecchi.** Per circa 1239
  ticket chiusi prima di luglio 2026 l'ultimo periodo può risultare aperto in uno stato diverso da
  `current_status` (fino a oc:8137 i comandi automatici cambiavano stato senza scrivere log);
  nessun caso dopo il fix. Non si corregge: sono ticket chiusi, e se uno viene riaperto il nuovo
  cambio di stato scrive un log e l'incoerenza sparisce.
- **Righe `waiting` ripetute** dai promemoria (69 coppie consecutive uguali nel DB locale):
  gestite ignorando le righe uguali allo stato aperto.
- **Fuso orario**: colonne `timestamp` senza fuso, lette da Eloquent già in `Europe/Rome`
  (verificato). Il calcolo per giorno va fatto esplicitamente in `Europe/Rome` per non dipendere
  dal fuso del server.
- **Test che scrivono log veri**: cambiare stato con `save()` nei test genererebbe righe con l'ora
  attuale; per questo le righe si creano a mano (non esiste una `StoryLogFactory`).
- **Contratto consumato da un client esterno** (skill in `claude-marketplace`): la forma della
  risposta va considerata stabile dal primo rilascio.

## Out of scope

- `app/Actions/StoryTimeService.php` e `getStoryProgressDaysMinutes()` (ore lavorate: altra misura)
- La scrittura degli `StoryLog` (`Story::save()`, `SendWaitingStoryReminder`) e
  `LOG_IS_CHANGE_SQL` di `StoryController`
- Migration o colonne nuove; registrazione dello stato iniziale alla creazione
- Modifiche alla risposta degli endpoint esistenti
- Filtro per intervallo di date, paginazione, ore lavorative
- La modifica della skill `wm-plan` che consumerà l'endpoint (repo `claude-marketplace`)

## Moduli toccati

Tutto nel repo principale `orchestrator`, nessun submodule.

- `app/Services/StoryStatusHistoryService.php` — nuovo
- `app/Http/Controllers/Api/StoryController.php` — nuovo metodo `statusHistory()`
- `routes/api.php` — nuova route
- `tests/Feature/Api/StoryStatusHistoryApiTest.php` — nuovo
- `docs/knowledge/api-esterne-e-documentazione.md` — voce del nuovo endpoint
