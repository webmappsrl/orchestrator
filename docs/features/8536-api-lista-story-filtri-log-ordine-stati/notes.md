> Ticket: oc:8536

# Notes — API: lista story con filtri, log delle modifiche e ordine degli stati

## Deviazioni dal piano

- **`superpowers:writing-plans`/`superpowers:executing-plans` non disponibili in questo ambiente**:
  `plan.md` è stato scritto direttamente (stessa struttura richiesta: header con ticket, step
  numerati con file/decisioni, commit convention) e l'implementazione è stata eseguita direttamente
  invece di passare dall'entry point Superpowers previsto dal workflow. Nessun impatto sul
  risultato — solo sul percorso per arrivarci.
- **Esclusione log "watch"/tag: `jsonb_exists()` invece dell'operatore `?|` prescritto dal piano**.
  Il piano (nota trasversale) prevedeva `whereRaw("... changes::jsonb ?| array[...]")` senza
  bindings. In implementazione si è preferito `jsonb_exists(changes::jsonb, 'chiave')` in OR,
  semanticamente equivalente e verificato più robusto rispetto al conflitto `?`/placeholder PDO
  riscontrato in fase di reverse-interaction. Nota: esiste già altrove nel codebase
  (`Story.php:614`, `effectiveMinutesForStory()`) una terza forma per lo stesso problema
  (`changes::jsonb ?? 'status'`, doppio `?` per un singolo escape) — le tre forme (`?|`, `??`,
  `jsonb_exists`) non sono state riconciliate in un'unica convenzione, segnalato in review come
  cleanup non risolto in questo ciclo.

## Bug trovati (review formale `wm-review-ticket`, prima dei commit)

Cinque finder paralleli (correctness, side-effect/bug, deviazioni dal piano, cleanup, altitude)
hanno trovato due bloccanti, corretti e verificati con test dedicati prima di procedere:

1. **`per_page` negativo disabilitava completamente la paginazione** (Eloquent ignora un `LIMIT`
   negativo, restituendo l'intera tabella in una risposta). Corretto: `resolvePerPage()` clampa
   `per_page` a `[1, 100]`. Verificato empiricamente (5038 righe → 1 sola con `per_page=-1` dopo il
   fix, prima il fix restituiva tutte le 5038).
2. **Filtro data malformato (`created_from=not-a-date`) causava un 500 non gestito**, con stack
   trace e query SQL esposti nella risposta quando `APP_DEBUG=true`. Corretto:
   `validatedDateFilter()` risponde 422 su formato diverso da `Y-m-d`.

## Decisioni

- **Autorizzazione**: `index()` usa `authorize('viewAny', Story::class)`, `logs()` usa
  `authorize('view', $story)` (mirror di `show()`, opera su un'istanza precisa). `StoryPolicy`
  ritorna `true` per entrambe le ability per ogni utente autenticato — nessun cambio di
  comportamento, solo allineamento alla convenzione già in uso da `Tag`/`Task`/`QuoteController`
  (che invece `StoryController` non aveva mai adottato).
- **Cleanup applicati oltre ai due bloccanti** (su richiesta esplicita del dev, dopo la review):
  - Validazione 422 anche per `tag_id`/`user_id`/`creator_id`/`changed_by` non numerici (prima
    venivano convertiti silenziosamente a `0`, con risultato vuoto senza segnale d'errore).
  - Fix N+1 su `with=logs`: eager-load una volta per pagina (`Collection::load()`) invece di una
    query per story. Verificato empiricamente: conteggio query costante al variare di `per_page`
    (con `per_page=2` e `per_page=5` sullo stesso dataset).
  - Copertura test completata per tutti i filtri elencati nello Step 5 del piano che risultavano
    scoperti (`type`, `created_from`/`created_to`, `updated_from`/`updated_to`, `user_id`/
    `creator_id` come filtro positivo, valori espliciti di `sort`).
- **Cleanup NON applicato in questo ciclo** (per scelta esplicita, vedi Follow-up): centralizzare la
  regola "cos'è un log di modifica vera" in un unico punto (es. scope su `StoryLog`), oggi
  duplicata concettualmente in `StoryController::LOG_IS_CHANGE_SQL` e in
  `SendWaitingStoryReminder.php:75` con criteri diversi (OR su chiavi specifiche vs conteggio di
  chiavi). Le due logiche coincidono oggi per coincidenza — nessun log esistente ha chiavi miste.

## Follow-up

- **Ticket oc:8537** (aperto durante Fase: challenge, poi aggiornato durante reverse-interaction):
  indici mancanti su `stories` (`status`, `user_id`, `creator_id`, `created_at`), `story_logs`
  (`story_id`, `user_id` — considerare un indice composto `(user_id, created_at)` per
  `changed_by`+`changed_from`/`changed_to` combinati, non solo colonne separate) e `taggables`
  (`tag_id`).
- **Task 3 (ordine canonico degli stati, `StoryStatus::sortOrder()`) resta bloccato** in attesa di
  conferma esplicita del CTO. `sort=status`/`-status` ricade silenziosamente sul default
  (`-created_at`) finché il metodo non esiste — comportamento verificato da test dedicato.
  Contraddizioni da portare alla decisione: la Kanban mostra `waiting` in sequenza (non terminale),
  `StoryMetricsCalculator::FORWARD_STATUSES` tratta `done` come successivo a `released`.
- **Centralizzare la regola "log vero"** (vedi Decisioni sopra) in un ciclo successivo, con test
  dedicati che coprano sia `StoryController` sia `SendWaitingStoryReminder`.
- Nessun'altra deviazione rilevante dal piano approvato.
