> Ticket: oc:8040

# Notes — Invio email a tutti i dev alla creazione di qualsiasi ticket

## Deviazioni dal piano

Il piano iniziale prevedeva due mail class separate (`CustomerNewStoryCreated` e `DevNewStoryCreated`). Dopo una review della PR, si è deciso di unificare in `CustomerNewStoryCreated` unica, con `$novaUrl` calcolato dal ruolo del creator. `DevNewStoryCreated` e il suo template sono stati eliminati.

## Decisioni

- **Mail class unica `CustomerNewStoryCreated`**: il link Nova si adatta al ruolo del creator (`/resources/customer-stories/{id}` per customer, `/resources/stories/{id}` per dev). La logica di selezione vive nel costruttore, il template rimane semplice.
- **Corpo: solo `customer_request`** (condizionale se non vuoto): i developer compilano raramente `customer_request`, ma il campo è lo stesso per tutti i tipi di ticket. La scelta semplifica il template ed evita di esporre `description` nelle email.
- **Dev creatore incluso nei destinatari**: nessuna esclusione — "tutti i dev devono vederlo" include chi ha aperto il ticket.

## Follow-up

- **Invio sincrono**: `Mail::send()` blocca la request per N developer. Tech debt accettato consapevolmente, fuori scope.
