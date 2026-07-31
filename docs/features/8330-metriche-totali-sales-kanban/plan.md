> Ticket: oc:8330

# Metriche totali Sales Kanban — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Aggiungere 3 metric-card (stile Nova Value Metric) sopra la toolbar del kanban Sales, che mostrano totale economico + count per gli stati "To Present", "Presented", "Waiting For Order", alimentate dai dati già recuperati da `fetchCounts()` senza nuovi endpoint.

**Architecture:** Il builder PHP `KanbanCard` espone un nuovo array opzionale `metricStatuses` (lista di status value) serializzato in `cardData`. Il componente Vue (`card.js`, bundle non-sorgente in `dist/`) legge `cardData.metricStatuses`, incrocia ogni valore con l'array `columns` già esistente (stessa fonte usata per le colonne, quindi stesso colore/label — nessun hardcoding di dominio Quote nel JS) e renderizza una card per ognuno, usando le funzioni già esistenti `getHeaderCount()`/`getHeaderSum()`/`formatCurrency()`. Un nuovo stato reattivo `countsLoading` pilota lo spinner; un flag `countsError` gestisce il fallimento del fetch mostrando "—" invece di "0".

**Tech Stack:** Laravel Nova 4 (PHP, card server-side), Vue 3 runtime (nessun build step, componente scritto a mano in `dist/js/card.js` + `dist/css/card.css`), traduzioni Laravel (`lang/it.json`, `lang/en.json`).

## Global Constraints

- Nessuna build step per `nova-components/kanban-card` — `dist/js/card.js` e `dist/css/card.css` sono editati direttamente a mano (nessun sorgente `.vue`).
- Prima di ogni commit che tocca `card.js`, eseguire `node --check nova-components/kanban-card/dist/js/card.js` per validare la sintassi (il file non passa da nessuna build/lint automatica).
- Nessun nuovo endpoint backend: tutti i dati sono già disponibili via `GET /nova-vendor/kanban-card/counts` (già chiamato da `fetchCounts()`).
- Il componente `kanban-card` è condiviso da altri dashboard Nova (es. `app/Nova/Dashboards/Kanban.php`): l'attivazione delle metric-card è strettamente opt-in tramite `metricStatuses` — se non configurato, nessuna card viene renderizzata e il comportamento di quei dashboard resta identico.
- Locale default `it` (`config/app.php`), lingue disponibili `it`/`en` (`lang/it.json`, `lang/en.json`). Ogni testo introdotto deve avere entrambe le traduzioni, nessuna chiave mancante.
- Nessun commit o branch automatico: ogni step di commit in questo piano è un'istruzione testuale per lo sviluppatore, non un'azione da eseguire autonomamente.

---

## File coinvolti

- Modifica: `nova-components/kanban-card/src/KanbanCard.php` — nuova proprietà `metricStatuses`, nuovo metodo builder `metricStatuses()`, esposizione in `getApiConfig()`/`jsonSerialize()`, nuove chiavi in `translations`.
- Modifica: `app/Nova/Dashboards/Sales.php` — chiamata `->metricStatuses([...])` sulla card Kanban.
- Modifica: `nova-components/kanban-card/dist/js/card.js` — computed `metricCards`, stato `countsLoading`/`countsError`, markup Vue delle 3 card, aggiornamento `fetchCounts()`.
- Modifica: `nova-components/kanban-card/dist/css/card.css` — classi `.kanban-metrics-row`, `.kanban-metric-card`, `.kanban-metric-*`.
- Modifica: `lang/it.json`, `lang/en.json` — traduzioni per prefisso "Totale" e messaggio di errore metrica.

Nessun file di test automatico esiste per questo componente (nessun sorgente Vue, nessuna build) — la verifica è manuale, descritta nel Task 5.

---

### Task 1: Backend — `metricStatuses` sul builder `KanbanCard`

**Files:**
- Modify: `nova-components/kanban-card/src/KanbanCard.php`

**Interfaces:**
- Consumes: nessuna (nuova proprietà indipendente)
- Produces: proprietà pubblica `array $metricStatuses = []`; metodo `metricStatuses(array $statuses): self`; chiave `'metricStatuses' => $this->metricStatuses` nel payload restituito da `jsonSerialize()`; nuove chiavi `'metricTotalPrefix'` e `'metricError'` dentro l'array `'translations'` di `jsonSerialize()`. Il frontend (Task 2) legge `cardData.metricStatuses` e `cardData.translations.metricTotalPrefix`/`metricError`.

- [ ] **Step 1: Aggiungere la proprietà pubblica**

