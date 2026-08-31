> Ticket: oc:8426

# Nuovo stato "Pending Release" tra Tested e Released

## Cosa cambia

Viene introdotto un nuovo stato `pending_release` nell'enum `StoryStatus`, in posizione
logica tra `Tested` e `Released`, che rappresenta **"concluso per noi, non ancora
rilasciato"**.

Un ticket in `pending_release`:

- **è visibile in due soli posti**: la colonna dedicata del Kanban (per il team) e
  `/resources/story-showed-by-customers` — "I miei Ticket" (per il cliente)
- **sparisce da tutte le liste interne del team**: `developer-stories`,
  `assigned-to-me-stories` ("my work") e `customer-stories` ("Ticket")
- **sparisce dal calendario Google dei developer** — non consuma più uno slot di
  capacità pianificata
- **non genera nessuna notifica email** (cambio di stato silenzioso)
- **conta come chiuso nel SAL** dei tag/RDO
- **conta come stato avanzato nelle metriche**: un ritorno a `todo`/`progress`/`assigned`
  viene contato come rilavorazione

### Dove si vede, in sintesi

| Vista | Ticket in `pending_release` |
|---|---|
| Colonna Kanban dedicata (team) | **visibile** — è il punto di controllo |
| "I miei Ticket" (cliente) | **visibile** |
| `developer-stories` ("Ticket", team) | escluso |
| `assigned-to-me-stories` ("my work") | escluso |
| `customer-stories` ("Ticket", team) | escluso |
| Calendario Google del developer | escluso |

Lo spostamento in `pending_release` è sempre una **decisione manuale del developer** a
feature conclusa. Nessun automatismo, nessuna transizione automatica da `tested`.

## Perché

Dall'introduzione del concetto di RDO, la board e il calendario dei developer si sono
riempiti di ticket bloccati in `tested`: il lavoro è finito e verificato, ma il ticket non
è ancora rilasciato. Le cause sono due, entrambe fuori dal controllo del developer:

1. **il cliente deve visionarlo in ambiente di staging e confermare che va bene**
2. **il ticket è concluso ma attende una release più ampia** che aggrega più ticket

In entrambi i casi il developer non deve più agire, ma la board e il calendario continuano
a contarli come lavoro aperto.

I dati di produzione (dump del 30/08/2026) confermano il sintomo:

| Stato | Ticket |
|---|---|
| `tested` | **16** |
| `assigned` | 11 |
| `testing` | 6 |
| `todo` | 2 |
| `released` | 1 |
| `waiting` | 1 |

`tested` è **lo stato attivo più popolato della board**, e per definizione contiene lavoro
che non richiede più il developer. Dei 16 ticket, **8 hanno un creator customer** (scenario
validazione) e **8 un creator interno** (scenario release aggregata): i due scenari pesano
esattamente uguale, nessuno dei due è marginale.

**11 dei 16 ticket sono fermi da oltre 25 giorni**, il più vecchio (`oc:8272`) da 41.

## Requisiti

- [ ] Nuovo case `PendingRelease = 'pending_release'` in `App\Enums\StoryStatus`, inserito
      **dopo `Tested`** (l'ordine dei `cases()` determina l'ordine nelle `options()` di
      `StoryStatusFilter` e di `fieldTrait::getOptions()`)
- [ ] `StoryStatus::label()` → nuovo case che ritorna `__('Pending Release')`
- [ ] `StoryStatus::color()` → colore **fuori dalla famiglia verde** (proposto `#14B8A6`,
      teal): `tested` è `#34D399` e `released` è `#10B981`, già molto vicini tra loro — un
      terzo verde renderebbe le tre colonne indistinguibili a colpo d'occhio
- [ ] **Due** chiavi di traduzione in **entrambi** `lang/it.json` e `lang/en.json`
      (locale di default del repo: `it`, fallback `en`):
      - `"Pending Release"` → usata da `StoryStatus::label()` (Kanban e filtro)
      - `"Pending_release"` → usata da `fieldTrait::getOptions()` (riga 662) e da
        `displayUsing()` (riga 335), che costruiscono l'etichetta con
        `__(ucfirst($status->value))` e quindi cercano **quella** chiave
      Con un commento nei file di lingua che spieghi perché sono due, altrimenti al prossimo
      refactor la seconda sembra un typo da rimuovere. **Senza la seconda chiave, nel Select
      di edit e nel badge di detail si legge letteralmente `Pending_release`.**
