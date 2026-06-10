> Ticket: oc:8040

# Notes — Invio email a tutti i dev alla creazione di qualsiasi ticket

## Deviazioni dal piano

Nessuna — implementazione aderente al plan.

## Decisioni

- **Due mail class separate** (`CustomerNewStoryCreated` / `DevNewStoryCreated`) invece di una sola: le differenze concrete sono il campo del corpo (`customer_request` vs `description` con fallback) e la rotta Nova del link. Scelta deliberata per chiarezza e reversibilità — una futura unificazione in `NewStoryCreated` con parametro `$novaResource` è possibile e a basso costo.
- **Dev creatore incluso nei destinatari**: nessuna esclusione — "tutti i dev devono vederlo" include chi ha aperto il ticket.
- **`CustomerNewStoryCreated` invariata**: il flusso customer non è stato toccato.

## Follow-up

- **Unificazione mail class**: `CustomerNewStoryCreated` e `DevNewStoryCreated` condividono subject, mittente e struttura HTML. Si potrebbe unificare in `NewStoryCreated` con parametro per la rotta Nova. Da valutare in ciclo successivo.
- **Test con `Mail::fake()` sul flusso customer esistente**: il test `customer_creator_still_sends_customer_mail_to_all_developers` copre la regressione, ma sarebbe utile un test dedicato alla suite customer pre-esistente.
- **Invio sincrono**: `Mail::send()` blocca la request per N developer. Tech debt accettato consapevolmente, fuori scope.
