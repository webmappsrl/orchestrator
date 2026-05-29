> Ticket: oc:7944

# Notes — Ottimizzazione Costi Hetzner

## Deviazioni dal piano
- `.env.example` non esiste nel progetto — il placeholder ENV non è stato aggiunto. Da creare se il file viene introdotto in futuro.
- I prezzi dei Volumes Hetzner (€0.0476/GB/mese) e Snapshots (€0.0119/GB/mese) sono stati hardcodati nel service poiché l'API Hetzner non espone un endpoint dedicato ai prezzi per queste risorse. Da aggiornare se Hetzner modifica i prezzi di listino.

## Bug trovati
- Export CSV con `WithMultipleSheets`: il formato CSV esportava solo il primo foglio. Risolto passando a **XLSX** (`hetzner-monitoring.xlsx`).

## Decisioni
- **Prezzi Volumes/Snapshots hardcodati**: l'API Hetzner Cloud non restituisce pricing per Volumes e Snapshots nei rispettivi endpoint. I valori sono stati ricavati dalla documentazione pubblica Hetzner (maggio 2026). Valori: Volumes €0.0476/GB/mese, Snapshots €0.0119/GB/mese.
- **Export XLSX multi-foglio**: 8 fogli — Riepilogo, **Tutto** (inventario unificato con note), **Azioni da fare** (nota presente AND `action_priority` ≠ `ok`), poi un foglio per tipo risorsa con colonne Priorità/Azione/Note.
- **Foglio Azioni da fare**: risorse con `action_priority` ≠ `ok` **oppure** con nota salvata (anche se azione OK), per coprire follow-up annotati manualmente.
- **Sheet multipli (storico)**: in origine CSV con un foglio per tipo; sostituito da XLSX per supportare tutti i fogli.
- **Componente Vue self-contained**: seguendo il pattern di kanban-card, il componente Vue è scritto come JavaScript puro registrato via `Nova::script()`, senza build step separato. Evita complessità di setup Webpack/Vite per un componente read-only.
- **Errori per progetto isolati**: un token non valido non blocca il caricamento degli altri progetti — ogni progetto è indipendente nella cache e nella gestione degli errori.

## Deploy in produzione

### Passo manuale (prima del `git pull`) — solo la prima volta
Aggiungere al `.env` di produzione le variabili ENV con i token Hetzner (i valori reali sono **fuori da git**, recuperarli dal gestore delle credenziali aziendale):

```
HETZNER_TOKEN_DEFAULT=<token>
HETZNER_TOKEN_WEBMAPP_SERVER=<token>
HETZNER_TOKEN_OSM2CAI=<token>
HETZNER_TOKEN_GEOBOX1=<token>
HETZNER_TOKEN_EUMA=<token>
HETZNER_TOKEN_DEVELOP=<token>
HETZNER_TOKEN_GEOBOX2=<token>
HETZNER_TOKEN_DEVOPS=<token>
HETZNER_TOKEN_FORESTAS=<token>
HETZNER_TOKEN_ERSAF=<token>
```

### Passi automatici — già coperti da `scripts/deploy_prod.sh`

| Step | Comando | Effetto per questo ticket |
|---|---|---|
| 1 | `git pull` | Porta nova-component, migration, controller, model, JS/CSS |
| 2 | `composer install` | Registra `wm/hetzner-monitoring` (path repository già in `composer.json`) |
| 3 | `php artisan migrate --force` | Crea tabella `hetzner_monitoring` con colonna `properties` (jsonb) |
| 4 | `php artisan optimize:clear` | Pulisce config, route, view cache |
| 5 | `php artisan horizon:terminate` | Riavvia Horizon |

### Non necessario
- **npm build** — Vue component già compilato e committato in `dist/js/card.js`, nessun build step
- **submodule update** — `nova-components/hetzner-monitoring/` è nel repo principale, non è un submodule
- **Redis flush** — la cache Hetzner scade da sola (TTL 15 min)

## Follow-up
- Valutare se mostrare il nome del server assegnato ai Floating IP e Volumes invece dell'ID numerico (richiede un'ulteriore chiamata API o un map locale dalla lista server).
- Aggiungere storico costi (trend mensile) se il management lo richiede — richiede persistenza DB.
- Aggiornare periodicamente i prezzi hardcodati di Volumes e Snapshots.
