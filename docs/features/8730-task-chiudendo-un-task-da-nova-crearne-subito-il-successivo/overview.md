> Ticket: oc:8730

# Task: chiudendo un task da Nova, crearne subito il successivo

## Cosa cambia

L'action Nova `ToggleTaskCompleted`, che oggi chiude un task aperto e riapre un task chiuso senza
mostrare campi, viene modificata: quando il task su cui la si lancia è **aperto**, il popup mostra tre
campi facoltativi per il task successivo — **Titolo**, **Scadenza**, **Note**.

- **Titolo compilato:** il task si chiude e sulla stessa trattativa nasce il task successivo con
  titolo, scadenza e note indicati. La scadenza in questo caso è obbligatoria: senza, il popup resta
  aperto con l'errore sul campo e non si chiude nulla.
- **Titolo vuoto:** il task si chiude e basta, come oggi (trattativa persa o vinta, nessun seguito).
  Scadenza o note scritte senza titolo vengono ignorate.
- **Task chiuso:** l'action lo riapre come oggi, e il popup non mostra campi.

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

Esempio: sulla trattativa «Sito Rossi» il task «Richiamare il cliente per la demo» è aperto. Si
lancia «Segna come completato», si scrive «Inviare offerta aggiornata» con scadenza 14/10 e si
conferma: il primo task risulta completato e nella tab «Task» della trattativa compare il nuovo.

Restano come oggi: chi può eseguire l'action (solo il creatore del task), la creazione normale dei
task dalla tab «Task» della trattativa, l'API.

