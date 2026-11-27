> Ticket: oc:9032
> Stato: seguito, con 3 divergenze annotate in notes.md

# Chiusura di un ticket da parte del cliente — piano di implementazione

> **Per chi esegue:** usare `superpowers:executing-plans` o `superpowers:subagent-driven-development`,
> task per task. Gli step sono checkbox (`- [ ]`). **Nessun commit durante l'esecuzione**: le righe
> «Commit» sono istruzioni testuali per il dev, il primo commit lo fa lui.

**Obiettivo:** una Nova Action con cui il cliente porta a `done` le proprie Story, anche su più
Story selezionate, notificando dev e tester.

**Architettura:** una nuova Action `CloseOwnStoriesAction` registrata solo nel ramo cliente di
`App\Nova\Story::actions()` (ereditato da `StoryShowedByCustomer`). Il controllo sul proprietario
(`creator_id`) sta in `handle()`, non in `canRun`, così l'Action può contare le Story saltate.
Lo stato si cambia con `save()`: lo `StoryLog` lo crea l'override di `Story::save()`.

**Stack:** Laravel 12, Laravel Nova, PHPUnit nel container `php81_orchestrator` su `orchestrator_test`.

**Spec:** [overview.md](overview.md)

## Vincoli globali

- Identificatori in inglese; commenti e documentazione in italiano.
- Nessuno `StoryLog` scritto a mano (regola del `CLAUDE.md`).
- Nessuna lettura di `story_story`: la relazione padre-figlio è solo `parent_id`.
- Testi con chiave inglese in `lang/en.json` e traduzione in `lang/it.json`; locale di default `it`.
- Test: `docker exec php81_orchestrator php artisan test --filter=<Classe>`, mai con
  `DB_DATABASE=orchestrator`. I test usano `DatabaseTransactions`.
- Commit: `feat(oc:9032): …`.

## Punti da guardare in review

