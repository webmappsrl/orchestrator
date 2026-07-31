> Ticket: oc:8330

# Notes — Metriche totali Sales Kanban

## Deviazioni dal piano
Nessuna deviazione rispetto al piano: tutti i 5 task sono stati implementati esattamente come pianificato (builder `metricStatuses()`, config su `Sales.php`, stato `countsLoading`/`countsError`, markup + CSS, traduzioni it/en).

## Bug trovati
Durante la verifica manuale in browser è emerso un problema **non collegato a questa feature**: il drag&drop sulla Kanban generale (`/dashboards/kanban`) falliva con `Predis\Connection\ConnectionException: getaddrinfo for redis failed`. Causa: il container `redis_orchestrator` aveva perso l'alias di rete `redis` sulla rete Docker `orchestrator_laravel` (probabilmente per un riavvio del daemon Docker), quindi il container PHP non riusciva più a risolvere l'hostname per il job di sync calendario dispatchato da `StoryObserver` ad ogni update di Story. Risolto con `docker network disconnect`/`connect --alias redis` sul container Redis. Nessuna modifica di codice necessaria — puro problema di ambiente locale, pre-esistente all'inizio di questa sessione.

Nota anche: il container `php81_orchestrator` esegue solo `php-fpm` senza un webserver HTTP davanti (nessun nginx nel container, nessuna porta 8000 servita di default) — è stato necessario avviare manualmente `php artisan serve --host 0.0.0.0 --port 8000` dentro al container per poter testare in browser. Anche questo è un problema di ambiente pre-esistente, non introdotto da questa feature.

## Decisioni
- Durante la Fase: challenge, l'approccio iniziale di leggere `QuoteStatus::label()/color()` direttamente lato JS è stato scartato in favore di un builder opt-in `metricStatuses()` che dichiara gli stati lato PHP e riusa la config `columns` già esistente lato frontend — nessuna duplicazione di dominio Quote nel componente Vue condiviso, nessun rischio di disallineamento colore/etichetta.
- Aggiunto uno stato reattivo dedicato `countsLoading`/`countsError` (indipendente dal flag `loading` esistente, che copre solo il caricamento iniziale degli item) per pilotare correttamente lo spinner e la gestione dell'errore di fetch richiesti dal dev in Fase: reverse-interaction.

## Follow-up
Nessun tech debt introdotto consapevolmente da questa feature. Il problema di rete Docker/Redis è stato risolto ma potrebbe ripresentarsi ad ogni riavvio del daemon Docker locale — non è stato aperto un ticket dedicato perché è un problema di ambiente locale del singolo sviluppatore, non di configurazione del progetto.
