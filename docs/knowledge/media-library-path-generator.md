# Media Library: path generator e download degli allegati

> Origine: oc:8028

## Stato attuale

- **Il wm-package sovrascrive `path_generator` e `disk_name`**: `WmPackageServiceProvider::packageRegistered()`
  — fase *register*, non `boot()` — fa un `array_merge` sulla config di `media-library`,
  rimpiazzando `CustomPathGenerator` con `WmfePathGenerator` e `disk_name` con `wmfe`. Il
  ripristino di **entrambi** avviene in `AppServiceProvider::register()`, che gira dopo.
- **`disk_name` è hardcodato a `public`** in `AppServiceProvider`: tutti i file storici sono su
  disco `public`, non su S3. Non usare `env('MEDIA_DISK')`, che nel container di sviluppo punta a
  `wmfe`.
- **Tre layout coesistono su disco**: A (`media/Model/name/file`, fino ad apr 2026),
  B (`media/Model/name/id/file`, apr-mag 2026), C (`orchestrator/media/id/file`, da mag 2026).
  `OrchestratorPathGenerator` li tenta in ordine C→B→A; i nuovi upload vanno in C.
- **Nessuna migrazione fisica dei file**: il generator ibrido risolve senza spostare nulla. Ha
  ripristinato l'accesso a 605 dei 631 media legacy (rilevamento di oc:8028; non ricostruibile
  oggi, in locale la tabella `media` conta 650 righe — locale, 2026-09-12).
