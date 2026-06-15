> Ticket: oc:8028

# Fix download allegati — path generator ibrido C→B→A

## Cosa cambia
Il sistema tornerà a trovare tutti gli allegati storici. Viene creato un `OrchestratorPathGenerator` che per ogni media prova i path in ordine C→B→A sul disco `public`. I nuovi upload continuano ad andare in Layout C. Nessun file viene spostato.

## Perché
`wm-package` sovrascrive a runtime la config di `media-library` tramite `array_merge` nel suo ServiceProvider, rimpiazzando:
- `path_generator` → `WmfePathGenerator` (produce Layout C: `orchestrator/media/{id}/`)
- `disk_name` → `wmfe` (S3, mentre i file reali sono su disco `public`)

`AppServiceProvider` ripristina solo `media_model`, lasciando attivi path generator e disk di `wm-package`. Il risultato: `getPath()` calcola sempre il path C anche per file scritti con layout A o B.

**Timeline dei tre layout:**

| Periodo | Layout | Path | File |
|---------|--------|------|------|
| fino a ~apr 2026 | **A** — `CustomPathGenerator` vecchio | `media/{Model}/{name}/{file}` | ~535 |
| apr–mag 2026 | **B** — `CustomPathGenerator` aggiornato | `media/{Model}/{name}/{id}/{file}` | ~54 |
| mag 2026 – oggi | **C** — `WmfePathGenerator` | `orchestrator/media/{id}/{file}` | 26 |

Solo i 26 file in Layout C vengono trovati. Gli altri 605 sono fisicamente presenti su disco ma irraggiungibili perché il path calcolato non corrisponde.

## Requisiti
- [ ] Nuovo `OrchestratorPathGenerator`: `getPath()` tenta C → se il file non esiste su disco prova B → poi A. I nuovi upload vanno in C (path di default quando nessun layout legacy corrisponde)
- [ ] `AppServiceProvider::register()` ripristina `path_generator => OrchestratorPathGenerator` e `disk_name => public` dopo il merge di `wm-package`
- [ ] Test: verifica che `AppServiceProvider` ripristini correttamente entrambe le config dopo il boot
- [ ] Test: per un media con file in Layout A, `getPath()` restituisce il path A
- [ ] Test: per un media con file in Layout B, `getPath()` restituisce il path B
- [ ] Test: per un media senza file legacy, `getPath()` restituisce il path C (default nuovi upload)

## Rischi
- **`array_merge` di wm-package**: se `wm-package` aggiunge altri override in futuro, il ripristino in `AppServiceProvider` deve essere aggiornato. I test CI lo catturano.
- **Performance**: `getPath()` esegue fino a 2 check su disco (`Storage::exists`) per ogni media legacy. Con 605 media è accettabile; se il volume cresce molto si può aggiungere una colonna `path_layout` in DB.
- **16 file irrecuperabili**: non trovati in nessun layout. Vengono loggati (warning) e il generator restituisce il path C di default — il download fallirà con un errore chiaro invece di un crash.
- **Disk wmfe nel container di sviluppo**: il container ha `MEDIA_DISK=wmfe` ma produzione usa `public`. Il ripristino in `AppServiceProvider` forza `public` indipendentemente dalla variabile d'ambiente.

## Out of scope
- Migrazione fisica dei file su disco
- Audit e rimozione dei duplicati (es. media 556/557 stesso PDF)
- Fix upstream del package `ebess/advanced-nova-media-library`
- Media su altri progetti che usano `wm-package`

## Moduli toccati
| File | Azione | Repo |
|------|--------|------|
| `app/Services/MediaLibrary/OrchestratorPathGenerator.php` | Creare — path generator ibrido C→B→A | principale |
| `app/Providers/AppServiceProvider.php` | Modificare — ripristinare `path_generator` e `disk_name` | principale |
| `tests/Feature/OrchestratorPathGeneratorTest.php` | Creare — test fallback C→B→A e ripristino config | principale |
