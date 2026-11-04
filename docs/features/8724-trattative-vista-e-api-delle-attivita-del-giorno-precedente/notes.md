> Ticket: oc:8724

# Notes — Trattative: vista e API delle attività del giorno precedente

## Decisioni

- Tag associato al ticket in `environment-setup: tag-ambiente`: `orchestrator` (id 590). Nessun tag
  nuovo creato, su indicazione della dev.
- Tag di contenuto (`overview: tag-contenuto`): cercati fra i tag esistenti i termini «trattative»,
  «crm», «quote», «preventivi», «api». Nessun tag pertinente (per «api» solo due tag Documentation
  di altri progetti, id 311 e 318). Nessun tag associato e nessuno creato, su indicazione della dev.

## Domande aperte

Le domande per il reviewer (Alessandro Peci, indicato da Giuseppe Bonfanti nello scrum del
04/11/2026) sono nella sezione «Domande aperte per il reviewer» di `overview.md`, con lo spazio per
le risposte.

## Riferimenti

- Nello scrum del 04/11/2026 Giuseppe Bonfanti dice: «Alessio addirittura se l'è fatta da solo su un
  branch di orchestrator». Il lavoro è la tabella sperimentale «Che cosa ho fatto ieri» nella
  dashboard Kanban-2 (commit `e24bdc82` e `3115e863`, Alessio Piccioli, 23/11/2025, file
  `app/Nova/Dashboards/Kanban2.php` e `story-viewer-experimental-activities.blade.php`). È sui
  branch `montagna-servizi`, `ms-fundraising-202602` e `ms-tag-price`, non su `develop`. Riguarda le
  Story (`story_logs`), non le trattative: da leggere come riferimento per il piano.
- Challenge del 03-04/11/2026, rischi giudicati ipotetici e non portati a domanda: fuso orario dei
  timestamp (già fra i rischi dell'overview); un errore nel diario che blocca il salvataggio di
  trattative e task (da coprire nel piano: il diario non deve mai far fallire il salvataggio);
  crescita della tabella senza pulizia periodica; task creati su trattative già chiuse.
