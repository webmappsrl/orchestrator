> Ticket: oc:8044

# Salvataggio update ticket molto lento

## Cosa cambia

La sincronizzazione del calendario Google al salvataggio di una Story non avviene più in modo sincrono dentro la richiesta HTTP, ma viene delegata a un job in coda (`SyncDeveloperCalendarJob`) con debounce. Il salvataggio singolo e il bulk edit da Nova tornano a rispondere in tempi normali (< 2s); il calendario del developer si aggiorna in background entro ~1-2 minuti.

Trigger estesi rispetto a oggi (decisioni di Fase 2/3):

- **cambio di assegnazione** (`user_id`): sync per **entrambi** i calendari (vecchio e nuovo assegnatario) — oggi non triggerava nulla, calendario stale fino alle 07:45
- **uscita dallo stato `test`**: sync anche per il calendario del tester (oggi solo all'*ingresso* in `test`, l'evento 2BETESTED restava stale)

Inoltre viene corretto un **bug latente** scoperto dalla Challenge: il comando `sync:stories-calendar` fissava le date nel costruttore — innocuo nei processi usa-e-getta, ma nei worker Horizon long-running (istanza del comando cachata dall'applicazione Artisan) avrebbe sincronizzato su date passate dopo la prima mezzanotte.

## Perché

In produzione il salvataggio di una Story con cambio status impiega ~10 secondi e il bulk edit su 5+ Story va in timeout. Causa root confermata dal codice:

- `app/Observers/StoryObserver.php:143,149` — `Artisan::call('sync:stories-calendar', ...)` **sincrono** nell'evento `updated`, anche due volte nello stesso save (developer + tester quando status → `test`)
- `sync:stories-calendar` cancella e ricrea **tutti** gli eventi del giorno del developer: decine di chiamate API Google sequenziali (~10s con 15-30 ticket attivi)
- `app/Nova/Actions/EditStories.php:42-73` — il bulk edit salva in loop: N save = N sync sequenziali → 50+ secondi → timeout PHP/nginx
- Il cascade demote (`saving()`, righe 107-117) ri-triggera l'observer per ogni story demote: un singolo cambio status può generare 1+N sync nella stessa richiesta

## Requisiti

- [ ] Creare `app/Jobs/SyncDeveloperCalendarJob.php`: job in coda che esegue la sync calendario per un singolo developer via `Artisan::call('sync:stories-calendar', ...)` (riusa la logica senza duplicarla)
- [ ] Debounce: `ShouldBeUniqueUntilProcessing` con chiave = email del developer, `delay(60s)`, `uniqueFor = 300s` — N save ravvicinati → 1 sola sync che legge lo stato finale dal DB; un save *durante* l'esecuzione accoda un nuovo job (mai una sync persa)
- [ ] Lock di unicità su **Redis** (`uniqueVia`): sopravvive a `cache:clear`, indipendente dalla topologia dei container
- [ ] Serializzazione esecuzione: middleware `WithoutOverlapping(email)->releaseAfter(60)` — mai due sync concorrenti per lo stesso developer (l'idempotenza delete-then-recreate vale solo in sequenza)
- [ ] Osservabilità minima: il job cattura `Artisan::output()` e logga — `Log::warning` se l'output contiene errori (`Failed to...`), `Log::info` altrimenti
- [ ] Sostituire i due `Artisan::call()` sincroni in `StoryObserver::syncStoryCalendarIfStatusChanged()` con dispatch del job
- [ ] Trigger aggiuntivi: dispatch su `isDirty('user_id')` (entrambi gli assegnatari) e per il tester anche in **uscita** dallo stato `test`
- [ ] Fix bug data stantia: spostare l'inizializzazione di `$today`/`$startTime` dal costruttore all'inizio di `handle()` in `SyncStoriesWithGoogleCalendar`
- [ ] Il job gira sulla coda `default` (nessuna modifica a `config/horizon.php`)
- [ ] Il comando schedulato `sync:stories-calendar` delle 07:45 resta funzionalmente invariato (sync completa di tutti i developer)
- [ ] Test con `Bus::fake()`: dispatch su cambio status / cambio assegnazione (entrambe le email) / uscita da test; nessuna chiamata sincrona residua. Attenzione nota: i lock unique vengono acquisiti anche con `Bus::fake()` — i test devono tenerne conto (cache `array` in testing)

### Criteri di accettazione (aggiornati rispetto al ticket)

- Salvataggio singola Story con cambio status: risposta Nova < 2 secondi
- Bulk edit su 5+ Story con cambio status: azione completata senza errore
- Calendario Google aggiornato in background entro **~1-2 minuti** dal save (rilassato dai ~15s del ticket: è il costo del debounce, accettato in Fase 2)
- Nessuna regressione sul comando schedulato delle 07:45

## Rischi

| Rischio | Mitigazione |
|---|---|
| Bug data stantia nei worker long-running (istanza comando cachata → sync su date passate dopo mezzanotte, eventi orfani mai ripuliti) | **Risolto in scope**: init date in `handle()` invece che nel costruttore (Challenge, criticità 1) |
| Fallimenti invisibili: il comando ingoia le eccezioni → job sempre verde su Horizon anche con Google irraggiungibile | Logging dell'output del comando con livello `warning` in presenza di errori (Challenge, criticità 2). Alerting attivo: follow-up, fuori scope |
| Due sync concorrenti per lo stesso developer (lock `uniqueFor` scaduto con backlog in coda) → eventi duplicati | `WithoutOverlapping` con `releaseAfter(60)` serializza l'esecuzione per email (Challenge, criticità 3) |
| Horizon in produzione gira in una screen non supervisionata → se muore, la sync infragiornaliera si ferma silenziosamente | Problema infrastrutturale pre-esistente, tracciato nel ticket dedicato **oc:8059** (systemd + monitoraggio). Fallback: la sync delle 07:45 via cron resta garantita |
| Lock su cache `file` fragili (topologia container, `cache:clear`) | `uniqueVia(redis)`: lock su Redis, già backend delle code (Challenge, criticità 5) |
| Job ucciso dal timeout di 60s del supervisor `default` (tries=1) se Google è molto lento → sync persa fino alle 07:45 | **Rischio accettato consapevolmente** (Fase 2, Domanda 3): volumi attuali bassi; worst case il calendario resta stale fino alla sync schedulata |
| Save che committa un istante dopo la lettura dei dati da parte del job → modifica non sincronizzata | Finestra residua di millisecondi grazie al `delay(60s)`; rischio accettato, si riallinea alle 07:45 |
| Perdita di StoryLog / time tracking sul cascade demote | Il punto 4 del ticket (`saveQuietly()`) è stato **scartato** (Fase 2, Domanda 2): `updated()` alimenta StoryLog → `StoryTimeService` (calcolo ore) e la query calendario stessa. Il costo delle sync a catena è azzerato dalla dedup del job |
| Rollback non istantaneamente pulito (job delayed in Redis → class-not-found dopo revert) | Procedura documentata in `notes.md`: drenare la coda prima del revert oppure pulire i failed jobs; lo stato si riallinea comunque alle 07:45 |

## Out of scope

- Modifiche a `config/horizon.php` (code dedicate, timeout) — scartato in Fase 2, Domanda 3
- `saveQuietly()` sul cascade demote — scartato in Fase 2, Domanda 2 (dannoso per time tracking)
- Ottimizzazione interna di `SyncStoriesWithGoogleCalendar` (batching API Google, estrazione in service, error handling strutturato) — la logica del comando resta invariata salvo il fix data stantia
- Modifiche a `app/Nova/Actions/EditStories.php` — beneficia del fix senza modifiche dirette; il loop resta O(N) per StoryLog/StoryTimeService, accettabile (collo di bottiglia dominante rimosso)
- Sync su `delete`/`create` di una Story — comportamento pre-esistente invariato, si riallinea alle 07:45
- Sync su cambio di `tester_id` senza cambio status — comportamento pre-esistente invariato
- Edge case a cavallo di mezzanotte (save 23:59 + delay → sync sul giorno nuovo) — si riallinea alle 07:45
- Feedback UI in Nova sul ritardo di sincronizzazione (~1-2 min) — nessuna modifica UI in questo ticket
- Supervisione/monitoraggio di Horizon — ticket dedicato **oc:8059**
- Alerting attivo sui fallimenti della sync (Sentry, notifiche, job rosso su Horizon) — follow-up, richiede refactoring del comando in service

## Moduli toccati

| File | Repo | Azione |
|---|---|---|
| `app/Jobs/SyncDeveloperCalendarJob.php` | principale | **nuovo** — job con debounce, lock Redis, WithoutOverlapping, logging output |
| `app/Observers/StoryObserver.php` | principale | modifica — dispatch al posto di `Artisan::call`; trigger aggiuntivi su `user_id` e uscita da `test` |
| `app/Console/Commands/SyncStoriesWithGoogleCalendar.php` | principale | modifica minima — init `$today`/`$startTime` in `handle()` (fix data stantia) |
| `tests/Feature/SyncDeveloperCalendarJobTest.php` | principale | **nuovo** — test con `Bus::fake()` |

Feature interamente **custom**: nessun submodule coinvolto (`wm-package` e `wm-reports` non toccati).

## Ticket correlati

- **oc:8059** — Horizon in produzione: sostituire screen con systemd e aggiungere monitoraggio (nato dalla Challenge di questo ticket)
