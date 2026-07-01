> Ticket: oc:8137

# Ensure StoryLog is always created on story status change

## Cosa cambia

La logica di creazione del `StoryLog` viene spostata dall'observer (`StoryObserver::createStoryLog()`) a un override del metodo `save()` nel modello `Story`. In questo modo il log viene creato automaticamente per ogni cambio di campo, indipendentemente da come viene chiamato il save (`save()`, `saveQuietly()`, contesto CLI o HTTP).

I comandi che usavano `saveQuietly()` + `StoryLog::create()` manuale vengono semplificati: si usa `save()` normale (dove possibile) o si rimuove il log manuale (dove `saveQuietly()` è intenzionale per sopprimere side effect). `SetMilestoneEpicsToDone` viene deprecato. Il `user_id: 1` hardcodato in `SendWaitingStoryReminder` viene sostituito con `orchestrator_artisan@webmapp.it`.

## Perché

`StoryLog` è la fonte primaria per i report di produttività e il tracciamento delle attività. Se alcuni cambi di status non producono una riga di log, i report sono inaffidabili. Il problema era strutturale: `saveQuietly()` sopprime gli observer, e `->update()` bulk bypassa Eloquent completamente. L'override di `save()` risolve alla radice senza richiedere interventi nei singoli punti di chiamata futuri.

## Requisiti

- [ ] `Story::save()` overridato: cattura i dirty fields prima di `parent::save()`, crea il `StoryLog` dopo, con fallback su `orchestrator_artisan@webmapp.it` se `Auth::user()` è null
- [ ] `StoryObserver::createStoryLog()` rimosso dall'hook `updated` (la responsabilità passa al modello)
- [ ] `AutoUpdateStoryStatus`: rimosso `saveQuietly()`, sostituito con `save()` normale (nessun log manuale necessario)
- [ ] `MoveScrumStoriesInDoneCommand`: rimosso `saveQuietly()`, sostituito con `save()` normale
- [ ] `SlackRevertProgressCommand`: rimosso `StoryLog::create()` manuale (l'override lo gestisce); `saveQuietly()` resta per sopprimere email e sync calendario
- [ ] `SendWaitingStoryReminder`: `user_id: 1` hardcodato sostituito con `orchestrator_artisan@webmapp.it` via `firstOrCreate`
- [ ] `SetMilestoneEpicsToDone` marcato come `@deprecated` con nota esplicativa
- [ ] Test sull'override `Story::save()`: verifica che il log venga creato con `save()` e con `saveQuietly()`
- [ ] Test di regressione per tutti i comandi: verifica che producano almeno una riga `StoryLog` dopo l'esecuzione

## Rischi

- **Doppia creazione log durante la transizione**: se l'observer non viene aggiornato in sincronia con l'override, i save normali produrranno due log. Mitigazione: rimuovere `createStoryLog()` dall'observer nello stesso commit in cui si aggiunge l'override.
- **`getDirty()` vuoto dopo `parent::save()`**: i dirty fields vengono sincronizzati da Eloquent durante il save. Bisogna catturarli con `$dirty = $this->getDirty()` **prima** di chiamare `parent::save()`.
- **`wasRecentlyCreated` non disponibile prima del save**: il check per evitare di loggare i `created` va fatto con un flag locale (`$isNew = !$this->exists`) prima del save, non con `wasRecentlyCreated` (disponibile solo dopo).
- **`SlackRevertProgressCommand` side effect**: `saveQuietly()` resta intenzionale per sopprimere email e sync calendario — l'override crea comunque il log perché `withoutEvents()` non blocca codice PHP custom nel metodo overridato. Comportamento corretto e voluto.

## Out of scope

- Log per cambi di status delle **Epic** (nessun `EpicLog` esiste, fuori scope)
- `TagController::attachStory/detachStory`: log relazionale (attach/detach tag), non un cambio di campo del modello — resta manuale, è corretto
- `LogStory` middleware: log di visualizzazione (`watch`), non un cambio di status — resta manuale, è corretto
- Conversione di `->update()` bulk su query builder in altri contesti non citati nel ticket

## Moduli toccati

- `app/Models/Story.php` — override `save()`
- `app/Observers/StoryObserver.php` — rimozione `createStoryLog()` dall'hook `updated`
- `app/Console/Commands/AutoUpdateStoryStatus.php` — `saveQuietly()` → `save()`
- `app/Console/Commands/MoveScrumStoriesInDoneCommand.php` — `saveQuietly()` → `save()`
- `app/Console/Commands/SlackRevertProgressCommand.php` — rimozione `StoryLog::create()` manuale
- `app/Console/Commands/SendWaitingStoryReminder.php` — fix `user_id: 1` hardcodato
- `app/Nova/Actions/SetMilestoneEpicsToDone.php` — deprecazione
- `tests/Feature/StoryLogOverrideTest.php` — nuovo test override `save()`/`saveQuietly()`
- `tests/Feature/StoryLogCommandsTest.php` — test di regressione comandi
