> Ticket: oc:7944

# Notes — Ottimizzazione Costi Hetzner

## Deviazioni dal piano
- `.env.example` non esiste nel progetto — il placeholder ENV non è stato aggiunto. Da creare se il file viene introdotto in futuro.
- I prezzi dei Volumes Hetzner (€0.0476/GB/mese) e Snapshots (€0.0119/GB/mese) sono stati hardcodati nel service poiché l'API Hetzner non espone un endpoint dedicato ai prezzi per queste risorse. Da aggiornare se Hetzner modifica i prezzi di listino.

## Bug trovati
Nessuno durante l'implementazione.

## Decisioni
- **Prezzi Volumes/Snapshots hardcodati**: l'API Hetzner Cloud non restituisce pricing per Volumes e Snapshots nei rispettivi endpoint. I valori sono stati ricavati dalla documentazione pubblica Hetzner (maggio 2026). Valori: Volumes €0.0476/GB/mese, Snapshots €0.0119/GB/mese.
- **Sheet multipli nel CSV**: scelto un foglio per tipo di risorsa (Servers, Floating IPs, Volumes, Load Balancers, Snapshots) per facilità di filtraggio in Excel. Alternativa flat (tutti in una riga) sarebbe stata meno leggibile.
- **Componente Vue self-contained**: seguendo il pattern di kanban-card, il componente Vue è scritto come JavaScript puro registrato via `Nova::script()`, senza build step separato. Evita complessità di setup Webpack/Vite per un componente read-only.
- **Errori per progetto isolati**: un token non valido non blocca il caricamento degli altri progetti — ogni progetto è indipendente nella cache e nella gestione degli errori.

## Follow-up
- Aggiungere DigitalOcean come secondo provider (ciclo successivo) — struttura `config/hetzner.php` è già separata per facilitare un futuro `config/cloud-providers.php`.
- Valutare se mostrare il nome del server assegnato ai Floating IP e Volumes invece dell'ID numerico (richiede un'ulteriore chiamata API o un map locale dalla lista server).
- Aggiungere storico costi (trend mensile) se il management lo richiede — richiede persistenza DB.
- Aggiornare periodicamente i prezzi hardcodati di Volumes e Snapshots.
