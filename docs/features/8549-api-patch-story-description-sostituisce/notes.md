> Ticket: oc:8549

# Notes — API: PATCH story deve sostituire la description, non appenderla

## Deviazioni dal piano

Nessuna deviazione rilevante.

## Bug trovati

Nessuno oltre a quello oggetto del ticket.

## Decisioni

- In fase di challenge sono stati sollevati rischi collaterali (possibile consumer esterno
  affidato all'append, `description: null`/stringa vuota che cancella il campo silenziosamente,
  perdita irreversibile dello storico note già accumulate). Il dev ha chiesto esplicitamente di
  non espanderli in requisiti aggiuntivi per questo ticket: restano rischi accettati, non
  mitigati in questo ciclo.

## Follow-up

- `Story::addDevNote()` (`app/Models/Story.php` ~riga 511) non ha più chiamanti in `app/` dopo
  questo fix (unico caller era `StoryController::update()`). Non rimosso in questo ciclo perché
  non richiesto dal ticket — da valutare come cleanup separato.