- [ ] Colonna Kanban in `app/Nova/Dashboards/Kanban.php`, tra la colonna virtuale
      `tested_by_others` e `Released`
- [ ] `statusFilterOverrides` per `pending_release` → `['user_id', 'creator_id']` (come
      `released`, **non** `tester_id` come `tested`): a test concluso la card interessa chi
      ha sviluppato e chi ha aperto il ticket. Senza override esplicito filtrerebbe sul
      default `user_id` e le card create da customer non comparirebbero a nessuno
- [ ] `App\Nova\CustomerStory::indexQuery()` → aggiungere `pending_release` all'array
      `$whereNotIn` (righe 31-35, che già esclude `done`/`backlog`/`rejected`). Copre
      automaticamente anche le metric-card di `cards()`, che riusano la stessa `indexQuery()`
- [ ] `App\Nova\DeveloperStory::indexQuery()` (riga 36, oggi `where('status','!=',done)`) →
      escludere anche `pending_release`
- [ ] `App\Nova\AssignedToMeStory::indexQuery()` (riga 21, oggi
      `whereNotIn('status',[new,done])`) → escludere anche `pending_release`
- [ ] `App\Models\Tag::salClosedStoryStatusValues()` (righe 93-100) → aggiungere
      `StoryStatus::PendingRelease->value`. Il SAL è una metrica **interna** (`Tag` e
      `TagGroup` vivono nella `MenuSection('DEV')`, visibile solo a Admin/Manager/Developer):
      quando il developer dichiara un ticket concluso e in attesa di rilascio, il lavoro
      sull'RDO è erogato e il SAL deve rifletterlo. `tested` resta **fuori** (decisione
      esplicita, vedi Out of scope)
- [ ] `StoryMetricsCalculator::FORWARD_STATUSES` (riga 18) → aggiungere `'pending_release'`,
      così un ritorno a `todo`/`progress`/`assigned` viene contato come reopen
- [ ] `app/Traits/fieldTrait.php` → aggiungere `pending_release` a `loadingWhen()`
      (righe 322-328): non essendo in nessuna lista apparirebbe come *completato* nel badge
      Nova, mentre è un'attesa
- [ ] Test — coprire almeno:
      - esclusione da `CustomerStory`, `DeveloperStory`, `AssignedToMeStory`
      - **presenza** in `StoryShowedByCustomer` (il cliente deve continuare a vederli)
      - esclusione dal calendario (`getTestedTickets()` non li restituisce)
      - conteggio del reopen da `pending_release`
      - `pending_release` conta come chiuso in `salTicketCounts()`
      - **test di completezza dell'enum**: iterare su `StoryStatus::cases()` e verificare che
        ogni case abbia `label()` e `color()`. `label()` è un `match` **senza `default`**
        (righe 55-70, a differenza di `color()` e `collapse()`): un case dimenticato solleva
        `UnhandledMatchError`, cioè un 500 sul Kanban — la home operativa del team — e su
        ogni index. Il test protegge anche gli stati futuri

## Rischi

*(sezione da completare dopo la Fase: challenge)*

Rischi identificati in fase di analisi e confermati dalla Challenge:

- **Nessun presidio nel tempo.** Cambio silenzioso, fuori da tutte le liste interne, fuori
  dal calendario: l'unico punto in cui il team vede questi ticket è **la colonna Kanban**.
  È un presidio reale — il Kanban è la vista principale del team, non un angolo remoto — ma
  è *passivo*: nessuna notifica, nessuna scadenza, nessun reminder. Con ticket che oggi
  restano fermi fino a 41 giorni (`oc:8272`), il rischio è che la colonna cresca e nessuno la
  presidi. Mitigazione possibile (fuori dallo scope di questo ciclo): un reminder schedulato
  sul modello di `SendWaitingStoryReminder`, che presidia esattamente lo stesso problema per
  `waiting`.
- **Il valore della feature dipende da un'azione manuale.** Nessun automatismo sposta i
  ticket in `pending_release`: se il developer non lo fa, i 16 ticket restano in `tested` e
  la board non si svuota — costo pieno, beneficio zero. Non è previsto nessun indicatore per
  accorgersene.
