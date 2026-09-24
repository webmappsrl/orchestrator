> Ticket: oc:8625

# API task: rendere modificabile la data di scadenza

## Cosa cambia

`PATCH /api/tasks/{task}` accetta un terzo campo, `due_date`, oltre a `status` e `notes`. La
nuova scadenza viene salvata e restituita nella risposta. Oggi il campo viene scartato in
silenzio e la risposta è comunque 200.

## Perché

Il team commerciale sposta i follow-up dei preventivi ogni giorno: un cliente chiede di
risentirsi più avanti, una call viene anticipata. Via API l'unico modo è chiudere il task e
ricrearlo, con due conseguenze: lo storico della trattativa si riempie di task chiusi che non
corrispondono ad attività svolte, e le note vanno ricopiate a mano. Chi automatizza (la skill
Cowork) oggi invia `due_date`, riceve 200 e non si accorge che la data non è cambiata.

Richiesta interna del team commerciale, assegnata allo scrum del 23/09/2026 con consegna in
giornata.

## Requisiti

- [ ] `due_date` è accettata in PATCH con la stessa regola del POST (`['sometimes', 'date']`):
      vanno bene sia la sola data sia data e orario, e anche le date passate.
- [ ] Una `due_date` non valida (stringa non interpretabile come data, oppure `null`) riceve 422
      e la data salvata non cambia.
- [ ] Può modificare `due_date` chiunque abbia un ruolo abilitato, con la stessa regola di
      `notes` (`TaskPolicy::update()`), anche se non ha creato il task. La policy non cambia.
- [ ] Un payload che contiene `status` insieme a `due_date`, inviato da chi non ha creato il
      task, riceve 403 e non salva niente, nemmeno la data: la verifica di `updateStatus`
      resta prima di qualsiasi scrittura.
- [ ] `status` e `due_date` vengono salvati con un solo `save()`; `notes` resta separato perché
      `appendNote()` salva da sé.
- [ ] La risposta non cambia forma: `due_date` continua a uscire in ISO 8601 da `formatTask()`.
- [ ] Gli altri campi non ammessi continuano a essere ignorati, come oggi.
- [ ] Il docblock di `TaskController::update()` (letto da Scramble) e
      `docs/knowledge/task-e-quote.md` descrivono il nuovo campo e la sua regola di
      autorizzazione. La "tabella degli endpoint" citata dal ticket non esiste nei `docs/`:
      l'unica descrizione dei campi ammessi in PATCH è quel docblock ("limited to two
      fields"), da cui Scramble genera la documentazione.
- [ ] Quattro test nuovi in `tests/Feature/Api/TaskApiTest.php`, ognuno dei quali rilegge il
      valore dal DB con `fresh()`:
      1. `due_date` valida → 200, data salvata e restituita;
      2. `due_date` non valida o `null` → 422, data invariata;
      3. `due_date` da chi non ha creato il task → 200, data salvata;
      4. `status` + `due_date` da chi non ha creato il task → 403, niente salvato.

## Rischi

- **Contratto consumato fuori dal repo.** La skill Cowork usa questa API. La modifica aggiunge
  solo un campo accettato e non cambia la forma della risposta, quindi le richieste che oggi
  riescono continuano a riuscire.
- **Spostare la data cambia la classificazione del task nelle viste Nova** (scaduto / oggi /
  futuro, `app/Nova/Task.php:177-191`). È il comportamento atteso.

## Out of scope

- La modifica di altri campi del task via API (`title`, `quote_id`, …).
- Il rifiuto con 422 dei campi non ammessi: resta il comportamento attuale, comune a tutte le
  API del repo. Se serve, va deciso per tutte con un ticket a parte.
- Vincoli sulle date passate o sul formato con orario obbligatorio.
- L'interfaccia Nova, dove la scadenza è già modificabile.

## Moduli toccati

Tutto nel repo principale, nessun submodule coinvolto.

- `app/Http/Requests/Api/TaskApiRequest.php` — regola `due_date` nel ramo PATCH di `rules()`
- `app/Http/Controllers/Api/TaskController.php` — `update()`: assegnazione di `due_date`, unico
  `save()` con `status`, docblock
- `tests/Feature/Api/TaskApiTest.php` — quattro test nuovi
- `docs/knowledge/task-e-quote.md` — autorizzazione per campo della PATCH
