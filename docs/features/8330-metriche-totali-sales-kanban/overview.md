> Ticket: oc:8330

# Metriche totali Sales Kanban per stati "To Present / Presented / Waiting For Order"

## Cosa cambia
Nel componente Kanban del dashboard Sales (`nova-components/kanban-card/`), viene aggiunta una riga di 3 "metric card" in stile Nova Value Metric, posizionata **sopra** la toolbar di ricerca/filtro esistente. Ogni card mostra:
- Titolo: `Totale ` + label dello stato (riuso di `QuoteStatus::label()`, es. "Totale To Present")
- Importo grande: somma economica (`sum`) formattata in EUR (riuso di `formatCurrency()` già esistente)
- Count tra parentesi: `(N totale)` (riuso di `getHeaderCount()` già esistente)
- Accento visivo: bordo/colore laterale ripreso da `QuoteStatus::color()` / `column.color`, coerente con il colore già usato per la colonna kanban corrispondente

Le 3 card sono alimentate dagli stessi dati già recuperati da `fetchCounts()` (`totalCountByStatus`), quindi si aggiornano automaticamente con ricerca e filtro cliente/utente, senza nuove chiamate HTTP né nuovi endpoint backend.

Durante il fetch (`loading === true` per il caricamento iniziale, o comunque mentre `fetchCounts()` è in corso), ogni card mostra uno spinner al posto del valore.

Layout responsive: le 3 card sono disposte in riga con `flex-wrap: wrap` — su schermi stretti vanno in stack verticale invece di scrollare orizzontalmente.

## Perché
L'utente Sales vuole un quadro d'insieme immediato (totale economico + conteggio) per gli stati "da presentare", "presentata", "in attesa di ordine" senza dover scorrere/contare manualmente le colonne del kanban, con lo stesso stile visivo già familiare delle Value Metric Nova (es. "Totale Contratti Attivi" nella pagina Rinnovi).

## Requisiti
- [ ] Nuovo metodo opzionale sul builder Kanban (es. `->metricStatuses([...])` in `KanbanCard.php`), usato da `app/Nova/Dashboards/Sales.php` per dichiarare esplicitamente i 3 stati (`to present`, `presented`, `waiting for order`) da mostrare come metric-card; il componente Vue resta agnostico di dominio — se `metricStatuses` è assente/vuoto, nessuna card viene renderizzata (comportamento invariato per `Kanban.php` e altri dashboard che non lo configurano)
- [ ] 3 metric-card renderizzate come markup Vue custom nella toolbar del componente `kanban-card`, una per ogni valore in `metricStatuses`
- [ ] Ogni card mostra: titolo (`Totale ` + `column.label` dello stato), importo EUR formattato, count tra parentesi "(N totale)" — colore/label letti dalla stessa config `columns` già usata per le colonne kanban (nessuna duplicazione/hardcoding di stati Quote nel JS)
- [ ] Le card sono alimentate esclusivamente da `totalCountByStatus` (nessun nuovo endpoint, nessuna nuova fetch)
- [ ] Nuovo stato reattivo `countsLoading` (true prima di ogni chiamata `fetchCounts()`, false nel `finally`), usato dalle metric-card per mostrare uno spinner — indipendente dal flag `loading` esistente (che copre solo il caricamento iniziale degli item)
- [ ] Gestione esplicita del fallimento di `fetchCounts()`: in caso di errore la card mostra un simbolo di errore (es. "—" con tooltip), mai un "0" che potrebbe essere scambiato per un dato reale
- [ ] Le card si aggiornano automaticamente ad ogni chiamata di `fetchCounts()` (ricerca, filtro cliente/utente, cambio pagina/colonna)
- [ ] Accento visivo (bordo/colore) ripreso da `column.color` per lo stato corrispondente (stessa fonte dati delle colonne, nessun disallineamento possibile)
- [ ] Layout responsive con `flex-wrap: wrap` (stack verticale su schermi stretti, mai scroll orizzontale forzato) + `text-overflow: ellipsis` e `min-width` sulla card per evitare rotture di layout con importi EUR molto lunghi
- [ ] Traduzioni complete in `it` (default) e `en` per ogni testo introdotto (nessuna chiave mancante in nessuno dei due file lingua)
- [ ] Le 3 card sono posizionate sopra il blocco toolbar di ricerca/filtro esistente, non alterano il comportamento di ricerca/filtro/drag&drop già presente
- [ ] Validazione sintattica (`node --check nova-components/kanban-card/dist/js/card.js`) prima di ogni commit, più verifica manuale di drag&drop/ricerca/filtro dopo la modifica (nessuna build/CI automatica su questo file)

## Rischi
- Il componente `kanban-card` è condiviso da altre dashboard Nova (es. `Kanban.php`) — mitigato rendendo l'attivazione esplicitamente opt-in via `metricStatuses` configurato lato PHP, nessuna euristica JS basata su nomi di stato.
- `totalCountByStatus[status]` può essere `undefined` per uno stato non ancora caricato — le card mostrano spinner (`countsLoading`) finché il primo fetch non è completato, poi `0` se il valore resta assente a fetch concluso con successo.
- Modifica diretta a `card.js` (bundle non-sorgente, nessuna build/lint automatica): un errore di sintassi rompe l'intero Kanban Sales per tutti gli utenti (drag&drop, ricerca, colonne). Mitigato con `node --check` pre-commit e test manuale completo del componente dopo la modifica.

## Out of scope
- Nessuna nuova rotta/endpoint backend (i dati sono già disponibili via `fetchCounts()`)
- Nessuna modifica alla logica di aggregazione (`SalesQuoteColumnAggregator`)
- Nessuna Value Metric Nova PHP-side standard (già escluse dal ticket per il problema di reattività ai filtri)
- Nessuna modifica ad altre dashboard che usano `kanban-card` (es. `Kanban.php`) al di fuori del dashboard Sales

## Moduli toccati
- `nova-components/kanban-card/src/KanbanCard.php` — nuovo metodo `metricStatuses()` sul builder, esposto a `cardData`
- `app/Nova/Dashboards/Sales.php` — configura `->metricStatuses(['to present', 'presented', 'waiting for order'])`
- `nova-components/kanban-card/dist/js/card.js` — markup Vue delle 3 metric-card, stato `countsLoading`, gestione errore fetch (nessun build step, modifica diretta con validazione `node --check`)
- `nova-components/kanban-card/dist/css/card.css` — nuove classi per le metric-card (layout, spinner, accento colore, responsive wrap, ellipsis)
- `lang/it.json`, `lang/en.json` — traduzioni per i nuovi testi introdotti
