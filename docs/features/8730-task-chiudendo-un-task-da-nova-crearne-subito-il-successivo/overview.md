> Ticket: oc:8730

# Task: chiudendo un task da Nova, crearne subito il successivo

## Cosa cambia

L'action Nova `ToggleTaskCompleted`, che oggi chiude un task aperto e riapre un task chiuso senza
mostrare campi, viene modificata: quando il task su cui la si lancia è **aperto**, il popup mostra
quattro campi per il task successivo — **Titolo**, **Scadenza**, **Assegnatario**, **Note**.

- **Titolo compilato:** il task si chiude e sulla stessa trattativa nasce il task successivo con
  titolo, scadenza, assegnatario e note indicati. Scadenza e assegnatario in questo caso sono
  obbligatori: senza, il popup resta aperto con l'errore sul campo e non si chiude nulla.
- **Titolo vuoto:** il task si chiude e basta, come oggi (trattativa persa o vinta, nessun seguito).
  Scadenza, assegnatario o note senza titolo vengono ignorati.
- **Task chiuso:** l'action lo riapre come oggi, e il popup non mostra campi.

Il campo **Assegnatario** si apre **già compilato** con l'assegnatario di default di oc:8722 — l'owner
della trattativa; se la trattativa non ha owner, o l'owner non ha un ruolo che accede ai task,
l'utente che sta chiudendo — e si può cambiare prima di confermare. La regola è già scritta in un
punto solo, e oc:8722 l'ha pensata anche per questo popup:

```php
// app/Models/Task.php:56-72 (su develop, da oc:8722)
 * Assegnatario di default (oc:8722, review del 14/10/2026): l'owner
 * della Quote; se manca, o non ha un ruolo che accede ai task, l'utente
 * che agisce. Unico punto della regola: lo usano l'hook di creazione,
 * il campo Nova precompilato, la riassegnazione alla cancellazione di
 * un utente e il popup di chiusura di oc:8730.
 */
public static function defaultAssigneeId(?Quote $quote, ?User $actor): ?int
{
    $owner = $quote?->user;

    if ($owner !== null && $owner->canAccessTasks()) {
        return $owner->id;
    }

    return $actor?->id;
}
```

Il campo si comporta come quello del form «Crea Task» di oc:8722: si sceglie solo fra gli utenti che
accedono ai task.

```php
// app/Nova/Task.php:100-105 (su develop, da oc:8722)
private function assigneeField(): BelongsTo
{
    return BelongsTo::make(__('Assignee'), 'assignee', User::class)
        ->searchable()
        ->rules('required')
        ->relatableQueryUsing(fn (NovaRequest $request, $query) => $query->withTaskAccess())
```

L'action lavora su **un task per volta**, sia per chiudere (con o senza task successivo) sia per
riaprire: compare sulla riga, nel dettaglio del task e fra le azioni della lista solo quando è
selezionato esattamente un task (`vendor/laravel/nova/src/Actions/ActionCollection.php:26-29`).
Oggi invece gira su tutti i task selezionati:

```php
// app/Nova/Actions/ToggleTaskCompleted.php:28-36 (su develop)
public function handle(ActionFields $fields, Collection $models)
{
    foreach ($models as $task) {
        $task->status = $task->status === Task::STATUS_COMPLETED
            ? Task::STATUS_TODO
            : Task::STATUS_COMPLETED;
        $task->save();
    }
}
```

Il nome dell'action segue lo stato del task: «Segna come completato» su un task aperto, «Riapri» su
un task chiuso. Nova passa all'action il task su cui viene lanciata, sia dalla riga della lista sia
dal dettaglio, e nome e campi sono calcolati dopo:

```php
// vendor/laravel/nova/src/ResolvesActions.php:89-92 (dettaglio; uguale a :60-63 per la riga)
->withAuthorizedToRun($request, $this->resource)
->each(function ($action) {
    $action->resource = $this->resource;
})

// vendor/laravel/nova/src/Actions/Action.php:947-949
'name' => $this->name(),
...
'fields' => FieldCollection::make($this->fields($request))
```

Esempio: sulla trattativa «Sito Rossi», di cui è owner Mario, il task «Richiamare il cliente per la
demo» è aperto. Si lancia «Segna come completato»: il campo Assegnatario mostra già Mario. Si scrive
«Inviare offerta aggiornata» con scadenza 14/10, si lascia Mario e si conferma: il primo task
risulta completato e nella tab «Task» della trattativa compare il nuovo, assegnato a Mario.

