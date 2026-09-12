# Story: cambi di stato, log e notifiche

Cosa succede quando una Story cambia stato: quale log viene scritto, quali mail partono, quali
automatismi la spostano.

## Stato attuale

### Il log nasce dall'override di `Story::save()` (oc:8137)
- **`Story::save()` è overridato**: cattura i campi sporchi, chiama `parent::save()` e crea il
  `StoryLog` dentro una transazione. Vale per ogni cambio di campo e per **qualsiasi** modo di
  salvare — `save()`, `saveQuietly()`, CLI o HTTP — perché `withoutEvents()` non blocca codice
  PHP custom nel metodo overridato. `StoryObserver::createStoryLog()` è stato rimosso dall'hook
  `updated`: la responsabilità è del modello, e tenerli entrambi produrrebbe due log per save.
- **Non scrivere `StoryLog` a mano dopo un `save()`/`saveQuietly()` su Story**: sarebbe un
  doppione. I comandi che lo facevano sono stati semplificati.
- Quando `Auth::user()` è `null` (comandi, job, seed) l'autore del log è
  `orchestrator_artisan@webmapp.it`, risolto in `Story.php:55-62` con la catena
  `Auth::user() ?? User::where('email', ...)->first() ?? User::create([...])` — l'utente può non
  esistere nel DB di test. `firstOrCreate` per lo stesso utente compare invece in
  `SendWaitingStoryReminder.php:129`.
- I `created` non vengono loggati: il check si fa con un flag locale `$isNew = !$this->exists`
  **prima** del save, non con `wasRecentlyCreated`, disponibile solo dopo.
- Restano manuali e sono corretti così: il log relazionale di attach/detach tag in
  `TagController` (non è un cambio di campo del modello) e il log di visualizzazione del
  middleware `LogStory`. Le Epic non hanno alcun log (`EpicLog` non esiste).

### Stato `pending_release` fra Tested e Released (oc:8426)
- Significa "concluso per noi, non ancora rilasciato": copre sia l'attesa di validazione cliente
  su staging sia l'attesa di una release aggregata.
- Visibile **solo** nella colonna Kanban dedicata e in "I miei Ticket" del cliente; escluso da
  `developer-stories`, `assigned-to-me-stories`, `customer-stories` e dal Google Calendar.
  Transizione sempre manuale, nessuna migrazione dati, nessun automatismo — il valore della
  feature dipende interamente da un'azione manuale e non esiste indicatore per accorgersi se non
  viene compiuta.
- **L'uscita dal calendario e il cambio silenzioso non sono costati una riga**:
  `SyncStoriesWithGoogleCalendar::getTestedTickets()` filtra su `status = 'tested'` esatto e le
  notifiche in `Story::booted()` sono cablate stato per stato, senza default "qualsiasi cambio
  manda mail". **Corretto ma implicito**: allargare quelle whitelist reintrodurrebbe il ticket in
  calendario o farebbe partire mail senza che nulla lo segnali. La rete è parziale:
  `PendingReleaseStatusTest` copre l'uscita dal calendario con **un solo** test
  (`test_pending_release_non_finisce_nel_calendario_del_developer`, riga 224); **nessuno** dei suoi
  test verifica l'assenza di mail, che resta senza copertura.
- **`statusFilterOverrides` per `pending_release` è `['user_id','creator_id']`** (come `released`),
  non `tester_id` (come `tested`): a test concluso la card interessa chi ha sviluppato e chi ha
  aperto. Senza override le card create da customer non comparirebbero a nessuno.
- **Conta in `FORWARD_STATUSES` ma non in `$closedInQuarter`**: un ritorno a
  `todo`/`progress`/`assigned` è rilavorazione (la più costosa: lavoro dichiarato pronto e
  bocciato), ma contarlo come chiuso nel trimestre gonfierebbe la produttività con lavoro non
  rilasciato.
- **Conta come chiuso nel SAL dei tag, `tested` no.** Il SAL è una metrica interna
  (`MenuSection('DEV')`, nessun numero mostrato al cliente), quindi il criterio è "il dato è utile
  a noi?".
- **`StoryShowedByCustomer` (`/resources/story-showed-by-customers`, "I miei Ticket") non va
  toccata**: è la vista **del cliente**, da non confondere con `CustomerStory`
  (`/resources/customer-stories`, "Ticket"), che è la vista **del team**. Il suo `whereNotIn`
  esclude solo `done`/`rejected` e resta invariato volutamente.
- **Nessuna modifica a `nova-components/kanban-card/`**: `KanbanController::updateStatus()` scrive
  il valore ricevuto senza whitelist, quindi il drag&drop funziona appena la colonna esiste. Il
  `'tested'` hardcodato in `KanbanController` riguarda solo la colonna virtuale
  `tested_by_others` e non va toccato.