```php
// app/Nova/Actions/ToggleTaskCompleted.php:20-23 — chi può eseguirla, invariato
public function authorizedToRun(Request $request, $model)
{
    return $model->creator_id !== null && $model->creator_id === $request->user()?->id;
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

### Comportamenti decisi a partire da ticket e codice

- **Il titolo decide se nasce il task successivo.** Il ticket, nella description: `handle()` «chiude
  il task e, solo se il titolo è compilato, crea il nuovo Task sulla stessa Quote».
- **Con il titolo, la scadenza è obbligatoria.** `due_date` non ha default e non accetta null, e il
  form Nova dei task la chiede già. Una scadenza predefinita non viene né dal ticket né dal codice.

  ```php
  // database/migrations/2026_08_19_113137_create_tasks_table.php:16
  $table->dateTime('due_date');

  // app/Nova/Task.php:73-75
  Date::make(__('Due date'), 'due_date')
      ->sortable()
      ->rules('required'),
  ```

- **Il task successivo si crea anche su una trattativa chiusa**, come fa oggi Nova dalla tab «Task».
  Che Nova non applichi il blocco dell'API sulle trattative «Chiuso Vinto»/«Chiuso Perso» è una
  scelta già documentata in `docs/knowledge/task-e-quote.md` e nella policy:

  ```php
  // app/Policies/TaskPolicy.php:60-68
  public function create(User $user, ?Quote $quote = null)
  {
      if ($quote === null) {
          return true;
      }

      return $quote->status !== QuoteStatus::Closed_Won->value
          && $quote->status !== QuoteStatus::Closed_Lost->value;
  }
  ```

- **Il nuovo task ha come creatore chi chiude**, senza codice aggiuntivo:

  ```php
  // app/Models/Task.php:107-111
  static::creating(function (Task $task) {
      if ($task->creator_id === null && auth()->check()) {
          $task->creator_id = auth()->id();
  ```

## Requisiti

- [ ] L'action resta una sola (`ToggleTaskCompleted`): chiude un task aperto e riapre un task chiuso.
- [ ] Su un task aperto il nome è «Segna come completato» e il popup mostra i campi facoltativi
      Titolo, Scadenza, Note; su un task chiuso il nome è «Riapri» e il popup non ha campi.
- [ ] Titolo compilato → il task viene chiuso e viene creato un Task sulla stessa Quote con titolo,
      scadenza e note dati; il creatore del nuovo task è l'utente che esegue l'action.
- [ ] Titolo compilato e Scadenza vuota → errore di validazione sul campo Scadenza, nessuna modifica.
- [ ] Titolo vuoto → il task viene chiuso e non si crea nulla, anche se Scadenza o Note sono compilate.
- [ ] La riapertura funziona come oggi (`todo`, `completed_at` azzerato dall'hook `saving` esistente).
- [ ] L'action resta eseguibile solo dal creatore del task (regola di oggi).
- [ ] Nome dell'action, etichette dei campi e messaggi tradotti in tutte le lingue del repo
      (`lang/it.json`, `lang/en.json`; lingua di default `it`).
- [ ] L'action ha un `uriKey` fisso, che non dipende dal nome mostrato.
- [ ] `fields()` toglie i campi solo quando l'action ha un task e quel task è chiuso; senza task
      (momento dell'esecuzione) li restituisce sempre.
- [ ] Chiusura del task e creazione del successivo avvengono in una `DB::transaction`: o entrambe o
      nessuna.
- [ ] Il titolo ha `max:255`, come nel form Nova dei task (`app/Nova/Task.php:69-71`).
- [ ] Test:
  - chiusura con task successivo (nuovo task sulla stessa Quote, creatore = chi chiude);
  - chiusura senza titolo (nessun task nuovo, anche con scadenza o note compilate);
  - titolo senza scadenza (errore, task ancora aperto);
  - riapertura (nessun campo, nessun task nuovo);
  - **esecuzione come la fa Nova**: una richiesta `POST /nova-api/tasks/action?action=<uriKey>`
    con l'utente autenticato, il task selezionato, titolo e scadenza, che verifica che il task si
    chiuda e il successivo nasca. È il test che intercetta i due errori descritti nei Rischi: i test
    che chiamano `handle()` direttamente, come fanno oggi quelli del repo
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
    non nascerebbe, senza errore. Gestito restituendo i campi quando il task non c'è.

  Va comunque verificato a mano in Nova, dalla riga della lista, dal dettaglio del task e dalla tab
  «Task» della trattativa, che un task aperto mostri «Segna come completato» con i campi e uno chiuso
  «Riapri» senza campi. In `handle()` i campi valgono solo per i task che si stanno chiudendo, quindi
  riaprendo non nasce mai un task successivo.
- **Le note sono Tiptap.** Nella risorsa Task il campo note è `Marshmallow\Tiptap\Tiptap`
  (`app/Nova/Task.php:128-130`); nell'action va usato lo stesso campo, già usato in un'altra action
  (`app/Nova/Actions/RespondToStoryRequest.php`), così le note del nuovo task hanno lo stesso formato
  di quelle create dalla tab «Task».

## Domande aperte per il reviewer

Punti emersi dal codice e non dal ticket: non vengono affrontati in questo ticket, si implementa il
comportamento più semplice indicato sotto. Se vanno affrontati, è meglio un ticket a parte.

1. **Più task selezionati insieme.** Il ticket e lo scrum del 07/10 parlano sempre di un task
   («chiudiamo un task», «lo prendi dal vecchio task»); il codice di oggi permette di lanciare
   l'action su più task. Comportamento che si implementa: ogni task selezionato si chiude o si
   riapre come oggi, e con il titolo compilato nasce un task successivo per **ciascun** task chiuso
   (es. tre task su tre trattative → tre «Follow-up» con la stessa scadenza). Con più task
   selezionati Nova passa all'action solo l'ultimo (`vendor/laravel/nova/src/ResolvesActions.php:66-71`),
   quindi nome e campi seguono lo stato di quello. Con stati misti (task aperti e chiusi
   selezionati insieme) l'action chiude gli aperti e riapre i chiusi, come fa oggi, qualunque nome
   mostri. Alternativa: limitare l'action a un task per volta (`sole()`, disponibile in Nova 5.7.6).
2. **Pagina non aggiornata.** Se il task viene chiuso altrove (un'altra scheda, la skill Claude)
   mentre la lista è aperta, dalla lista si vede ancora «Segna come completato»: compilando il titolo
   e premendo «Esegui», l'action trova il task già chiuso e lo **riapre**, ignorando il titolo, come
   farebbe oggi l'interruttore. Comportamento che si implementa: questo. Alternativa: se il titolo è
   compilato e il task risulta già chiuso, non fare nulla e mostrare un messaggio.

## Out of scope

- Chi può chiudere e riaprire i task: resta il solo creatore. L'apertura a ogni utente commerciale
  è di oc:8722, insieme al campo assegnatario del task (che quindi non compare nel popup).
- API: nessuna modifica. La skill Claude chiude e crea con due chiamate (`PATCH` e `POST /api/tasks`).
- La creazione normale dei task dalla tab «Task» della trattativa.
- La casella «Completed» nel form di modifica del task (`app/Nova/Task.php:116-126`): chiude il task
  senza proporre il successivo. Il ticket chiede di modificare l'action («sto dicendo di modificare
  la action chiudi task»), non il form.
- Da considerare in oc:8722: quando chiunque potrà chiudere i task, chi chiude il task di un collega
  diventerà creatore del task successivo (`creator_id` impostato da `Task::booted()`), e con la
  regola di oggi sarà l'unico a poterlo chiudere.

## Moduli toccati

Tutto nel repo principale `orchestrator`, nessun submodule.

- `app/Nova/Actions/ToggleTaskCompleted.php` — nome dinamico, `uriKey` fisso, campi del task
  successivo, chiusura e creazione in transazione in `handle()`
- `lang/it.json`, `lang/en.json` — nomi dell'action, etichette, messaggi
- `tests/Feature/TaskTest.php` (o un test nuovo dedicato) — test dell'action
- `docs/knowledge/task-e-quote.md` — aggiornamento a fine lavoro
