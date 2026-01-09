# Management - Panoramica

Strumenti di controllo, monitoraggio e verifica delle attività del sistema Orchestra.

## Sezioni

- **Report**: Reportistica e analisi delle attività
- **Activity Check**: Verifica e monitoraggio delle attività
- **Ticket Status Flow**: Flusso degli stati dei ticket
- **Automatic Status Changes**: Modifiche automatiche dello stato

## Documentazione Disponibile

### [Flusso Stati Ticket](ticket-status-flow.md)
Documentazione completa sul flusso naturale dell'evoluzione dei ticket nel sistema:
- Stati disponibili e loro significato
- Flusso principale (Happy Path)
- Flussi alternativi (Backlog, Problem, Waiting, Rejected)
- Transizioni automatiche
- Regole di validazione
- Diagramma di flusso
- Best practices per sviluppatori, tester e manager

### [Modifiche Automatiche Stato](automatic-status-changes.md)
Documentazione tecnica dettagliata su tutti i punti nel codice dove lo stato di un ticket viene modificato automaticamente:
- Comandi schedulati
- StoryObserver events
- Story Model events
- Job per creazione story
- Comandi manuali
- Creazione e aggiornamento story logs
- Riepilogo tabellare completo

## Convenzioni

- Gli stati dei ticket seguono un flusso definito (vedi [Flusso Stati Ticket](ticket-status-flow.md))
- Le modifiche automatiche sono documentate in dettaglio (vedi [Modifiche Automatiche Stato](automatic-status-changes.md))
- Tutte le modifiche di stato vengono registrate in `story_logs` per tracciabilità

