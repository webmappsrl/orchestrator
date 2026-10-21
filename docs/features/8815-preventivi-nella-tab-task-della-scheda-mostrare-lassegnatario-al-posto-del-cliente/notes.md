> Ticket: oc:8815

# Notes — Preventivi: nella tab Task della scheda mostrare l'assegnatario al posto del cliente

## Deviazioni dal piano

Nessuna deviazione dai task del piano. Il piano è stato scritto prima della stima (e non dopo)
perché `wm-estimate` ne legge il percorso.

## Bug trovati

Nessuno introdotto. Vedi «Follow-up» per un limite preesistente emerso durante il lavoro.

## Decisioni

- **Tag:** nessun tag aggiunto né creato oltre a `orchestrator` e `26Q4` già presenti (dev).
- **Form invariato:** l'Assegnatario resta in fondo al form del tab; nel ramo `quotes` diventano
  quindi due campi dallo stesso `assigneeField()` (dev, domanda 2).
- **Precaricamento escluso:** `with('assignee')` nel ramo `quotes` di `indexQuery()` non è stato
  aggiunto, per toccare solo ciò che chiede il ticket (dev, challenge). La colonna fa una query per
  riga, una in meno della colonna Cliente sostituita.
- **Stima:** proposta `wm-estimate` Misurato 1,6h + Stimato 0,9h = 2,5h; la dev ha impostato 2h.
- **Ambiente locale:** `docker compose up -d` falliva con `postgis/postgis:17-3.5: no match for
  platform in manifest` (Mac arm64); il container è stato avviato dalla dev prima di eseguire i test.

## Follow-up

- **Tab Task in Preventivi archiviati e in Cliente → Preventivi:** `ArchivedQuotes`
  (`archived-quotes`) e `QuoteNoFilter` (`quote-no-filters`) ereditano il tab Task da `Quote`, ma
  `Task::fields()`, `filters()` e `indexQuery()` riconoscono solo `viaResource === 'quotes'`: lì il
  tab mostra ancora la colonna Cliente. Lasciato fuori dalla dev: va in un ticket dedicato, insieme
  al bug di scoping di `QuoteNoFilter` già annotato in `docs/knowledge/task-e-quote.md`.