1. Story già `done` inviata all'Action: non va contata come chiusa e non genera notifiche (test in Task 1 e Task 2).
2. Story di un altro cliente passata per id nella POST: resta invariata, il messaggio la conta fra le saltate ([R2](overview.md#r2), Task 1).
3. Dev e tester coincidenti: una sola notifica (Task 2).
4. Story senza `user_id` né `tester_id`: nessuna notifica, nessun errore (Task 2).
5. Utente non cliente sulla stessa Resource: l'Action non compare (Task 1).

---

### Task 1: Action di chiusura per il cliente

> ⚠️ L'implementazione ha deviato da questo task: [notes.md](notes.md#task-1-action-di-chiusura-per-il-cliente)

Realizza [R1](overview.md#r1), [R2](overview.md#r2), [R3](overview.md#r3), [R5](overview.md#r5),
[R6](overview.md#r6) e la parte di [R7](overview.md#r7) relativa a nome, conferma ed esito.

**File:**
- Crea: `app/Nova/Actions/CloseOwnStoriesAction.php`
- Modifica: `app/Nova/Story.php` — ramo cliente di `actions()` (oggi contiene solo `RespondToStoryRequest`)
- Modifica: `lang/en.json`, `lang/it.json`
- Test: `tests/Feature/CloseOwnStoriesActionTest.php`

**Interfacce prodotte:**
- `App\Nova\Actions\CloseOwnStoriesAction extends Laravel\Nova\Actions\Action`
- `uriKey(): string` → `'close-own-stories'`
- `handle(ActionFields $fields, Collection $models): ActionResponse`
- `fields(NovaRequest $request): array` → `[]`
- metodo protetto `notifyAssignees(Story $story): void` — vuoto in questo task, riempito nel Task 2

**Testi** (chiave = testo inglese; traduzione italiana):

| Chiave (`en.json`) | `it.json` |
|---|---|
| `Mark as done` | `Segna come completata` |
| `The selected requests will be marked as done and moved to the archive. To reopen one, you will need to contact the team.` | `Le richieste selezionate verranno segnate come completate e spostate nell'archivio. Per riaprirne una dovrai contattare il team.` |
| `:closed request(s) marked as done.` | `:closed richieste segnate come completate.` |
| `:closed request(s) marked as done, :skipped skipped because they are not yours.` | `:closed richieste segnate come completate, :skipped saltate perché non sono tue.` |

`Confirm` e `Cancel` esistono già nei json.

- [ ] **Step 1: scrivere i test che falliscono**

Il test chiama l'endpoint di Nova, non `handle()`, come `ToggleTaskCompletedActionTest`:
`POST /nova-api/story-showed-by-customers/action?action=close-own-stories` con `resources`
(elenco di id). Cliente: `User::factory()->create(['roles' => [UserRole::Customer]])`; le Story con
`Story::factory()->create([...])`, poi `creator_id` assegnato e salvato con `saveQuietly()`:
l'hook `Story::created` sovrascrive `creator_id` con l'utente autenticato.

- `test_customer_closes_own_stories_from_any_status`: tre Story del cliente in `new`, `progress`,
  `released` → risposta OK, tutte e tre `status = done`; il messaggio contiene `3`.
- `test_story_of_another_user_is_skipped`: due Story del cliente + una di un altro cliente (in
  `progress`) → le due del cliente `done`, l'altra resta `progress`; il messaggio è la versione
  «saltate» con `:closed = 2`, `:skipped = 1`.
- `test_already_done_story_is_not_counted`: una Story del cliente già `done` + una in `todo` →
  il messaggio conta `1` chiusa e `0` saltate (versione senza «saltate»).
- `test_children_are_not_closed`: Story padre del cliente con un figlio (`parent_id`) in `todo`
  creato da un dev → padre `done`, figlio ancora `todo`.
- `test_action_is_available_to_customers_only`: le action restituite da
  `GET /nova-api/story-showed-by-customers/actions` contengono `close-own-stories` per il cliente
  e non per un utente `UserRole::Developer`.
- `test_story_log_records_the_change`: dopo la chiusura esiste un solo `StoryLog` della Story con
  `changes->status = done` e `user_id` del cliente (nessun doppione).

- [ ] **Step 2: eseguire i test e vederli fallire**

`docker exec php81_orchestrator php artisan test --filter=CloseOwnStoriesActionTest`
Atteso: FAIL (Action inesistente, 404 o action non trovata).

- [ ] **Step 3: implementare `CloseOwnStoriesAction`**

- `handle()`: per ogni modello, se `creator_id !== auth()->id()` incrementa `$skipped` e passa
  oltre; se è già `done` passa oltre senza contarlo; altrimenti `status = StoryStatus::Done->value`,
  `save()`, `$closed++`, `$this->notifyAssignees($story)`. Restituisce `Action::message()` con il
  testo «saltate» solo se `$skipped > 0`.
- `name()` → `__('Mark as done')`.
- Nessun `canRun`: il controllo è in `handle()` perché con `canRun` Nova scarta i record prima di
  `handle()` (`vendor/laravel/nova/src/Actions/ActionModelCollection.php`) e il conteggio dei
  saltati non sarebbe possibile.
- Nessun `sole()` né `onlyInline()`: deve funzionare su selezione multipla dall'index e dalla
  detail view.

- [ ] **Step 4: registrare l'Action nel ramo cliente di `Story::actions()`**

Dopo `RespondToStoryRequest`:
`(new actions\CloseOwnStoriesAction())->confirmText(__('The selected requests will be …'))->confirmButtonText(__('Confirm'))->cancelButtonText(__('Cancel'))`.
Il ramo del team non cambia.

- [ ] **Step 5: aggiungere i testi in `lang/en.json` e `lang/it.json`** (tabella sopra).

- [ ] **Step 6: eseguire i test e vederli passare**

`docker exec php81_orchestrator php artisan test --filter=CloseOwnStoriesActionTest` → PASS.

- [ ] **Step 7: commit** (istruzione per il dev)

```bash
git add app/Nova/Actions/CloseOwnStoriesAction.php app/Nova/Story.php lang/en.json lang/it.json tests/Feature/CloseOwnStoriesActionTest.php
git commit -m "feat(oc:9032): action del cliente per chiudere le proprie story"
```

---

### Task 2: notifica a dev e tester

> ⚠️ L'implementazione ha deviato da questo task: [notes.md](notes.md#task-2-notifica-a-dev-e-tester)

Realizza [R4](overview.md#r4) e la parte di [R7](overview.md#r7) relativa alla notifica.

**File:**
- Modifica: `app/Nova/Actions/CloseOwnStoriesAction.php` — `notifyAssignees()`
- Modifica: `lang/en.json`, `lang/it.json`
- Test: `tests/Feature/CloseOwnStoriesActionTest.php`

**Interfacce consumate:** `notifyAssignees(Story $story): void` dal Task 1.

**Testi:**

| Chiave (`en.json`) | `it.json` |
|---|---|
| `The story :id has been closed by :name` | `La story :id è stata chiusa da :name` |
| `View story` | `Vedi la story` (chiave assente in entrambi i json: va aggiunta) |

- [ ] **Step 1: scrivere i test che falliscono** (con `Notification::fake()`)

- `test_developer_and_tester_are_notified`: Story del cliente con `user_id` = dev A e
  `tester_id` = dev B → `Notification::assertSentTo($devA, NovaNotification::class)` e lo stesso
  per B, una volta ciascuno.
- `test_single_notification_when_developer_is_tester`: `user_id` = `tester_id` = dev A → una
  sola notifica ad A (`assertSentToTimes($devA, NovaNotification::class, 1)`).
- `test_no_notification_without_assignees`: Story in `backlog` senza `user_id` né `tester_id` →
  `Notification::assertNothingSent()`, risposta OK.
- `test_skipped_and_already_done_stories_do_not_notify`: Story di un altro utente assegnata a dev A
  + Story del cliente già `done` assegnata a dev A → nessuna notifica.

- [ ] **Step 2: eseguire i test e vederli fallire** — `--filter=CloseOwnStoriesActionTest`, FAIL sulle notifiche.

- [ ] **Step 3: implementare `notifyAssignees()`**

Destinatari: `collect([$story->developer, $story->tester])->filter()->unique('id')`. Per ciascuno
`->notify(NovaNotification::make()->type('info')->message(__('The story :id has been closed by :name', [...]))->action(__('View story'), url('/nova/resources/stories/' . $story->id))->icon('check-circle'))`,
sullo stesso modello delle notifiche in `Story::updated`.

- [ ] **Step 4: aggiungere i testi** e **Step 5: eseguire i test** → PASS.

- [ ] **Step 6: eseguire la suite completa**

`docker exec php81_orchestrator php artisan test` → nessun test nuovo in errore
(`StoryChildFieldTest` è flaky già noto, vedi `docs/howto/eseguire-i-test.md`).

- [ ] **Step 7: commit** (istruzione per il dev)

```bash
git add app/Nova/Actions/CloseOwnStoriesAction.php lang/en.json lang/it.json tests/Feature/CloseOwnStoriesActionTest.php
git commit -m "feat(oc:9032): notifica nova a dev e tester alla chiusura del cliente"
```

---

### Task 3: verifica a mano

> ⚠️ L'implementazione ha deviato da questo task: [notes.md](notes.md#task-3-verifica-a-mano)

Verifica [R1](overview.md#r1), [R3](overview.md#r3), [R6](overview.md#r6) nell'interfaccia.

- [ ] Con un utente cliente in locale: «my stories», selezionare due Story, lanciare «Segna come
  completata», controllare il testo della modale, confermare; le Story spariscono dall'index e
  compaiono nell'archivio.
- [ ] Dalla detail view di una singola Story del cliente: l'Action c'è e funziona.
- [ ] Con un utente developer: l'Action non compare; `StoryToDoneStatusAction` resta com'era.
- [ ] Il dev assegnato vede la notifica nella campanella di Nova.
