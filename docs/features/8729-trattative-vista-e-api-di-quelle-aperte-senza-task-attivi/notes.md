> Ticket: oc:8729

# Notes — Trattative: vista e API di quelle aperte senza task attivi

## Deviazioni dal piano

## Bug trovati

## Decisioni

- Ricerca dei tag di ambiente (`orchestrator`, `backend`) saltata su indicazione della dev, in
  pianificazione: nessun tag associato.
- Proposta dei tag di contenuto (`trattative`, `api`) saltata su indicazione della dev, dopo
  l'approvazione dell'overview: nessun tag associato né creato.
- Stima rinviata dalla dev: `wm-estimate` ha proposto Misurato 7,9h (tempo trascorso, pause
  comprese) + Stimato 2,7h = 10,6h, non approvata né scritta su Orchestrator.
- Review dell'overview con `wm-review-ticket` prima del commit (esito tenuto in conversazione, non
  scritto sul ticket). Corretti tre bloccanti: «Cosa cambia» allineato a «Rischi» (il client confronta
  `/api/quotes` e `/api/tasks`); scoping `forUser` dichiarato «salvo esito della domanda aperta»;
  `ArchivedQuotes::lenses()` vuoto per non ereditare la Lens. Cleanup applicati: tolto l'elenco unico
  degli stati chiusi nell'enum (non chiesto dal ticket, avrebbe coperto 2 dei 6 punti che duplicano la
  lista); tolto il 422 su `due_from` > `due_to` (convenzione di `StoryController::validatedDateFilter`);
  422 su `quote_id` non intero. Cleanup non applicati, su scelta della dev: Out of scope (endpoint
  dedicato, tool MCP `list_tasks` che accetta solo `sort`), test su `/docs/api.json` e casi della Lens,
  riformulazione della riga «task attivo con una scadenza», scope sul modello ed eager load di
  `customer` nella Lens (da riprendere nel piano).

## Follow-up
