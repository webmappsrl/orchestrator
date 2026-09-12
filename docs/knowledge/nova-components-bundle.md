# Nova components: bundle scritti a mano

I componenti custom sotto `nova-components/` non hanno una build: i file in `dist/` sono JS e CSS
**scritti direttamente a mano**, senza sorgente Vue, senza Webpack o Vite, senza lint automatica.

## Stato attuale

- **Ogni modifica a un `dist/js/*.js` va validata con `node --check` prima del commit.** Un errore
  di sintassi in `nova-components/kanban-card/dist/js/card.js` rompe l'intero componente Kanban
  (drag&drop, ricerca, colonne) per **tutti** i dashboard che lo usano, non solo per quello che si
  stava modificando. Vale allo stesso modo per `team-performance` (oc:8192) e `kanban-card`
  (oc:8330).
- **Il rollback non è atomico**: rollbackare il solo PHP senza il `card.js` corrispondente produce
  `undefined` nel frontend al posto dei valori attesi. I due file vanno rollbackati insieme
  (oc:8192).
- **I componenti nuovi seguono lo stesso pattern**: `nova-components/hetzner-monitoring/` è un JS
  puro registrato via `Nova::script()`, nessun build step separato per aggiunte read-only (oc:7944).

### Metric-card del Kanban (oc:8330)
- **Attivazione strettamente opt-in** via `KanbanCard::metricStatuses(array $statuses)`: il
  componente è condiviso da dashboard che non hanno il concetto di `QuoteStatus` (es.
  `app/Nova/Dashboards/Kanban.php`). Le metric-card si renderizzano solo se il dashboard configura
  esplicitamente `metricStatuses` (default array vuoto) — nessuna euristica JS sui nomi degli stati,
  nessun impatto sugli altri dashboard.
- **Colore ed etichetta si leggono dalla config `columns` già esistente**, passata lato PHP da
  `QuoteStatus::cases()`, non da una nuova chiamata a `label()`/`color()` lato frontend: fonte unica,
  nessun disallineamento fra colore della colonna e colore della metric-card.
- **Nessun nuovo endpoint**: le metric-card riusano `totalCountByStatus`, già popolato da
  `fetchCounts()` per il badge delle colonne, e le funzioni esistenti
  `getHeaderCount()`/`getHeaderSum()`/`formatCurrency()`.
- **`countsLoading`/`countsError` sono flag distinti da `loading`**, che copre solo il caricamento
  iniziale pesante degli item: `fetchCounts()` viene richiamato anche da ricerca e filtro senza
  passare per `loading`, e serve distinguere un fallimento di rete (mostrato come `—` con tooltip)
  da un totale realmente a zero.
