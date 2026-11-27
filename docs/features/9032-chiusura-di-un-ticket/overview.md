> Ticket: oc:9032
> Stato: chiusa il 2026-11-27 · approvata il 2026-11-27

# Chiusura di un ticket da parte del cliente

## Cosa cambia

Un utente cliente può portare a `done` le proprie richieste con una Nova Action, sia su una
Story sola sia su più Story selezionate nell'index di «my stories» (`StoryShowedByCustomer`).
Alla chiusura il dev e il tester assegnati ricevono una notifica Nova.

## Perché

Oggi il cliente non ha modo di chiudere una richiesta che per lui è risolta o non serve più:
deve scriverlo nel ticket con `RespondToStoryRequest` e aspettare che qualcuno del team cambi lo
stato. La richiesta del ticket è che lo faccia da sé, anche su più ticket insieme.

## Punto di partenza

- Il cliente è un utente con ruolo `UserRole::Customer`; le sue Story sono quelle con
  `creator_id` uguale al suo id, impostato alla creazione in `Story::created`
  (`app/Models/Story.php`).
- La Resource che il cliente usa è `StoryShowedByCustomer`, il cui `indexQuery` filtra per
  `creator_id` ed esclude `done` e `rejected`; non ridefinisce `actions()`, quindi vale il ramo
  cliente di `Story::actions()` (`app/Nova/Story.php`), che oggi contiene solo
  `RespondToStoryRequest` (inline, `sole()`).
- `StoryToDoneStatusAction` esiste ed è riservata al team: mette `done` su ogni modello ricevuto,
  senza controllare stato di partenza né proprietario.
- `StoryPolicy::update` restituisce sempre `true`: il vincolo «solo le proprie Story» oggi esiste
  solo nell'`indexQuery`, non nella policy, e una Nova Action controlla solo il proprio `canRun`.
- L'hook `Story::updated` notifica il dev solo quando a cambiare lo stato è il tester: se lo
  stato lo cambia il cliente non parte nessuna notifica. Lo `StoryLog` del cambio di stato lo
  crea già l'override di `Story::save()`.
- Locale di default `it`, fallback `en`; i testi delle Action sono chiavi inglesi in
  `lang/en.json` e `lang/it.json`.

## Requisiti

- <a id="r1"></a>**R1** — Un cliente porta a `done` una o più Story di cui è creatore, da
  qualsiasi stato, selezionandole nell'index di «my stories» oppure dalla detail view della
  singola Story.
  - Verifica: [CloseOwnStoriesActionTest.php](../../../tests/Feature/CloseOwnStoriesActionTest.php) › `test_customer_closes_own_stories_from_any_status`
- <a id="r2"></a>**R2** — Una Story di cui il cliente non è creatore non cambia stato, anche se il
  suo id arriva nella richiesta dell'Action.
  - Verifica: [CloseOwnStoriesActionTest.php](../../../tests/Feature/CloseOwnStoriesActionTest.php) › `test_single_story_of_another_user_is_not_closed`
- <a id="r3"></a>**R3** — L'Action chiede solo una conferma: nessun campo nella modale. Il testo
  della conferma avvisa che le richieste vanno in archivio e che per riaprirne una bisogna
  contattare il team. Chi vuole lasciare un messaggio lo fa con `RespondToStoryRequest` sul
  singolo ticket.
  - Verifica: [app/Nova/Story.php](../../../app/Nova/Story.php) › `CloseOwnStoriesAction`
- <a id="r4"></a>**R4** — Per ogni Story chiusa dal cliente con l'Action parte una notifica Nova al
  dev assegnato (`user_id`) e al tester (`tester_id`): una sola se sono la stessa persona, nessuna
  a un ruolo non assegnato (una Story senza assegnatari non notifica nessuno). Nessuna mail.
  - Verifica: [CloseOwnStoriesActionTest.php](../../../tests/Feature/CloseOwnStoriesActionTest.php) › `test_developer_and_tester_are_notified`
- <a id="r5"></a>**R5** — Cambiano stato solo le Story selezionate: i figli (`parent_id`) di una
  Story chiusa restano nel loro stato.
  - Verifica: [CloseOwnStoriesActionTest.php](../../../tests/Feature/CloseOwnStoriesActionTest.php) › `test_children_are_not_closed`
- <a id="r6"></a>**R6** — L'Action è visibile solo agli utenti cliente; il team continua a usare
  `StoryToDoneStatusAction`.
  - Verifica: [CloseOwnStoriesActionTest.php](../../../tests/Feature/CloseOwnStoriesActionTest.php) › `test_action_is_available_to_customers_only`
- <a id="r7"></a>**R7** — Nome dell'Action, testo della modale, messaggi di esito e notifica sono
  tradotti in `it` e `en`.
  - Verifica: [CloseOwnStoriesActionTest.php](../../../tests/Feature/CloseOwnStoriesActionTest.php) › `test_message_is_singular_for_one_story_in_italian`

## Rischi

- **Lavoro in corso chiuso dal cliente**: con la chiusura da qualsiasi stato, un dev può
  ritrovarsi chiuso un ticket in `progress`; la notifica Nova ([R4](#r4)) è la mitigazione.
  Le Story senza assegnatario — sul DB locale tutte in `backlog` — non notificano nessuno.
  Accettato dal dev; riportato in
  [docs/knowledge/story-status-e-notifiche.md](../../knowledge/story-status-e-notifiche.md).
- **Nessun annullamento lato cliente**: una Story chiusa esce da «my stories» e nell'archivio il
  cliente non ha Action; la mitigazione è l'avviso nella conferma ([R3](#r3)), la riapertura la fa
  il team. Accettato dal dev; riportato nella stessa pagina di conoscenza.
- **Metriche del team**: una Story assegnata e chiusa dal cliente conta come chiusa per il dev
  assegnato nelle metriche di team performance. Accettato dal dev; riportato nella stessa pagina
  di conoscenza.

## Out of scope

- Limitare la chiusura a certi stati di partenza.
- Chiusura a cascata dei figli.
- Un campo messaggio nella modale.
- Restringere `StoryPolicy::update` per i clienti: è un problema più ampio di questo ticket.
- Un'Action per il cliente che riapra una Story chiusa.
- La notifica quando il cliente chiude una Story `released` dal form di modifica, strada che
  esiste già: la notifica di [R4](#r4) parte solo dall'Action.

## Moduli toccati

Tutto nel repo principale, nessun submodule.

- `app/Nova/Actions/CloseOwnStoriesAction.php` — nuova Action per il cliente
- `app/Nova/Story.php` — registrazione nel ramo cliente di `actions()`
- `lang/en.json`, `lang/it.json` — testi
- `tests/Feature/CloseOwnStoriesActionTest.php` — test dell'Action
- `docs/features/9032-chiusura-di-un-ticket/` — overview, piano e note
