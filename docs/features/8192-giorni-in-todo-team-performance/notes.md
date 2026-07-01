> Ticket: oc:8192

# Notes — Mostra metrica "giorni in todo" nella card team performance

## Deviazioni dal piano
- Colonna "Commit" rimossa durante il review gate su richiesta del developer — non aggiungeva valore significativo alla metrica di performance.
- Ordine colonne riorganizzato su richiesta: Cycle Time → Todo >1g → Reopen → Reviews → On Time → PR.
- "Reviews" spostata dopo "Reopen" su richiesta.

## Bug trovati
- Cache Redis `team_perf_avg_{year}_q{quarter}` conteneva il vecchio aggregato senza `avg_todo_stagnation_days` — necessario svuotarla dopo il deploy (o aspettare TTL 1h).

## Decisioni
- **Etichetta "Todo >1g"**: `workingDaysBetween` conta giorni lavorativi completi, quindi un ticket in todo per meno di un giorno lavorativo restituisce 0. Per non confondere l'utente con "Giorni in todo" (che implicherebbe anche frazioni di giorno), l'etichetta è stata resa esplicita.
- **Rendering `>= 1`**: i valori 0 mostrano `-` (indistinguibili da null) perché 0 giorni interi in todo non è informazione utile.
- **Rollback non atomico accettato**: un rollback del solo PHP senza rollback di `card.js` causerebbe `undefined` invece di `-`. Rischio documentato e accettato consapevolmente.

## Follow-up
- Cache invalidation automatica: al momento la cache `team_perf_avg` non viene invalidata quando cambiano i dati. Un meccanismo di invalidazione esplicita potrebbe essere utile in futuro.
