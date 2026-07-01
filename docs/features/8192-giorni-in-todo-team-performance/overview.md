> Ticket: oc:8192

# Mostra metrica "giorni in todo" nella card team performance

## Cosa cambia
La card Nova team performance espone una nuova metrica: i giorni lavorativi totali trascorsi in stato `todo` per ogni ticket. Appare come colonna nella tabella per-ticket e come KPI aggregato (media per developer, poi media del team) in cima alla card.

## Perché
Mancava visibilità su quanto tempo i ticket rimangono in attesa prima di essere presi in carico. La metrica aiuta a identificare colli di bottiglia nel processo di assegnazione e prioritizzazione. `StoryMetricsCalculator` aveva già `todoStagnationDays()` (giorni massimi) ma non esposto né usato nella card.

## Requisiti
- [ ] Aggiungere `todoStagnationTotalDays()` in `StoryMetricsCalculator` che somma tutti gli intervalli in `todo` (non il massimo)
- [ ] Esporre `todo_stagnation_days` nel payload di `TeamPerformanceController::getTickets()`
- [ ] Aggiungere `avg_todo_stagnation_days` nell'aggregato developer (`buildAggregate`) e team (`buildTeamAggregate`)
- [ ] Mostrare la colonna "Giorni in todo" nella tabella per-ticket del componente Vue (null → `—`, 0 → `0`)
- [ ] Mostrare il KPI "Media giorni in todo" nel blocco aggregato in cima alla card (null → `—`, 0 → `0`)
- [ ] Nessuna nuova migrazione — la metrica è calcolata dai `StoryLog` esistenti

## Rischi
- **StoryLog incompleti**: ticket molto vecchi potrebbero non avere log per lo status `todo`, restituendo `null`. Mitigazione: null viene mostrato come `—`, non come `0`, per non inquinare le medie.
- **Performance**: `todoStagnationTotalDays()` itera i log per ogni ticket — stesso pattern di `cycleTimeMinutes()`, già in produzione senza problemi. La cache in-memory `$logCache` ammortizza le query.
- **Card.js scritto direttamente**: nessun sorgente Vue, il file compilato è modificato manualmente. Tech debt esistente, non introdotto da questa feature.

## Out of scope
- Modifica al metodo `todoStagnationDays()` esistente (max) — rimane invariato per non rompere eventuali usi futuri
- Filtri o drill-down per la nuova metrica
- Tooltip esplicativo sulla colonna (può essere aggiunto in un ciclo successivo)

## Moduli toccati
- `app/Services/Metrics/StoryMetricsCalculator.php` — nuovo metodo `todoStagnationTotalDays()`
- `app/Http/Controllers/Nova/TeamPerformanceController.php` — aggiunta metrica in `getTickets()` e `buildAggregate()`
- `nova-components/team-performance/dist/js/card.js` — nuova colonna e KPI nel componente Vue
