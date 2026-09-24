> Ticket: oc:8625

# Notes — API task: rendere modificabile la data di scadenza

## Deviazioni dal piano

Nessuna deviazione rilevante: i cinque task sono stati eseguiti come descritti.

## Bug trovati

- **Fuso orario perso (trovato in review):** la regola `date` accetta ISO 8601 con offset o `Z`,
  ma il cast `datetime` di `Task` scrive l'ora così com'è, senza convertirla:
  `2026-09-30T22:00:00Z` finiva nel DB come le 22:00 di Roma del 30/09 invece della mezzanotte
  del 1/10. Il difetto c'era già nel POST. Corretto con `TaskApiRequest::dueDate()`, che
  converte nel fuso dell'applicazione, usato sia dal POST sia dalla PATCH.

## Decisioni

- **Tag:** nessun tag aggiunto. I candidati trovati per "orchestrator", "backend" e "api" non
  riguardavano questo lavoro; la creazione dei tag `api` e `task` è stata proposta e rifiutata
  dal dev.
- **Challenge:** nessun rilievo bloccante. Formati con barre letti all'americana, vista globale
  Nova che azzera l'orario, spostamento su task completati o quote chiuse sono difetti che
  esistono già (anche nel POST o in Nova) e restano fuori scope: il ticket non ne parla. Il fuso
  orario perso, inizialmente messo fra questi, è stato poi corretto in review (vedi "Bug trovati").
- **Unico `save()`:** in `TaskController::update()` tutti i campi, compresa la nota
  (`appendNote($note, false)`), vengono assegnati al model e scritti con un solo `save()`
  finale. Una prima versione salvava `status`/`due_date` e poi `appendNote()` salvava una
  seconda volta. Resta una duplicazione nota: i campi modificabili sono elencati sia in
  `TaskApiRequest::rules()` sia negli `if` del controller, quindi chi aggiunge un campo deve
  toccare entrambi, altrimenti il campo risponde 200 senza salvare.
- **Controllo `updateStatus` prima di ogni scrittura:** sta nello stesso `if` dell'assegnazione
  di `status`; basta che preceda il `save()`, che arriva dopo tutte le assegnazioni.
- **`null` riceve 422:** la regola PATCH è `sometimes|date` senza `nullable`; la scadenza si può
  spostare, non togliere (la colonna è obbligatoria).
- **Test 1 senza fuso orario:** usa `'2026-10-15 10:30:00'` per non dipendere dalla
  coincidenza fra `+02:00` e l'ora legale di Roma.
- **Test 4 già verde prima della modifica**, come previsto dal piano: protegge dallo spostamento
  del controllo `updateStatus` dopo il salvataggio.

## Verifica

`TaskApiTest` + `TaskPolicyTest` nel container su `orchestrator_test`: 38 test verdi (dopo la
review: +2 test sul fuso orario, POST e PATCH, e +1 sul payload `{due_date, notes}`).

## Follow-up

Nessuno richiesto dal ticket.
