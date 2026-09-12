# Sync del Google Calendar dei developer

> Origine: oc:8044

## Stato attuale

- **La sync al save di una Story è un job in coda, non sincrona**: `SyncDeveloperCalendarJob` usa
  `ShouldBeUniqueUntilProcessing` (nessuna sync persa: un save durante l'esecuzione accoda un nuovo
  job), delay di 60 secondi nel costruttore, lock su Redis (`uniqueVia`) e `WithoutOverlapping` — la
  sync è delete-then-recreate, quindi idempotente solo se serializzata. Il save da Nova torna sotto
  i 2 secondi e il bulk edit non va più in timeout.
- **Niente `saveQuietly()` sul cascade demote progress→todo**: gli eventi del modello alimentano
  `StoryLog` → `StoryTimeService` (calcolo ore) e la query del calendario. Il costo delle sync a
  catena si azzera con la dedup del job, non sopprimendo gli eventi.
- **Le date del comando `sync:stories-calendar` si inizializzano in `handle()`, mai nel
  costruttore**: Artisan cacha l'istanza del comando per processo, e nei worker long-running una
  data fissata nel costruttore diventa stantia dopo mezzanotte.
- **Coda `default`, nessuna modifica a Horizon**: il rischio di timeout a 60s è accettato
  consapevolmente (volumi bassi, fallback alla sync schedulata delle 07:45). Il job però **non**
  gira con `tries=1`: `SyncDeveloperCalendarJob.php:43` dichiara `public $tries = 5;` proprio
  perché `WithoutOverlapping` rimette in coda il job sovrapposto e ogni release conta come
  tentativo — `tries=1` è il default del supervisor, non del job. La
  supervisione Horizon è il ticket oc:8059.
- Quali stati entrano nel calendario è trattato in
  [Story: cambi di stato, log e notifiche](story-status-e-notifiche.md).