In `nova-components/kanban-card/src/KanbanCard.php`, subito dopo la proprietà `columnsConfig` (riga 48, prima del blocco `excludedColumns`), aggiungere:

```php
    /**
     * Status values (subset of columnsConfig) for which a summary metric-card
     * (title + currency total + count) is shown above the toolbar.
     * Empty by default: no metric-card is rendered unless explicitly configured.
     *
     * @var array<string>
     */
    public array $metricStatuses = [];
```

- [ ] **Step 2: Aggiungere il metodo builder**

Subito dopo il metodo `columns()` (dopo la riga 491, prima di `excludedColumns()`), aggiungere:

```php
    /**
     * Show a summary metric-card (title + currency total + count) above the toolbar
     * for each of these status values. Requires aggregateColumnsUsing() to be set,
     * otherwise the sum will always be null and only the count will be shown.
     *
     * @param  array<string>  $statuses
     */
    public function metricStatuses(array $statuses): self
    {
        $this->metricStatuses = array_values(array_filter($statuses, fn($s) => is_string($s) && $s !== ''));

        return $this;
    }
```

- [ ] **Step 3: Esporre il campo in `jsonSerialize()`**

In `jsonSerialize()` (righe 769-811), aggiungere `'metricStatuses' => $this->metricStatuses,` nell'array restituito, subito dopo la riga `'columns' => $columns,`:

```php
        return array_merge(parent::jsonSerialize(), [
            'apiConfig' => $this->getApiConfig(),
            'columns' => $columns,
            'metricStatuses' => $this->metricStatuses,
            'resourceUri' => $this->resourceUri,
```

- [ ] **Step 4: Aggiungere le nuove chiavi di traduzione**

Nello stesso metodo `jsonSerialize()`, dentro l'array `'translations' => [...]` (righe 791-809), aggiungere due chiavi subito dopo `'showLess' => __('Kanban Show Less'),`:

```php
                'showLess' => __('Kanban Show Less'),
                'metricTotalPrefix' => __('Kanban Metric Total Prefix'),
                'metricTotalSuffix' => __('Kanban Metric Total Suffix'),
                'metricError' => __('Kanban Metric Error'),
            ],
```

- [ ] **Step 5: Verificare la sintassi PHP**

Run: `docker exec php81_orchestrator php -l nova-components/kanban-card/src/KanbanCard.php`
Expected: `No syntax errors detected`

- [ ] **Step 6: Commit**

```bash
git add nova-components/kanban-card/src/KanbanCard.php
git commit -m "feat(oc:8330): add metricStatuses builder option to KanbanCard"
```

---

### Task 2: Backend — configurare le 3 metriche sul dashboard Sales

**Files:**
- Modify: `app/Nova/Dashboards/Sales.php`

**Interfaces:**
- Consumes: `KanbanCard::metricStatuses(array $statuses): self` (Task 1)
- Produces: nessuna interfaccia nuova consumata da altri task — è configurazione terminale.

- [ ] **Step 1: Aggiungere la chiamata al builder**

In `app/Nova/Dashboards/Sales.php`, nel metodo `cards()`, aggiungere `->metricStatuses([...])` alla catena esistente, subito dopo `->limitPerColumn(5)` e prima di `->columns(...)`:

```php
                ->limitPerColumn(5)
                ->metricStatuses([
                    QuoteStatus::To_Present->value,
                    QuoteStatus::Presented->value,
                    QuoteStatus::Waiting_For_Order->value,
                ])
                ->columns(
```

- [ ] **Step 2: Verificare la sintassi PHP**

Run: `docker exec php81_orchestrator php -l app/Nova/Dashboards/Sales.php`
Expected: `No syntax errors detected`

- [ ] **Step 3: Verificare manualmente il payload JSON esposto al frontend**

Run (dal container, con un utente Admin/Manager autenticato via browser — questo step si verifica visivamente al Task 5, qui verifichiamo solo che il codice non generi errori server-side):

```bash
docker exec php81_orchestrator php artisan tinker --execute="
\$card = (new App\Nova\Dashboards\Sales())->cards()[0];
dump(\$card->jsonSerialize()['metricStatuses']);
"
```

Expected output: array con i 3 valori `["to present", "presented", "waiting for order"]`

- [ ] **Step 4: Commit**

```bash
git add app/Nova/Dashboards/Sales.php
git commit -m "feat(oc:8330): configure metric-card statuses on Sales kanban dashboard"
```

---

### Task 3: Frontend — stato `countsLoading`/`countsError` e aggiornamento `fetchCounts()`

