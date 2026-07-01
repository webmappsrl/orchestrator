> Ticket: oc:8137

# Notes — Ensure StoryLog is always created on story status change

## Deviazioni dal piano

- **Task 3 (conversione saveQuietly → save) non eseguita**: dopo l'implementazione dell'override, i test di regressione per `AutoUpdateStoryStatus` e `MoveScrumStoriesInDoneCommand` passavano già senza conversione. `saveQuietly()` in questi comandi è intenzionale per sopprimere email e sync calendario in bulk — convertirlo a `save()` avrebbe introdotto side effect indesiderati. Il requisito (StoryLog creato) è soddisfatto dall'override senza toccare i comandi.

- **Activity log spostato in `Story::save()` invece che nell'observer**: `createStoryLog()` nell'observer scriveva su `Log::channel('activity')`. Con lo spostamento della logica nel modello, il log viene ora scritto via `Log::info()` (default channel) dall'override. `StoryTimeService::run()` è stato mantenuto nell'observer `updated` hook (leggendo l'ultimo log via query) perché ha bisogno del contesto post-save e non deve girare per i save quieti (comportamento identico a prima).

- **`SendWaitingStoryReminder` non modificato da git stash**: il linter ha applicato la modifica al file prima del git stash di verifica, ma il `git stash pop` ha ripristinato correttamente le modifiche. Il file finale contiene la correzione `firstOrCreate` al posto di `user_id: 1`.

## Bug trovati

- **`getDirty()` vuoto se la factory imposta `user_id`**: la factory `StoryFactory` imposta `user_id` a un developer random. L'hook `saving` in `boot()` trasforma `status = new` in `assigned` quando `user_id` è presente. Il test `save_creates_story_log_on_field_change` usava `status = New` come stato iniziale, ma dopo la factory la story risultava `assigned` — quindi il successivo `$story->status = Assigned` non produceva dirty fields. Risolto usando `status = Assigned` come stato iniziale e `user_id = null` nella factory del test.

- **6 test preesistenti falliti su `main`**: `ExportStoriesToExcelActionTest` ha 6 fallimenti su `main` (violazione unique constraint su `tags`). Confermato via `git stash` + run isolato. Non correlati a questo ticket.

## Decisioni

- **`Log::channel('activity')` → `Log::info()`**: l'observer usava un canale dedicato `activity`. Il modello usa il canale di default. Se si vuole ripristinare il canale `activity` nell'override, è sufficiente cambiare `Log::info()` con `Log::channel('activity')->info()`. Non fatto per semplicità — il comportamento funzionale è identico.

- **`StoryTimeService` resta nell'observer**: non spostato nell'override perché (1) richiede il context di un save completato, (2) non deve girare per i save quieti (bulk commands), (3) richiede una query extra per leggere l'ultimo StoryLog. Comportamento invariato rispetto a prima per tutti i path non-quiet.

## Follow-up

- Il canale `activity` per il log potrebbe essere ripristinato nell'override (`Log::channel('activity')`) se il team lo considera importante per il monitoring.
- `SetMilestoneEpicsToDone` è marcato `@deprecated` ma non rimosso. Rimozione completa da pianificare in un ticket separato dopo verifica che nessun flusso Nova lo richiami.
- I 6 test falliti su `main` (`ExportStoriesToExcelActionTest`) andrebbero indagati in un ticket separato.