- **Il rollback non è simmetrico**: il revert del codice è banale (nessuna migrazione, colonna
  testuale, `Rule::enum` permissiva), ma le righe con `status = 'pending_release'` resterebbero
  orfane e `label()` senza ramo `default` solleverebbe `UnhandledMatchError`. Serve anche
  `UPDATE stories SET status = 'tested' WHERE status = 'pending_release'`; i ticket già usciti dal
  Google Calendar non vi rientrano da soli.

### Mail sui cambi di stato (oc:8040, oc:7977, oc:8091)
- **Alla creazione di un ticket tutti i dev ricevono una mail**, sempre la stessa classe:
  `Story.php:247` invia `CustomerNewStoryCreated` nel loop sui developer, **senza alcun ramo sul
  ruolo del creator**. Non esiste una `DevNewStoryCreated`: `app/Mail/` non la contiene. Il dev
  creatore non è escluso dai destinatari.
- **Eccezione: i ticket di tipo Scrum non mandano nessuna mail alla creazione.** La guardia è un
  `return` posizionato dopo `$story->save()` (che assegna `creator_id` e `tester_id`) e prima del
  loop developer, così i metadati vengono comunque popolati. Il confronto è
  `$story->type === StoryType::Scrum->value`: `type` è una stringa, il modello non ha `$casts` per
  quel campo — se in futuro si aggiunge il cast, la guardia va aggiornata.
- **Su `status → released` il creator riceve sempre la mail**, indipendentemente dal ruolo, da chi
  agisce e dall'auto-assign del tester. Non ci sono guard di deduplicazione: per `released` nessun
  altro path notifica tester o assignee, e quelle guard bloccavano i developer-creator (l'hook
  `created` fa auto-assign `tester_id = creator_id`).

### Auto-revert dei ticket in progress (oc:8136)
- Comando schedulato ogni 20 minuti fra le 12 e le 18 che verifica la presenza Slack dei dev con
  ticket in progress e, se offline, riporta indietro il ticket con `saveQuietly()` — intenzionale,
  per sopprimere mail e sync calendario. Il `StoryLog` viene comunque scritto dall'override di
  `Story::save()`.
- **`everyTwentyMinutes()` non esiste fra gli helper di frequenza dello scheduler**: `Kernel.php:31`
  usa `->cron('*/20 12-18 * * *')`.
- **Uno Slack User ID inizia con `U`**: gli ID che iniziano con `D` sono canali DM. Si copia da
  profilo Slack → ⋯ → "Copia ID membro".
- **`SLACK_BOT_TOKEN` richiede lo scope `users:read`** nella sezione "Ambiti del token bot" (non
  "token utente") su api.slack.com/apps.

## Come ci siamo arrivati

- **`saveQuietly()` + `StoryLog::create()` manuale in `SlackRevertProgressCommand`**: era il
  meccanismo di oc:8136, superato da oc:8137 — il log manuale è stato rimosso e oggi nel comando
  non compare alcun `StoryLog`. Stessa sorte per il `user_id: 1` hardcodato in
  `SendWaitingStoryReminder` (ora `orchestrator_artisan@webmapp.it`). **`AutoUpdateStoryStatus` e
  `MoveScrumStoriesInDoneCommand` usano invece ancora `saveQuietly()`**
  (`AutoUpdateStoryStatus.php:34`, `MoveScrumStoriesInDoneCommand.php:25`): il log viene comunque
  scritto dall'override di `Story::save()`, mentre mail e sync calendario restano soppresse.
  `SetMilestoneEpicsToDone` è `@deprecated`.
- **Due stati distinti al posto di `pending_release`** (`customer_review` per la validazione
  cliente, `ready_to_release` per la release aggregata, oc:8426): scartati. Sui dati di produzione
  i due scenari pesavano esattamente uguale (produzione, 2026-09-02: dei 16 ticket allora in
  `tested`, 8 con creator customer e 8 interno — rilevamento storico non riproducibile, in locale
  oggi `tested` vale 3), quindi lo stato unico li rende indistinguibili pur avendo owner, canale di sollecito e
  tempi diversi — accettato consapevolmente. `pending_release` è l'unico nome accurato in entrambi
  gli scenari, a differenza di `customer_review` (copre solo il primo) e `staging` (descrive un
  ambiente, non l'attesa).
- **Contare anche `tested` come chiuso nel SAL** (oc:8426): consapevolmente lasciato fuori benché
  sia la causa di SAL fuorvianti su tag RDO reali (0% con 5 ticket su 6 collaudati, 7% con 8 su
  14). È una riga nello stesso array, ma cambia il SAL di **tutti** i tag storici
  (`wm-core` 88%→91%, `webmapp-app` 79%→81%): rinviato a un ticket dedicato.
- **Escludere `pending_release` dal force-todo sulla risposta del cliente** (oc:8426): valutato e
  scartato, perché nella pratica la risposta del cliente è quasi sempre una richiesta che riapre
  davvero il ticket. Il campo "Answer to ticket" riporta a `todo` quando chi risponde non è
  l'assegnatario, sovrascrivendo qualsiasi altro valore nello stesso submit. Limite noto: le
  risposte di puro assenso sporcano la metrica di rilavorazione, senza perdere il ticket.