**Files:**
- Modify: `nova-components/kanban-card/dist/js/card.js`

**Interfaces:**
- Consumes: nessuna (introduce nuovo stato reattivo Vue)
- Produces: `this.countsLoading` (boolean, `data()`), `this.countsError` (boolean, `data()`) — consumati dal markup del Task 4 per pilotare spinner e stato di errore delle metric-card.

- [ ] **Step 1: Aggiungere i due nuovi campi reattivi**

In `nova-components/kanban-card/dist/js/card.js`, nel blocco `data()` (righe 194-222), aggiungere subito dopo `totalCountByStatus: {},` (riga 210):

```javascript
                totalCountByStatus: {},
                countsLoading: true,
                countsError: false,
```

- [ ] **Step 2: Aggiornare `fetchCounts()` per pilotare i nuovi flag**

Sostituire il metodo `fetchCounts()` esistente (righe 733-761) con questa versione, che imposta `countsLoading`/`countsError` attorno alla stessa logica già presente (nessun cambiamento all'URL, agli header o al parsing della risposta):

```javascript
            /**
             * Fetches total counts per status from the backend (same filters as fetchItems).
             * Stores results in totalCountByStatus so the header badge always shows the real total.
             * Drives countsLoading/countsError so metric-cards can show a spinner or an error state.
             */
            async fetchCounts() {
                var self = this;
                self.countsLoading = true;
                self.countsError = false;
                try {
                    var statuses = self.columns.map(function (c) { return c.value; }).join(',');
                    var url = '/nova-vendor/kanban-card/counts?config=' + self.configParam +
                        '&statuses=' + encodeURIComponent(statuses);
                    if (self.filterFieldSelected && self.filterValue) {
                        url += '&filterField=' + encodeURIComponent(self.filterFieldSelected) + '&filterValue=' + encodeURIComponent(self.filterValue);
                    } else if (self.filterField && self.filterValue) {
                        url += '&' + encodeURIComponent(self.filterField) + '=' + encodeURIComponent(self.filterValue);
                    }
                    if (self.searchFields.length && self.searchValue) {
                        url += '&search=' + encodeURIComponent(self.searchValue);
                    }
                    var csrfToken = '';
                    var metaTag = document.querySelector('meta[name="csrf-token"]');
                    if (metaTag) csrfToken = metaTag.getAttribute('content') || '';
                    var response = await fetch(url, {
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-CSRF-TOKEN': csrfToken,
                        },
                    });
                    if (!response.ok) throw new Error('Counts fetch failed: ' + response.status);
                    self.totalCountByStatus = await response.json();
                } catch (e) {
                    console.error('Kanban counts fetch error:', e);
                    self.countsError = true;
                } finally {
                    self.countsLoading = false;
                }
            },
```

- [ ] **Step 3: Validare la sintassi**

Run: `node --check nova-components/kanban-card/dist/js/card.js`
Expected: nessun output (exit code 0 = sintassi valida)

- [ ] **Step 4: Commit**

```bash
git add nova-components/kanban-card/dist/js/card.js
git commit -m "feat(oc:8330): add countsLoading/countsError state to fetchCounts"
```

---

### Task 4: Frontend — computed `metricCards` e markup delle 3 metric-card

**Files:**
- Modify: `nova-components/kanban-card/dist/js/card.js`
- Modify: `nova-components/kanban-card/dist/css/card.css`

**Interfaces:**
- Consumes: `this.countsLoading`, `this.countsError` (Task 3); `this.columns` (computed esistente, riga 230-232); `this.cardData.metricStatuses` (Task 1); `this.getHeaderCount(status)`, `this.getHeaderSum(status)`, `this.formatCurrency(amount)` (metodi esistenti, righe 690-727); `this.translations.metricTotalPrefix`, `this.translations.metricError` (Task 1, esposti via `cardData.translations` — verificare che esista già un computed `translations()`, altrimenti usare `this.cardData.translations`).
- Produces: computed `metricCards` — array di oggetti `{ status, label, color, count, sum }`, usato solo dal markup di questo stesso task (nessun altro task lo consuma).

- [ ] **Step 1: Verificare l'accesso a `translations` nel componente esistente**

Run: `grep -n "translations()" nova-components/kanban-card/dist/js/card.js`

Se il computed `translations()` esiste già (es. `return this.cardData.translations || {};`), usare `this.translations.metricTotalPrefix` negli step successivi. Se non esiste, usare direttamente `this.cardData.translations.metricTotalPrefix` con fallback `|| {}` in ogni riferimento.

- [ ] **Step 2: Aggiungere il computed `metricCards`**

Nel blocco `computed` (dopo `toolbarLabel()`, riga 268-270), aggiungere:

```javascript
            /**
             * Builds the list of summary metric-cards from cardData.metricStatuses,
             * cross-referenced with the existing columns config (same color/label source
             * as the kanban columns, no duplicated status metadata here).
             */
            metricCards() {
                var self = this;
                var statuses = self.cardData.metricStatuses || [];
                return statuses.map(function (status) {
                    var col = self.columns.find(function (c) { return c.value === status; });
                    return {
                        status: status,
                        label: col ? col.label : status,
                        color: col ? col.color : '#9CA3AF',
                        count: self.getHeaderCount(status),
                        sum: self.getHeaderSum(status),
                    };
                });
            },
```

- [ ] **Step 3: Aggiungere il markup delle metric-card nel template**

Nel `template` (stringa multi-riga a inizio file), subito **prima** del blocco `<!-- Toolbar: single search + filter field (combobox) -->` (riga 21-22), aggiungere:

```html
                <!-- Summary metric-cards: total amount + count per status, driven by metricStatuses -->
                <div v-if="metricCards.length" class="kanban-metrics-row">
                    <div
                        v-for="metric in metricCards"
                        :key="metric.status"
                        class="kanban-metric-card"
                        :style="{ borderLeftColor: metric.color }"
                    >
                        <div class="kanban-metric-title">{{ translations.metricTotalPrefix }} {{ metric.label }}</div>
                        <div v-if="countsLoading" class="kanban-metric-loading">
                            <svg class="kanban-spinner" viewBox="0 0 24 24">
                                <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" fill="none" stroke-dasharray="31" stroke-linecap="round"/>
                            </svg>
                        </div>
                        <div v-else-if="countsError" class="kanban-metric-error" :title="translations.metricError">—</div>
                        <template v-else>
                            <div class="kanban-metric-amount">{{ formatCurrency(metric.sum) }}</div>
                            <div class="kanban-metric-count">({{ metric.count }} {{ translations.metricTotalSuffix }})</div>
                        </template>
                    </div>
                </div>

```

La chiave di traduzione `metricTotalSuffix` (es. it: "totale", en: "total") è già stata aggiunta in Task 1 Step 4 insieme a `metricTotalPrefix` e `metricError`.

- [ ] **Step 4: Aggiungere le classi CSS**

In `nova-components/kanban-card/dist/css/card.css`, subito prima del blocco `/* Toolbar: single combobox (search + filter) */` (riga 60), aggiungere:

```css
/* Summary metric-cards row (title + currency total + count per status) */
.kanban-metrics-row {
    display: flex;
    flex-wrap: wrap;
    gap: 0.75rem;
    margin-bottom: 1rem;
}

.kanban-metric-card {
    flex: 1 1 200px;
    min-width: 180px;
    max-width: 100%;
    border: 1px solid #e5e7eb;
    border-left-width: 4px;
    border-radius: 0.5rem;
    background: #fff;
    padding: 0.875rem 1rem;
    overflow: hidden;
}

.dark .kanban-metric-card {
    border-color: #374151;
    background: #111827;
}

.kanban-metric-title {
    font-size: 0.75rem;
    font-weight: 600;
    color: #6b7280;
    text-transform: uppercase;
    letter-spacing: 0.02em;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    margin-bottom: 0.375rem;
}

.dark .kanban-metric-title {
    color: #9ca3af;
}

.kanban-metric-amount {
    font-size: 1.5rem;
    font-weight: 700;
    color: #111827;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.dark .kanban-metric-amount {
    color: #f3f4f6;
}

.kanban-metric-count {
    font-size: 0.8125rem;
    color: #9ca3af;
    margin-top: 0.125rem;
}

.kanban-metric-loading {
    display: flex;
    align-items: center;
    padding: 0.25rem 0;
    color: #9ca3af;
}

.kanban-metric-error {
    font-size: 1.5rem;
    font-weight: 700;
    color: #ef4444;
    cursor: help;
}
```

- [ ] **Step 5: Validare la sintassi JS**

Run: `node --check nova-components/kanban-card/dist/js/card.js`
Expected: nessun output (exit code 0)

- [ ] **Step 6: Commit**

```bash
git add nova-components/kanban-card/dist/js/card.js nova-components/kanban-card/dist/css/card.css
git commit -m "feat(oc:8330): render summary metric-cards above kanban toolbar"
```

---

### Task 5: Traduzioni it/en e verifica manuale end-to-end

**Files:**
- Modify: `lang/it.json`
- Modify: `lang/en.json`

**Interfaces:**
- Consumes: chiavi `'Kanban Metric Total Prefix'`, `'Kanban Metric Error'`, `'Kanban Metric Total Suffix'` referenziate da `__()` in `KanbanCard.php` (Task 1).
- Produces: nessuna — task terminale di verifica.

- [ ] **Step 1: Aggiungere le traduzioni in italiano**

In `lang/it.json`, subito dopo la riga `"Kanban Show Less": "Mostra meno",` (riga 497), aggiungere:

```json
  "Kanban Show Less": "Mostra meno",
  "Kanban Metric Total Prefix": "Totale",
  "Kanban Metric Total Suffix": "totale",
  "Kanban Metric Error": "Errore nel caricamento del totale.",
```

- [ ] **Step 2: Aggiungere le traduzioni in inglese**

In `lang/en.json`, subito dopo la riga `"Kanban Show Less": "Show less",` (riga 709), aggiungere:

```json
  "Kanban Show Less": "Show less",
  "Kanban Metric Total Prefix": "Total",
  "Kanban Metric Total Suffix": "total",
  "Kanban Metric Error": "Error loading total.",
```

- [ ] **Step 3: Validare che i due JSON siano sintatticamente corretti**

Run: `docker exec php81_orchestrator php -r "json_decode(file_get_contents('lang/it.json'), true) !== null && json_decode(file_get_contents('lang/en.json'), true) !== null ? print('OK') : print('INVALID JSON');"`
Expected: `OK`

- [ ] **Step 4: Riavviare la cache config/view (necessario perché Nova può cachare le traduzioni)**

Run: `docker exec php81_orchestrator php artisan config:clear`

- [ ] **Step 5: Verifica manuale in browser**

Aprire il dashboard Sales (`/dashboards/sales`) autenticati come Admin o Manager e verificare:
1. Le 3 metric-card compaiono sopra la toolbar di ricerca, con bordo colorato coerente col colore delle colonne "To Present" (arancione `#F59E0B`), "Presented" (viola `#8B5CF6`), "Waiting For Order" (arancione scuro `#F97316`).
2. Al caricamento iniziale, ogni card mostra lo spinner per un istante, poi importo EUR + "(N totale)".
3. Usando la ricerca o il filtro cliente/utente nella toolbar, le 3 card si aggiornano (spinner poi nuovo valore) in sincronia col ribaltamento delle colonne kanban.
4. Restringendo la finestra del browser, le 3 card vanno in wrap verticale (stack) invece di uscire dallo schermo.
5. Drag & drop di un item tra colonne continua a funzionare come prima (nessuna regressione).
6. Cambiare la lingua utente (se disponibile in Nova) a `en` e verificare che i testi delle card siano tradotti ("Total ..." invece di "Totale ...").
7. (Opzionale, per validare la gestione errore) Da devtools, bloccare temporaneamente la richiesta `/nova-vendor/kanban-card/counts` (Network → block request URL) e ricaricare: le card devono mostrare "—" con tooltip invece di restare bloccate su spinner o mostrare "€ 0,00".

- [ ] **Step 6: Commit**

```bash
git add lang/it.json lang/en.json
git commit -m "feat(oc:8330): add it/en translations for sales kanban metric-cards"
```

---

## Self-Review (eseguita durante la stesura)

**Copertura spec:** ogni requisito di `overview.md` è coperto — builder opt-in (Task 1-2), markup + colore/label da `columns` (Task 4), `countsLoading`/errore (Task 3-4), responsive wrap + ellipsis (Task 4 CSS), traduzioni it/en (Task 5), `node --check` pre-commit (Task 3-4), verifica manuale drag&drop/ricerca/filtro (Task 5).

**Placeholder:** nessuno — ogni step ha codice completo o comando eseguibile con output atteso esplicito.

**Coerenza tipi/nomi:** `metricStatuses` (proprietà PHP) → `metricStatuses` (chiave JSON, invariato) → `cardData.metricStatuses` (letto in JS, Task 4) — nome identico in tutti i punti. `countsLoading`/`countsError` introdotti in Task 3, consumati in Task 4 col nome identico. Le 3 chiavi di traduzione (`metricTotalPrefix`, `metricTotalSuffix`, `metricError`) sono ora tutte specificate nel Task 1 Step 4 (corretto durante la stesura: la terza chiave era stata scoperta necessaria solo scrivendo il markup del Task 4 — vedi nota in quel task).