Restano come oggi: chi può eseguire l'action, la creazione normale dei task dalla tab «Task» della
trattativa, l'API. Chi può eseguire l'action lo decide la policy di oc:8722, che questo ticket non
tocca:

```php
// app/Nova/Actions/ToggleTaskCompleted.php:20-26 (su develop) — chi può eseguirla, invariato
/**
 * Stessa regola dell'API: TaskPolicy::updateStatus() (oc:8722).
 */
public function authorizedToRun(Request $request, $model)
{
    return $request->user()?->can('updateStatus', $model) ?? false;
}
```

## Perché

Il commerciale, quando chiude un task, nella maggior parte dei casi deve programmare il passo
successivo della trattativa. Oggi da Nova servono due passaggi: chiudere il task, poi tornare nella
trattativa e creare a mano il nuovo dalla relazione `Tasks`. Con la skill Claude il seguito viene già
chiesto alla chiusura; il ticket chiede la stessa cosa da Nova, nello stesso popup e senza una
domanda separata.

### Fonti dalle call

- **Ticket oc:8730, `customer_request`**, dalla call del 30/09/2026: «Alla chiusura di un task
  proporre la creazione di quello successivo»; «sto dicendo di modificare la action chiudi task»;
  «se compili i dati sotto ti crei il task e se non lo fai io quella comunque la chiudo sempre».
