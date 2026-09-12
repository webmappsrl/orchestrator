# Sync distribuita delle App multi-shard

> Origine: oc:8242

## Stato attuale

- **Identità composita `(shard, app_id)`**: `app_id` è l'id numerico remoto (stringa) dello shard,
  immutabile; l'`id` locale autoincrement resta la chiave per route, pivot e tag. L'unique composito
  ha sostituito l'unique su `app_id`. In Nova l'ID visibile è `app_id` più la colonna shard, mai
  l'id locale.
- **La sync scrive solo con `saveQuietly`**: mai eventi Eloquent — l'observer `updated` fa
  `BuildConfJson` con URL geohub hardcodati e l'hook `created` crea tag automatici. Nessun side
  effect viene invocato dalla sync.
- **Colonne a proprietà separata**: shard-owned (schema wm-package, `user_email` incluso) scritte
  solo dalla sync; orchestrator-owned (`user_id` valorizzato, `customer_name`, pivot `user_app`,
  tag) mai toccate dopo la creazione; `removed_from_shard_at` è sync-owned, timbrata **e** azzerata
  dalla sync. I `null` del payload non si scrivono mai (colonne NOT NULL con default).
- **Guardie di riconciliazione**: payload vuoto o invalido → no-op con log; rimozioni oltre il 30%
  delle attive dello shard → abort. **Mai delete fisico**: le app sparite vengono marcate dismesse,
  quelle ricomparse riattivate.
- **Registry in `config/shards.php`**: gli slug sono **immutabili**, rinominarli orfanizza le app
  dello shard. `enabled => false` è il kill switch e il rollback operativo — mai il `down()` della
  migration dopo il primo sync multi-shard. I token stanno in `SHARD_TOKEN_<SLUG>`.
- **Contratto di export del wm-package**: `/api/v1/export/apps`, whitelist esplicita in
  `AppExportResource` (mai serializzare le colonne del modello). Aggiungere un campo è compatibile,
  rinominarlo o rimuoverlo richiede una `v2`. Bearer token da `WM_EXPORT_TOKEN`: se assente
  l'endpoint è spento (403).
- **Report PDF dello store**: il bottone compare solo se `hasStorePresence()` (store link o `app_id`
  bundle-like); `storeBundleId()` deriva il package dal link Play Store. **Mai passare l'`app_id`
  numerico allo script Python**: il lookup sullo store fallisce e il PDF esce vuoto. Pre-generazione
  notturna alle 03:30 (`apps:generate-reports --fresh`), nome file shard-qualificato.
- **`App::author()` del wm-package richiede la FK esplicita `user_id`**: l'inferita `author_id` non
  esiste — relazione rotta da sempre, sistemata qui.
