> Ticket: oc:8242

# Notes — Sync distribuita del modello App da tutti gli shard

## Deviazioni dal piano

- **`fill()` filtra i null** (`AppSyncService::upsert`): non previsto dal piano. La tabella `apps` ha colonne NOT NULL con default (es. `default_language`) e l'insert di null espliciti violava i vincoli. Regola adottata: i null del payload non si scrivono mai — al create agiscono i default del DB, all'update non azzerano valori esistenti. Effetto collaterale accettato: un campo azzerato *sullo shard* non viene azzerato su Orchestrator.
- **Test multi-sync riscritti con coda di payload**: `Http::fake()` chiamato due volte sulla stessa URL non sovrascrive il primo stub (la factory HTTP è singleton e gli stub si accumulano, vince il primo). I test che simulano due sync consecutivi usano un unico fake con una coda di risposte (`fakeShardAppsSequence`).
- **`author()` di wm-package aveva la FK sbagliata**: `belongsTo(User::class)` inferiva `author_id` ma la colonna è `user_id` — la relazione era rotta da sempre (nessun uso interno al package la esercitava). Corretta con FK esplicita: è il bug che avrebbe reso `author_email` sempre null nell'export.
- **`Kernel::commands()` conteneva un load path rotto** (`__DIR__ . 'Commands/OrchestratorImport'`, senza slash): rimosso insieme al comando. Il load gemello `Commands/ImportProducts` (stesso path rotto, comando comunque caricato dal load principale) è stato lasciato: fuori scope.

## Bug trovati

- **Report PDF senza dati (bug preesistente, scoperto in prova locale)**: `AppReportController` passava `$app->app_id` allo script Python come `--bundle-id`, ma per le app importate dal geohub `app_id` è l'id numerico remoto ("53"), inesistente sugli store → lo store lookup falliva e il PDF usciva vuoto. Fix: `bundleId()` deriva il package dal link Play Store (`?id=...`, copre 31/68 app), fallback su `app_id` solo se è un bundle vero (contiene un punto), altrimenti nessun bundle → lo script fa fuzzy match sul nome (verificato: trova anche app senza store link in DB, es. MotoMappa). Test: `AppReportBundleIdTest`.

- Relazione `App::author()` in wm-package con FK implicita errata (vedi sopra) — fixata in questo ticket perché il contratto v1 la usa.
- La suite del package non era installabile out-of-the-box: `composer install` bloccato dall'audit security (advisory su laravel/framework 10) e poi dalla licenza Nova per la 5.9.3 del lock. Risolto con `audit.block-insecure=false` nella config composer globale del container, `auth.json` copiato da orchestrator (gitignorato) e Nova pinnata a 5.7.6 (stessa versione di orchestrator) nel lock del package (lock gitignorato, nessun file committato).
- Per i test del package è stato creato il DB di supporto `wm_package` (role `wm_package`, estensioni postgis+vector) su `postgres_orchestrator` — richiesto da `phpunit.xml` del package, non esisteva in locale.

## Decisioni

- **Bottone REPORT solo per app pubblicate sugli store** (richiesta in prova locale): `App::hasStorePresence()` = store link presente o `app_id` in formato bundle. In Nova le altre app mostrano `—` ("Non pubblicata sugli store"); l'accesso diretto all'URL risponde 422 con messaggio esplicativo. Trade-off consapevole: app sugli store ma senza link in DB (es. MotoMappa) restano senza bottone finché non si aggiunge il link alla scheda — criterio prevedibile preferito al fuzzy match fortunato.

- `customer_name` è l'unica colonna SEED_ONLY: la sync la scrive alla creazione (primo valore utile dal payload) ma non la tocca mai sugli update — su Orchestrator è un campo CRM curato a mano.
- `removed_from_shard_at` è sync-owned: la sync la timbra e la azzera (riattivazione automatica di app ricomparse).
- Guardia riconciliazione al 30% delle app attive per shard; payload vuoto o invalido = no-op totale con log warning.
- Sync on-demand dal detail Nova via `detailQuery()` con `dispatchSync` (inline): il job è `ShouldQueue` solo per riuso futuro, oggi gira sincrono con timeout 3s del driver + throttle Redis 180s per app.
- Il test `active scope` filtra su un sottoinsieme (`whereIn`) perché il DB dev contiene le 68 app reali del geohub (i test girano su orchestrator_test, ma il filtro rende il test robusto ovunque).

- **ID visibile in Nova = id remoto dello shard** (richiesta esplicita in fase di prova locale): la colonna "ID" dell'index mostra `app_id` e il titolo del detail è `nome (app_id @ shard)`. L'`id` locale resta interno (route, pivot, PDF) e non è mostrato.

## Follow-up

- Deploy di wm-package sugli shard (maphub, camminiditalia, osm2cai) + configurazione `WM_EXPORT_TOKEN` lato shard e `SHARD_TOKEN_<SLUG>` lato Orchestrator.
- Il geohub resta su endpoint legacy pubblico: protezione fuori scope, tracciata negli overview.
- Trasferimento CRM per app migrate tra shard: manuale; valutare azione Nova se il caso diventa frequente.
- `alert` sull'età dell'ultima sync riuscita per shard (oggi solo log): utile quando gli shard wm-package saranno attivi.
