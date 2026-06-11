> Ticket: oc:8044

# Notes — Salvataggio update ticket molto lento

## Deviazioni dal piano

- Nessuna deviazione sul codice: i 4 task sono stati eseguiti come da piano.
- I test sono stati eseguiti da Claude direttamente su `orchestrator_test` (non delegati al developer come previsto dal piano): durante il workflow si è scoperto che il DB di supporto test esiste ed è allineato (PG 17.5, pgvector disponibile — la vecchia nota di CLAUDE.md era obsoleta). Esito: `SyncDeveloperCalendarJobTest` 6/6 verdi, `StoryEmailTriggersTest` 22/22 verdi (no regressioni).

## Bug trovati in CI dopo il merge su develop

La CI (run 27351310042) è fallita per tre famiglie di problemi distinte:

1. **ConnectionException Redis (causato da questa feature)**: `uniqueVia(redis)` sul job acquisisce il lock al dispatch anche con `Bus::fake()`, quindi ogni test che salva una Story con cambio status apriva una connessione Redis — assente sui runner CI. Fix: in `Tests\TestCase::setUp()` lo store cache `redis` viene puntato al driver `array` per tutta la suite (rimosso il setUp equivalente dal solo SyncDeveloperCalendarJobTest).
2. **TagHoursMetricsTest (pre-esistente, non inerente)**: le run CI su develop erano già rosse prima di oc:8044. Due test assumevano `Tag::estimate` come colonna persistita, ma il refactor 3674990 l'ha trasformata in accessor runtime (somma di `estimated_hours` delle story taggate). Test adeguati al comportamento attuale.
3. **HetznerMonitoringExportTest (solo locale, non CI)**: 4 fail nel container locale perché `wm/hetzner-monitoring` (path repository aggiunto con oc:7944) non era installato nel vendor — `composer install` non era stato rieseguito dopo il pull. Risolto con `composer install` nel container; nessuna modifica al codice. I fail "class not found" dei sheet erano una cascata: le classi vivono dentro `HetznerExport.php` e vengono definite solo quando il file viene caricato dal primo test.

Esito finale: suite completa verde in locale (160 passed, 444 assertions).

## Bug trovati

- **Data stantia nel comando `sync:stories-calendar`** (emerso dalla Challenge, non dal ticket): `$today`/`$startTime` erano fissati nel costruttore; Artisan cacha l'istanza del comando per processo, quindi nei worker Horizon long-running dopo mezzanotte il comando avrebbe cancellato gli eventi di oggi ricreandoli su ieri (eventi orfani mai ripuliti dalla sync delle 07:45). Fixato spostando l'init in `handle()` — fix necessario *prima* di rendere il comando invocabile da un job in coda.
- **CLAUDE.md con sezioni duplicate** ("Feature disponibili" e "Decisioni architetturali" comparivano due volte, artefatto di merge): consolidate in sezioni uniche durante la Fase 8.

## Decisioni

Le decisioni di design principali sono documentate in `overview.md` (sezioni Rischi e Out of scope) e in CLAUDE.md. In aggiunta, emerse durante la scrittura del piano:

- `$tries = 5` sul job: un release di `WithoutOverlapping` conta come tentativo — con il default tries=1 del supervisor un job rilasciato per overlap verrebbe marcato failed.
- `expireAfter(300)` sul middleware: senza scadenza, un worker ucciso a metà sync lascerebbe il lock di overlap appeso per sempre.
- Delay di debounce nel **costruttore** del job (non nei punti di dispatch): impossibile dimenticarlo aggiungendo nuovi trigger.
- Aggiornata la sezione Testing di CLAUDE.md: i test girano su `orchestrator_test` senza override (la nota pgvector/PG14 era obsoleta).

## Procedura di rollback

Prima di un eventuale revert in produzione: `php artisan horizon:terminate` e attendere il drenaggio della coda (max delay 60s + backlog, verificabile su `/horizon`), oppure accettare i failed jobs class-not-found e pulirli con `php artisan horizon:forget-failed`. Il comando schedulato delle 07:45 è intatto: lo stato di regime si riallinea da solo ogni mattina.

## Follow-up

- **oc:8059** — Horizon in produzione gira in una screen non supervisionata: sostituire con systemd unit (`Restart=always`) + monitoraggio `horizon:status`. Ticket già creato durante la Challenge.
- **Alerting attivo sui fallimenti sync**: il comando ingoia le eccezioni Google e il job resta verde su Horizon; oggi c'è solo il logging (`Log::warning` su output con errori). Per un alerting vero serve estrarre la logica del comando in un service. Tech debt consapevole.
- **Sync su delete/create di Story e su cambio `tester_id`**: la matrice dei trigger resta incompleta (comportamento pre-esistente); se il "calendario stale" viene risegnalato, partire da qui.
- **Feedback UI sul debounce**: il calendario si aggiorna ~1-2 min dopo il save, senza indicazione in Nova. Da valutare solo se gli utenti lo percepiscono come problema.
- **Bulk edit O(N) residuo**: con la sync asincrona il save singolo costa ~50ms, ma il loop di `EditStories` resta sincrono (StoryLog + StoryTimeService per ogni story); su 50+ story il timeout potrebbe ripresentarsi.