- **Giuseppe Bonfanti, scrum del 07/10/2026 (08:32), circa al minuto 40**
  ([trascrizione](https://docs.google.com/document/d/1o0cxW4oq08GrgLbTQOHsoP87tmJYN4lVxaeTRt2g7ko)):
  - «devi andare a modificare la action di chiusura task prendendo i dati già di un nuovo task. Se li
    lascia vuoti non crea il task» → si modifica l'action esistente; campi vuoti = solo chiusura.
  - «devi controllare se lo sta per chiudere, gli devi chiedere se ne vuole creare un altro» → i campi
    compaiono solo in chiusura, non in riapertura.
  - «il preventivo da scegliere, già ce l'hai che lo prendi dal vecchio task. Ok, quindi solo un
    titolo, una scadenza e delle note» → stessa trattativa del task chiuso; campi Titolo, Scadenza, Note.
- **Giuseppe Bonfanti, scrum del 12/10/2026 (08:29), circa al minuto 15**
  ([trascrizione](https://docs.google.com/document/d/1ZWBPxytbWOYpuYQt5KQ7OlpzujfoZkF7JUlknBWRvrg)):
  «nell'8730 fare un'overview concordata è, diciamo, obbligatoria».

### Decisioni della review dell'overview

Review della PR [#265](https://github.com/webmappsrl/orchestrator/pull/265) sul commit `10f98f0`
(14/10/2026), riportata anche nel ticket, sezione «Risposte del reviewer alle domande aperte — da
implementare»:

- **Più task selezionati insieme:** «Togliere la chiusura multipla: l'action funziona su **un task
  per volta**, sia per chiudere (con o senza task successivo) sia per riaprire.»
- **Pagina non aggiornata:** «Resta come oggi: è un caso ipotetico, nessun messaggio di blocco.» Se il
  task è stato chiuso altrove mentre la lista era aperta, l'action trova il task già chiuso e lo
  riapre, ignorando il titolo, come farebbe oggi.
- **Assegnatario nel popup:** «Il popup «Segna come completato» mostra anche il campo
  **Assegnatario** del task successivo, **già compilato con il default** di oc:8722 (owner della
  trattativa; se manca, l'utente che chiude) e modificabile.» Lo stesso campo precompilato nel form
  «Crea Task» della trattativa, chiesto nella stessa review, è già stato fatto da oc:8722
  (`app/Nova/Task.php:93-120` su `develop`).
- **Dipendenza:** «La colonna `assignee_id` e il default nascono lì, quindi oc:8730 si implementa dopo
  oc:8722.» oc:8722 è unito in `develop` (commit `77c2bc1`, PR #266).

### Comportamenti decisi a partire da ticket e codice

- **Il titolo decide se nasce il task successivo.** Il ticket, nella description: `handle()` «chiude
  il task e, solo se il titolo è compilato, crea il nuovo Task sulla stessa Quote».
- **Con il titolo, la scadenza è obbligatoria.** `due_date` non ha default e non accetta null, e il
  form Nova dei task la chiede già. Una scadenza predefinita non viene né dal ticket né dal codice.

  ```php
  // database/migrations/2026_08_19_113137_create_tasks_table.php:16
  $table->dateTime('due_date');

  // app/Nova/Task.php:50-52 (su develop)
  DateTime::make(__('Due date'), 'due_date')
      ->sortable()
      ->rules('required'),
  ```

- **Con il titolo, anche l'assegnatario è obbligatorio**, come nel form «Crea Task» di oc:8722
  (`->rules('required')`, sopra). Arriva già compilato, quindi lo si trova vuoto solo se qualcuno lo
  svuota a mano.
- **Il task successivo si crea anche su una trattativa chiusa**, come fa oggi Nova dalla tab «Task».
  Che Nova non applichi il blocco dell'API sulle trattative «Chiuso Vinto»/«Chiuso Perso» è una
  scelta già documentata in `docs/knowledge/task-e-quote.md` e nella policy:

  ```php
  // app/Policies/TaskPolicy.php:68-76 (su develop)
  public function create(User $user, ?Quote $quote = null)
  {
      if ($quote === null) {
          return true;
      }

      return $quote->status !== QuoteStatus::Closed_Won->value
          && $quote->status !== QuoteStatus::Closed_Lost->value;
  }
  ```

- **Il nuovo task ha come creatore chi chiude**, senza codice aggiuntivo. L'assegnatario invece è
  quello scelto nel popup: l'hook ricalcola il default solo se `assignee_id` è vuoto.

  ```php
  // app/Models/Task.php:135-143 (su develop)
  static::creating(function (Task $task) {
      if ($task->creator_id === null && auth()->check()) {
          $task->creator_id = auth()->id();
      }

      if ($task->assignee_id === null) {
          $task->assignee_id = self::defaultAssigneeId($task->quote, auth()->user());
      }
  });
  ```

## Requisiti

- [ ] L'action resta una sola (`ToggleTaskCompleted`): chiude un task aperto e riapre un task chiuso.
- [ ] L'action lavora su un solo task: è registrata con `sole()`, quindi Nova la mostra sulla riga e
      nel dettaglio ma non fra le azioni dei task selezionati. `sole()` agisce solo sull'interfaccia
      (`vendor/laravel/nova/src/Actions/Action.php:490-496`; l'endpoint di esecuzione non lo
      controlla), quindi anche `handle()` rifiuta più di un task con un messaggio di errore, senza
      modificare nulla.
- [ ] Su un task aperto il nome è «Segna come completato» e il popup mostra i campi Titolo, Scadenza,
      Assegnatario, Note; su un task chiuso il nome è «Riapri» e il popup non ha campi.
- [ ] Il campo Assegnatario si apre compilato con `Task::defaultAssigneeId($task->quote, utente che
      chiude)`, è modificabile e propone solo gli utenti che accedono ai task (`withTaskAccess()`).
- [ ] Titolo compilato → il task viene chiuso e viene creato un Task sulla stessa Quote con titolo,
      scadenza, assegnatario e note dati; il creatore del nuovo task è l'utente che esegue l'action.
- [ ] Titolo compilato e Scadenza o Assegnatario vuoti → errore di validazione sul campo, nessuna
      modifica.
- [ ] Titolo vuoto → il task viene chiuso e non si crea nulla, anche se Scadenza, Assegnatario o Note
      sono compilati.
- [ ] La riapertura funziona come oggi (`todo`, `completed_at` azzerato dall'hook `saving` esistente).
- [ ] Chi può eseguire l'action non cambia: `TaskPolicy::updateStatus()` di oc:8722.
- [ ] Nome dell'action, etichette dei campi e messaggi tradotti in tutte le lingue del repo
      (`lang/it.json`, `lang/en.json`; lingua di default `it`).
- [ ] L'action ha un `uriKey` fisso, che non dipende dal nome mostrato.
- [ ] `fields()` toglie i campi solo quando l'action ha un task e quel task è chiuso; senza task
      (momento dell'esecuzione) li restituisce sempre. Il default dell'Assegnatario si calcola solo
      quando il task c'è: all'esecuzione arriva il valore inviato dal popup.
- [ ] Chiusura del task e creazione del successivo avvengono in una `DB::transaction`: o entrambe o
      nessuna.
- [ ] Il titolo ha `max:255`, come nel form Nova dei task (`app/Nova/Task.php:40-42` su `develop`).
- [ ] Test:
  - chiusura con task successivo (nuovo task sulla stessa Quote, creatore = chi chiude, assegnatario
    = quello scelto nel popup, anche diverso dal default);
  - campo Assegnatario precompilato: con l'owner della trattativa; con una trattativa senza owner,
    con l'utente che chiude;
  - chiusura senza titolo (nessun task nuovo, anche con scadenza, assegnatario o note compilati);
  - titolo senza scadenza e titolo senza assegnatario (errore, task ancora aperto);
  - riapertura (nessun campo, nessun task nuovo);
  - più task inviati insieme all'endpoint (errore, nessun task modificato);
  - **esecuzione come la fa Nova**: una richiesta `POST /nova-api/tasks/action?action=<uriKey>`
    con l'utente autenticato, il task selezionato, titolo, scadenza e assegnatario, che verifica che
    il task si chiuda e il successivo nasca. È il test che intercetta i due errori descritti nei
    Rischi: i test che chiamano `handle()` direttamente, come fanno oggi quelli del repo
    (`tests/Feature/ExportStoriesToExcelActionTest.php:86-89`), non li vedrebbero.

## Rischi

- **Quando si preme «Esegui», Nova non sa più su quale task si è.** All'apertura del popup Nova passa
  il task all'action (vedi «Cosa cambia»), ma all'esecuzione la ricostruisce su un modello vuoto:

  ```php
  // vendor/laravel/nova/src/Http/Requests/ActionRequest.php:31-41 — ritrova l'action dal suo uriKey
  return $this->availableActions()
      ...->first(
          fn ($action) => $action->uriKey() == $this->query('action')
      ) ?: abort($this->actionExists() ? 403 : 404);

  // vendor/laravel/nova/src/Http/Requests/InteractsWithResources.php:181-186 — modello vuoto
  return $resourceClass::newModel();

  // vendor/laravel/nova/src/Actions/Action.php:981 — uriKey ricavato dal nome
  return $this->uriKey ?? Str::slug($this->name(), '-', null);
  ```

  Conseguenze, se non gestite:
  - con il nome che cambia fra «Segna come completato» e «Riapri», all'esecuzione Nova cerca un
    uriKey diverso da quello mostrato e risponde 404: una delle due operazioni sarebbe sempre rotta.
    Gestito con un `uriKey` fisso;
  - i campi vengono ricalcolati senza task (`ActionRequest.php:198-201`): se `fields()` li desse solo
    «quando il task è aperto», all'esecuzione sparirebbero, il task si chiuderebbe e il successivo
    non nascerebbe, senza errore. Gestito restituendo i campi quando il task non c'è. Per lo stesso
    motivo il default dell'Assegnatario non si può calcolare all'esecuzione: vale il valore inviato.

  Va comunque verificato a mano in Nova, dalla riga della lista, dal dettaglio del task e dalla tab
  «Task» della trattativa, che un task aperto mostri «Segna come completato» con i campi e
  l'Assegnatario già compilato, e uno chiuso «Riapri» senza campi. In `handle()` i campi valgono
  solo se il task si sta chiudendo, quindi riaprendo non nasce mai un task successivo.
- **Le note sono Tiptap.** Nella risorsa Task il campo note è `Marshmallow\Tiptap\Tiptap`
  (`app/Nova/Task.php:161-163` su `develop`); nell'action va usato lo stesso campo, già usato in
  un'altra action (`app/Nova/Actions/RespondToStoryRequest.php`), così le note del nuovo task hanno
  lo stesso formato di quelle create dalla tab «Task».

## Out of scope

- Chi può chiudere e riaprire i task: lo ha deciso oc:8722 (`TaskPolicy::updateStatus()`), qui resta
  com'è.
- API: nessuna modifica. La skill Claude chiude e crea con due chiamate (`PATCH` e `POST /api/tasks`).
- La creazione normale dei task dalla tab «Task» della trattativa, compreso il suo campo Assegnatario
  precompilato, già fatto da oc:8722.
- La casella «Completed» nel form di modifica del task (`app/Nova/Task.php:149-160` su `develop`):
  chiude il task senza proporre il successivo. Il ticket chiede di modificare l'action («sto dicendo
  di modificare la action chiudi task»), non il form.

## Moduli toccati

Tutto nel repo principale `orchestrator`, nessun submodule. Dipende da oc:8722, già in `develop`.

- `app/Nova/Actions/ToggleTaskCompleted.php` — nome dinamico, `uriKey` fisso, un solo task,
  campi del task successivo con l'Assegnatario precompilato, chiusura e creazione in transazione in
  `handle()`
- `app/Nova/Task.php` — registrazione dell'action con `sole()`; `notesField()` diventa
  `public static`, riusato dall'action (vedi `notes.md`)
- `lang/it.json`, `lang/en.json` — nomi dell'action, etichette, messaggi
- `tests/Feature/ToggleTaskCompletedActionTest.php` — nuovo, test dell'action
- `docs/knowledge/task-e-quote.md` — aggiornamento a fine lavoro