- **Una risposta del cliente riporta il ticket a `todo`.** Il campo "Answer to ticket"
  (`fieldTrait.php:449-461`) imposta `forceTodoOnAnswerToTicket` quando chi risponde non è
  l'assegnatario, e `StoryObserver::saving()` (righe 102-112) forza `status = todo`
  sovrascrivendo qualsiasi altro valore nello stesso submit. **Comportamento voluto e non
  modificato:** nella pratica la risposta del cliente è quasi sempre una richiesta che
  riapre davvero il ticket, quindi il ritorno a `todo` e il reopen conteggiato sono corretti.
  Residuo accettato: sulle risposte di puro assenso ("ok, ben fatto") il ticket torna
  comunque a `todo` e viene contato come rilavorazione pur non essendolo — sporca la metrica,
  non perde il ticket (il developer lo rivede e lo manda a `released`).
- **Rollback non simmetrico.** Il revert del codice è banale (nessuna migrazione, colonna
  `status` testuale, `Rule::enum` permissiva), ma **le righe con `status = 'pending_release'`
  restano a DB** e diventano orfane: `label()` senza `default` solleverebbe
  `UnhandledMatchError` dove il valore viene risolto in enum. Un rollback completo richiede
  quindi anche `UPDATE stories SET status = 'tested' WHERE status = 'pending_release'`.
  Attenzione: farlo via modello riscatena eventi (calendario, StoryLog, mail), farlo via SQL
  grezzo lascia lo storico incoerente. Inoltre i ticket già usciti dal Google Calendar non
  vi rientrano da soli: dipendono dal prossimo `sync:stories-calendar` schedulato.
- **Il cliente non sa di dover validare.** Per gli 8 ticket con creator customer non parte
  alcuna notifica all'ingresso in `pending_release`: il cliente li vede solo se accede
  spontaneamente a "I miei Ticket". Nemmeno `CustomerStoriesDigest` (righe 45-54) li
  intercetta: ha bucket per `done`/`testing`/`todo`+`progress`/`new`, nessuno per `tested`
  né per `pending_release`. La richiesta di ok deve arrivargli per un canale esterno a
  Orchestrator (mail manuale, Slack, link staging diretto). Accettato consapevolmente.
- **Lo stato unico fonde due attese diverse.** `pending_release` non distingue "attende il
  cliente" da "attende la release aggregata": owner, canale di sollecito e tempi sono
  diversi, ma una volta dentro sono indistinguibili. Decisione esplicita (vedi Out of scope);
  lo split 8/8 osservato è inoltre inferito dal ruolo del creator, non un dato osservato —
  un creator interno può aver aperto il ticket per conto di un cliente che deve validare.

## Out of scope

- **Reminder schedulato** sui ticket fermi in `pending_release` (follow-up, vedi Rischi)
- **Notifica email** al cliente o al team sull'ingresso in `pending_release`
- **Modifiche a `StoryShowedByCustomer`** (`/resources/story-showed-by-customers`,
  "I miei Ticket"): è la vista **del cliente** (menu section `CUSTOMER`, `canSee` solo
  `hasRole(Customer)`, `indexQuery()` filtra su `creator_id = utente loggato`), non la vista
  interna del team. Il suo `$whereNotIn` (riga 57) esclude solo `done`/`rejected` e resta
  invariato **volutamente**: è il punto in cui il cliente deve continuare a vedere i ticket
  in attesa del suo ok. Da non confondere con `CustomerStory`
  (`/resources/customer-stories`, "Ticket"), che è la vista del team ed è quella da ripulire.
- Le altre lens (`ToBeTestedStory`,
  `DeveloperStory`, `BacklogStory`, `ArchivedStories`, `AssignedToMeStory`): filtrano su
  stati specifici diversi da `tested`/`pending_release`, non sono impattate
- **`$closedInQuarter`** in `StoryMetricsCalculator` (righe 310-316): continua a contare
  solo `done` e `released`. `pending_release` non è chiuso e contarlo lì gonfierebbe la
  produttività con lavoro non rilasciato
- **`tested` nel SAL.** `salClosedStoryStatusValues()` non include `tested` oggi, e non lo
  includerà: i due tag RDO reali mostrano di conseguenza `0%` (6 ticket, 5 collaudati) e `7%`
  (14 ticket, 8 collaudati). Il dato è fuorviante ma la decisione è di **non** toccarlo in
  questo ciclo — riguarda uno stato con anni di storico e cambierebbe il SAL di tutti i tag
  (`wm-core` 88%→91%, `webmapp-app` 79%→81%, ecc.)
