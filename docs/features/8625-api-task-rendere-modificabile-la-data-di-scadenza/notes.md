> Ticket: oc:8625

# Notes — API task: rendere modificabile la data di scadenza

## Deviazioni dal piano

Nessuna deviazione rilevante: i cinque task sono stati eseguiti come descritti.

## Bug trovati

Nessuno.

## Decisioni

- **Tag:** nessun tag aggiunto. I candidati trovati per "orchestrator", "backend" e "api" non
  riguardavano questo lavoro; la creazione dei tag `api` e `task` è stata proposta e rifiutata
  dal dev.
- **Challenge:** nessun rilievo bloccante. Fuso orario perso con offset diversi da Europe/Rome,
  formati con barre letti all'americana, vista globale Nova che azzera l'orario, spostamento su
  task completati o quote chiuse sono difetti che esistono già (anche nel POST o in Nova) e
  restano fuori scope: il ticket non ne parla.
- **Unico `save()`:** in `TaskController::update()` il salvataggio di `status` e `due_date` è
  condizionato a `$task->isDirty()`, non a una lista di chiavi della richiesta. Una prima
  versione usava `$request->hasAny(['status', 'due_date'])`; la review l'ha segnalata perché chi
  aggiunge un campo dovrebbe ricordarsi di aggiornare anche quella lista, altrimenti il campo
  risponderebbe 200 senza salvare. Il comportamento è identico: Eloquent non scrive un model
  non modificato.
- **Controllo `updateStatus` separato dall'assegnazione:** i due `if ($request->has('status'))`
  restano distinti perché l'autorizzazione deve precedere qualsiasi scrittura, anche di
  `due_date`.
- **`null` riceve 422:** la regola PATCH è `sometimes|date` senza `nullable`; la scadenza si può
  spostare, non togliere (la colonna è obbligatoria).
- **Test 1 senza fuso orario:** usa `'2026-10-15 10:30:00'` per non dipendere dalla
  coincidenza fra `+02:00` e l'ora legale di Roma.
- **Test 4 già verde prima della modifica**, come previsto dal piano: protegge dallo spostamento
  del controllo `updateStatus` dopo il salvataggio.

## Verifica

`TaskApiTest` + `TaskPolicyTest` nel container su `orchestrator_test`: 35 test verdi.

## Follow-up

Nessuno richiesto dal ticket.
