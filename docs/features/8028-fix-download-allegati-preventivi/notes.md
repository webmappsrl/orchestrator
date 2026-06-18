> Ticket: oc:8028

# Notes — Fix download allegati — path generator ibrido C→B→A

## Deviazioni dal piano

- Il test Layout A inizialmente usava `assertStringNotContainsString('/' . $media->id, ...)` — fragile quando `$quote->name` è un numero uguale a `$media->id`. Sostituito con `assertEquals('media/Quote/{name}/', $path)` più preciso.
- I test di fallback B e A richiedevano di rimuovere esplicitamente il file da Layout C dopo `addMedia()`, perché Spatie scrive il file su disco al momento dell'upload e il check C lo trovava prima del fallback.

## Bug trovati

- La causa radice non era `return $file` senza `toResponse()` (come ipotizzato inizialmente): il modello `Media` implementa `Responsable` e `toResponse()` viene chiamato correttamente. Il problema era che `WmfePathGenerator` calcolava un path inesistente.
- `wm-package` sovrascrive sia `path_generator` che `disk_name` tramite `array_merge` nel suo ServiceProvider. `AppServiceProvider` ripristinava solo `media_model`, lasciando attivi entrambi gli override di wm-package.
- Su 631 record media, solo 26 (mag–giu 2026) erano nel path corretto. I restanti 605 erano fisicamente su disco ma irraggiungibili.

## Decisioni

- `disk_name` hardcodato a `public` in `AppServiceProvider` (non usa `env('MEDIA_DISK')`): scelta consapevole, tutti i file storici sono su disco `public`.
- Generator globale senza eccezioni per tipo di modello: il check Layout C è il primo ed è O(1) per i media già corretti.
- Nessuna migrazione fisica dei file: il generator ibrido risolve il problema senza spostare nulla.

## Follow-up

- Aprire una issue/PR su `wm-package` per documentare che il suo ServiceProvider sovrascrive `path_generator` e `disk_name`, così altri progetti che includono wm-package sono consapevoli del side effect.
- I 16 file irrecuperabili (non trovati in nessun layout) andranno verificati manualmente.
- Valutare se `CustomPathGenerator` può essere rimosso ora che è sostituito da `OrchestratorPathGenerator`.