- **Due stati distinti** per i due scenari di attesa (`customer_review` +
  `ready_to_release`): valutati e scartati, un solo stato è sufficiente
- **Modifica al force-todo** su risposta del cliente (vedi Rischi): comportamento nativo
  mantenuto
- **Validazione del valore in `KanbanController::updateStatus()`** (righe 419-437): scrive
  `$request->input('status')` senza confronto con `StoryStatus::values()`, quindi accetta
  stringhe arbitrarie nella colonna `status`. Gap pre-esistente, non introdotto qui
- **`TagPolicy`**: non esiste, e `App\Nova\Tag`/`TagGroup` non definiscono `canSee()`. Il
  menu è nascosto ai customer, ma l'accesso via URL diretto non è bloccato da una policy.
  Gap pre-esistente, rilevato mentre si verificava la visibilità del SAL
- **Blocco o validazione delle transizioni di stato**: non viene impedito nessun passaggio,
  `pending_release` è raggiungibile e abbandonabile come qualsiasi altro stato
- **Automatismi di transizione** da `tested` a `pending_release`

## Moduli toccati

Feature **interamente custom**: `grep -rl "StoryStatus" wm-package/src/` non restituisce
nulla — il submodule non conosce il dominio Story. `nova-components/kanban-card/` è una
cartella del repo principale, non un submodule. **Tutto il codice e tutta la documentazione
vanno nel repo principale.**

| File | Modifica |
|---|---|
| `app/Enums/StoryStatus.php` | nuovo case + `label()` + `color()` |
| `lang/it.json`, `lang/en.json` | chiave `"Pending Release"` |
| `app/Nova/Dashboards/Kanban.php` | colonna + `statusFilterOverrides` |
| `app/Nova/CustomerStory.php` | `$whereNotIn` in `indexQuery()` |
| `app/Nova/DeveloperStory.php` | esclusione in `indexQuery()` |
| `app/Nova/AssignedToMeStory.php` | esclusione in `indexQuery()` |
| `app/Models/Tag.php` | `salClosedStoryStatusValues()` |
| `app/Services/Metrics/StoryMetricsCalculator.php` | `FORWARD_STATUSES` |
| `app/Traits/fieldTrait.php` | `loadingWhen()` |
| `tests/Feature/` | nuovi test |

**Nessuna modifica necessaria** a (verificato leggendo il codice):

- `app/Console/Commands/SyncStoriesWithGoogleCalendar.php` — `getTestedTickets()` filtra con
  `where('status', 'tested')` esatto: spostando il ticket esce **automaticamente** dal
  calendario, zero righe di codice
- `app/Models/Story.php` — le notifiche sono cablate stato per stato (`released` → creator,
  `todo` → assignee, `testing` → tester, `tested` → assignee); non esiste un default
  "qualsiasi cambio manda mail", quindi il cambio silenzioso è il comportamento nativo. La
  mail su `released` → creator resta identica quando il ticket completa il percorso
- `app/Http/Requests/Api/StoryApiRequest.php` — valida via `Rule::enum(StoryStatus::class)`,
  il nuovo case è accettato automaticamente
- `app/Nova/Filters/StoryStatusFilter.php` — itera su `StoryStatus::cases()` e usa
  `__($value->name)`, si popola da solo
- `app/Nova/StoryShowedByCustomer.php` — invariata **volutamente**: è il punto in cui il
  cliente continua a vedere i ticket in attesa del suo ok
- `nova-components/kanban-card/` — nessuna modifica al componente: `updateStatus()` scrive
  il valore ricevuto senza whitelist, quindi il drag&drop verso la nuova colonna funziona
  appena la colonna esiste. Il `'tested'` hardcodato in `KanbanController.php:108,145,207`
  riguarda solo la colonna virtuale `tested_by_others` e non va toccato (effetto corretto:
  spostando un ticket in `pending_release` esce anche da "Has Been Tested"). **Nessun
  bundle `dist/` da rigenerare**, quindi nessun `node --check` necessario
- `database/migrations/` — la colonna `status` è testuale, nessuna migrazione né constraint
  enum a DB
- **Nessuna migrazione dati.** I 16 ticket attualmente in `tested` non vengono toccati: è il
  developer a spostarli a mano, ticket per ticket, a feature conclusa
