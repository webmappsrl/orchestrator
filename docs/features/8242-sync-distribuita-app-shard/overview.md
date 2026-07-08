> Ticket: oc:8242

# Sync distribuita del modello App da tutti gli shard

## Cosa cambia

Orchestrator smette di conoscere solo le app del Geohub e diventa il catalogo unico delle app di **tutti gli shard** dell'infrastruttura Webmapp. L'attuale import statico e distruttivo (`OrchestratorImport`: sorgente hardcoded, `App::truncate()`, fillable letti a runtime dall'API geohub) viene sostituito da una **sync multi-shard full-fetch con upsert**:

- **Registry di shard committato** in `config/shards.php`: per ogni shard uno slug immutabile, l'URL base e il driver. I token stanno solo in ENV (`SHARD_TOKEN_<SLUG>`, uno distinto per shard). Aggiungere uno shard = una riga di config + una ENV. Commento esplicito nel file: *non rinominare mai uno slug — orfanizza le app dello shard*.
- **Seed iniziale del registry:**

  | Slug | URL | Driver |
  |---|---|---|
  | `geohub` | `https://geohub.webmapp.it` | `geohub` (legacy) |
  | `maphub` | `https://maphub.it` | `wmpackage` |
  | `camminiditalia` | `https://camminiditalia.maphub.it` | `wmpackage` |
  | `osm2cai` | `https://osm2cai.cai.it` | `wmpackage` |

- **Due driver di lettura**: `wmpackage` usa il nuovo endpoint export versionato del package (`/api/v1/export/apps`, vedi overview gemella in `wm-package/docs/features/8242-sync-distribuita-app-shard/`), autenticato con Bearer token; `geohub` usa l'attuale `/api/v1/app/all` pubblico (il geohub non monta wm-package), con **mapping esplicito** dei campi sullo schema wm-package (non pass-through: i campi che il geohub non ha restano NULL).
- **Full sync a ogni giro, niente incrementale**: a questi volumi (~68 app sul geohub) il filtro `updated_after` è ottimizzazione prematura e introdurrebbe perdite silenziose (update remoti via `saveQuietly`/query dirette non toccano `updated_at`, clock skew). La sync scarica sempre la lista completa di ogni shard e fa upsert: ogni giro riallinea tutto e il confronto per le dismesse viene gratis. `updated_after` resta nel contratto dell'endpoint wm-package per il futuro, ma Orchestrator non lo usa.
- **Identità composita `(shard, app_id)`**: nuova colonna `shard` sulla tabella `apps`; l'attuale `unique` su `app_id` diventa unique composito. L'`app_id` remoto (id numerico dello shard, salvato come stringa) non cambia mai: app con `app_id = 1` su geohub e su maphub sono righe distinte. L'`id` locale autoincrement resta come chiave surrogata per pivot e relazioni.
- **Upsert per shard, sempre senza eventi Eloquent**: la sync scrive con `saveQuietly`/upsert e **non invoca nessun side effect** — niente tag automatici, niente `AppObserver`/`BuildConfJson` (che scriverebbe conf con URL geohub hardcodati, sbagliati per gli altri shard). L'observer resta attivo solo per le modifiche manuali da Nova. Sparisce il giro backup/restore della pivot `user_app`.
- **Colonne a proprietà separata**: le colonne *shard-owned* (schema wm-package, incluso `user_email`) e `removed_from_shard_at` (*sync-owned*) sono scritte solo dalla sync; le colonne *orchestrator-owned* (`user_id` FK, `customer_name`, pivot `user_app`, tag) sono il CRM locale e la sync non le sovrascrive mai.
- **Auto-link CRM**: a ogni upsert, se `user_email` corrisponde (case-insensitive) all'email di un utente Orchestrator, `apps.user_id` viene popolata — solo se attualmente NULL: un'assegnazione manuale non viene mai sovrascritta. Oggi il match copre 6 app su 68; le altre restano "senza referente". Se il proprietario remoto cambia, `user_email` si aggiorna ma il `user_id` manuale resta (accettato: la parola finale sul CRM è di Orchestrator; l'email a fianco è il segnale visibile).
- **App dismesse, mai cancellate — con guardia anti-strage**: la riconciliazione confronta gli `app_id` remoti con quelli locali dello shard; le app sparite ricevono `removed_from_shard_at`, nessun delete fisico. **Guardie**: payload vuoto/invalido → nessuna azione su quello shard (solo log); rimozioni calcolate oltre il 30% delle app attive dello shard → abort con log (una cancellazione vera è di una-due app, mai di trenta). Un'app dismessa che **ricompare** nella lista viene riattivata automaticamente (azzeramento del timbro — per questo il campo è sync-owned). In Nova un filtro tiene di default la vista sulle attive.
- **Sync schedulata con errori isolati per shard**: uno shard giù non blocca gli altri; l'errore è loggato per shard. Lock anti-sovrapposizione per shard (`WithoutOverlapping`, stesso pattern della sync calendario) tra giro schedulato e sync on-demand.
- **Sync on-demand sul detail Nova**: all'apertura del detail di un'app, fetch della singola app dal suo shard con timeout corto (2–3s), fallback silenzioso alla copia locale, throttle per app via Redis (una sync ogni pochi minuti per app).
- **Fillable statici** sul modello App di Orchestrator (fine della lettura runtime dello schema dal geohub) e schema `apps` allineato alle colonne del modello wm-package.
- **Pulizia completa del codice morto** su `app/Models/App.php`: relazioni `ugc_medias`/`ugc_pois`/`ugc_tracks`, `getGeojson()`, `getMostViewedPoiGeojson()`, `getUGCPoiGeojson()`/`getUGCMediaGeojson()`/`getiUGCTrackGeojson()` (tutti referenziano classi inesistenti `EcTrack`/`EcPoi` → fatal se invocati) e `getAppfillables()`.
- **Filtro shard in Nova** sulla resource App (colonna + filtro).
- **Report PDF multi-shard**: il nome file diventa `webmapp_report_app_{shard}_{nome}_{mese}.pdf` — oggi è chiavato sul solo nome app, e due app omonime su shard diversi si sovrascriverebbero il report. Il bottone e il flusso restano identici; i PDF del mese corrente vengono semplicemente rigenerati col nuovo nome alla prima richiesta.
- **Rimozione del vecchio import nello stesso PR**: `OrchestratorImport::importApps()` sparisce — il suo `App::truncate()` su una tabella multi-shard cancellerebbe le app di tutti gli shard; il footgun non deve esistere.

## Perché

L'infrastruttura Webmapp è distribuita su più istanze (shard) e ognuna ospita le proprie app, ma Orchestrator — il sistema CRM/commerciale — vede solo il geohub. Serve un catalogo unico e aggiornato per amministrare la parte commerciale e di progetto. Il criterio di fondo espresso dal team: **l'utente X deve ritrovarsi la sua app associata, con la parte CRM agganciata** — cosa che oggi non avviene nemmeno per il geohub (0 app con FK `user_id` valorizzata, pivot `user_app` vuota, associazione affidata a una colonna testo `user_email`).

Il presupposto architetturale regge: tutti gli shard condividono il modello App di wm-package (derivato da quello del geohub), quindi la sync è uniforme; il geohub è trattato come uno shard con driver legacy. Il flusso è **a senso unico**: Orchestrator legge, non scrive mai verso gli shard — il raggio massimo del danno è la copia locale, riallineata dal giro di sync successivo.

## Requisiti

- [ ] Registry shard in `config/shards.php` (slug immutabile, URL, driver) con seed geohub/maphub/camminiditalia/osm2cai; token in ENV `SHARD_TOKEN_<SLUG>` (uno per shard)
- [ ] Migration A: colonna `shard` + `removed_from_shard_at` su `apps`; unique composito `(shard, app_id)` al posto dell'unique su `app_id`; backfill `shard = 'geohub'` sulle righe esistenti
- [ ] Migration B (separata): allineamento colonne `apps` allo schema wm-package
- [ ] Fillable statici sul modello locale (niente più fetch runtime)
- [ ] Driver `wmpackage`: consuma `/api/v1/export/apps` (paginato) e `/api/v1/export/apps/{app}` con Bearer token
- [ ] Driver `geohub`: consuma l'attuale `/api/v1/app/all` con mapping esplicito dei campi
- [ ] Sync full-fetch per shard con upsert su `(shard, app_id)`, sempre `saveQuietly`/senza eventi: mai truncate, mai side effect observer, mai scrittura delle colonne orchestrator-owned (`user_id` valorizzata, `customer_name`, pivot `user_app`, tag)
- [ ] Auto-link referente: `user_email` → `users.email` (case-insensitive) popola `apps.user_id` solo se NULL
- [ ] Riconciliazione dismesse con guardie: payload vuoto/invalido → no-op + log; rimozioni > 30% delle attive dello shard → abort + log; app ricomparsa → riattivata (azzeramento `removed_from_shard_at`)
- [ ] Sync schedulata per tutti gli shard; errore su uno shard isolato e loggato, gli altri proseguono; lock `WithoutOverlapping` per shard
- [ ] Sync on-demand della singola app all'apertura del detail Nova: timeout 2–3s, fallback alla copia locale, throttle per app via Redis
- [ ] Nova: colonna e filtro shard sulla resource App; filtro default "attive" (senza `removed_from_shard_at`)
- [ ] Rimozione di `OrchestratorImport::importApps()` e di tutto il codice morto sul modello App (`ugc_*`, `getGeojson`, `getMostViewedPoiGeojson`, `getUGC*Geojson`, `getAppfillables`)
- [ ] **La colonna REPORT (PDF) in Nova continua a funzionare per tutte le app di tutti gli shard**: pipeline invariata (`AppReportController` → `GenerateAppReportJob` → `genera_report_app.py`, dati da API store — verificato: nessuna dipendenza dal geohub); nome file shard-qualificato per evitare collisioni tra app omonime
- [ ] Test Feature su `orchestrator_test`: upsert per shard (due shard, stesso `app_id`), doppia sync che preserva CRM locale, auto-link email, guardie riconciliazione (payload vuoto, soglia 30%, riattivazione), isolamento errori per shard, nessun evento Eloquent scatenato dalla sync

## Rischi

- **Endpoint wm-package da deployare su ogni shard**: finché uno shard non aggiorna il package, la sua sync fallisce (404). Mitigazione: errori isolati per shard — rollout incrementale per natura; lo shard non pronto resta vuoto senza bloccare gli altri.
- **Drift di schema geohub ↔ wm-package**: già in corso (rename theme filters, campi overlays apr 2026). Il driver legacy usa un mapping esplicito; i campi mancanti restano NULL. Il drift produce dati mancanti silenziosi: accettato per il geohub (destinato alla migrazione), non riguarda gli shard wm-package che seguono il contratto versionato.
- **Allineamento schema a tre repo**: una migration futura su `apps` in wm-package richiede migration gemella su Orchestrator. Accettato: il contratto versionato (`v1`) rende la cosa esplicita invece che implicita, e un campo nuovo non ancora allineato non rompe nulla (viene ignorato).
- **Migrazione del vincolo unique**: `down()` completo ma con finestra di reversibilità reale limitata — dopo il primo sync multi-shard il rollback della migration perderebbe dati. Il **rollback operativo è la config**: disattivare gli shard dal registry ferma la sync senza perdite; la migration `down()` è solo per il pre-produzione (nota esplicita nella migration).
- **Match email fragile**: 6/68 oggi; account app spesso utenze shard non presenti in Orchestrator. Mitigazione: link best-effort, mai sovrascrittura di valori manuali, nessun requisito di copertura totale.
- **Detail Nova più lento**: fino a 2–3s all'apertura nel caso peggiore (shard lento, fuori throttle). Mitigazione: timeout corto, throttle Redis, fallback silenzioso.
- **Endpoint geohub pubblico**: il driver legacy continua a usare l'endpoint aperto (email esposte). Sanarlo è fuori scope ma tracciato; il nuovo endpoint wm-package nasce protetto.
- **App migrata tra shard** (futuro: geohub → maphub): diventa due righe — la vecchia dismessa col suo CRM, la nuova vergine. Accettato: trasferimento CRM manuale; se il caso diventasse frequente si valuterà un'azione Nova "trasferisci CRM".
- **Titolarità dati su shard di terzi**: `osm2cai.cai.it` è un'istanza brandizzata CAI — l'export porta email/nomi dei proprietari delle app nel CRM Webmapp. Tecnicamente identico agli altri shard; l'eventuale ok formale lato CAI è una decisione di business fuori dal perimetro tecnico di questo ticket.

## Out of scope

- Sync di Layer, POI, track, UGC o qualsiasi contenuto geografico degli shard (la tabella `layers` locale resta com'è, vuota)
- Uso del filtro incrementale `updated_after` lato Orchestrator (il contratto lo prevede, si attiverà solo se i volumi lo richiederanno)
- Driver a connessione DB diretta (l'architettura a driver lo lascia possibile in futuro)
- Migrazione del geohub a wm-package o protezione del suo endpoint legacy
- Popolamento della pivot `user_app` (assegnazione dev alle app resta manuale)
- Trasferimento automatico del CRM per app migrate tra shard
- Unificazione del modello App di Orchestrator con la classe `Wm\WmPackage\Models\App` (si allinea lo schema, non la classe: l'observer di wm-package scatena job da shard che su Orchestrator non devono girare)

## Moduli toccati

**Repo orchestrator (questo repo):**
- `config/shards.php` — nuovo: registry shard
- `database/migrations/` — migration A (shard, removed_from_shard_at, unique composito, backfill) + migration B (allineamento colonne wm-package)
- `app/Models/App.php` — fillable statici, rimozione completa codice morto, scope attive
- `app/Services/Shards/` — nuovo: `ShardRegistry`, driver `WmPackageShardDriver` / `GeohubShardDriver`, `AppSyncService` (upsert quiet, auto-link, riconciliazione con guardie)
- `app/Console/Commands/` — nuovo comando `apps:sync {--shard=}`; rimozione di `OrchestratorImport::importApps()`
- `app/Console/Kernel.php` — scheduling sync
- `app/Jobs/SyncShardAppJob.php` — nuovo: sync on-demand singola app
- `app/Nova/App.php` + `app/Nova/Filters/` — colonna/filtro shard, filtro attive, hook detail per sync on-demand
- `app/Http/Controllers/AppReportController.php` — nome file PDF shard-qualificato
- `tests/Feature/AppShardSyncTest.php` — nuovo

**Repo wm-package (submodule — dettaglio nella overview gemella):**
- Endpoint export apps versionato `/api/v1/export/apps` (lista paginata + singola) con Bearer token, whitelist campi via JsonResource, throttle, errori distinti
