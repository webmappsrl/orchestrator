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
  **⚠️ Superati in parte dallo scrum del 20/10 (voce sotto):** il confronto lato client fra
  `/api/quotes` e `/api/tasks` non è più la strada per le trattative scoperte; lo scoping `forUser`
  resta invariato senza condizioni e la domanda «Task di tutti o solo dell'utente» è tolta; lo scope sul
  modello è ora un requisito (definizione unica per Lens e API). Restano validi `ArchivedQuotes::lenses()`
  vuoto, i 422 su `quote_id` e il no al 422 su `due_from` > `due_to`, l'eager load di `customer` da
  riprendere nel piano.
- Scrum del 20/10/2026, review dell'overview con Alessandro Peci e Rubens Garofalo (trascrizione
  incollata dalla dev): Lens confermata («optiamo per la lens»); per l'API, parametro su
  `GET /api/quotes` che restituisce solo le trattative senza task attivi («metterei semplicemente un
  altro parametro in più per tornarmi quelli che non hanno task attivi. Lo metterei direttamente a sto
  punto dentro le pie di quot»), perché `/api/quotes` non filtra per utente e il confronto con
  `/api/tasks` sbaglia sui task dei colleghi. Le modifiche a `/api/tasks` della call del 30/09 restano
  su scelta della dev, perché le aveva chieste Giuseppe (assente allo scrum): la conferma è chiesta al
  reviewer nell'overview.
- Seconda review dell'overview con `wm-review-ticket`, dopo lo scrum (esito tenuto in conversazione).
  Corretti due bloccanti: l'esempio «quali task ha la trattativa 42?» per `/api/tasks`, falso per lo
  scoping `forUser`, sostituito, e l'avviso Scramble esteso a tutti i filtri di `/api/tasks`; queste
  note aggiornate. Cleanup applicati: valori ammessi e lettura booleana di `without_active_tasks`,
  regola passata da variabile per Scramble; `QuoteApiDocsTest` fra i moduli; `QuoteNoFilter` con
  `lenses()` vuoto; tabella Lens/Filter riformulata (menu a tendina, esclusione delle chiuse dallo
  scope); `quote_status` `string|null`; moduli di `/api/tasks` marcati «salvo esito»; pagina
  `quote-api-e-pdf.md` per il parametro e la Lens. Non applicati: elenco unico degli stati chiusi
  (già scartato), eager load di `customer` (nel piano). Da chiarire: le Quote `template` in stato
  aperto e senza task comparirebbero fra le trattative scoperte; non verificato, perché il DB locale
  non ha template.

## Follow-up
