> Ticket: oc:9032
> Stato: approvata il 2026-11-27

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
- <a id="r2"></a>**R2** — Una Story di cui il cliente non è creatore non cambia stato, anche se il
  suo id arriva nella richiesta dell'Action; le altre Story selezionate vengono chiuse, e il
  messaggio finale dice quante sono state saltate.
- <a id="r3"></a>**R3** — L'Action chiede solo una conferma: nessun campo nella modale. Il testo
  della conferma avvisa che le richieste vanno in archivio e che per riaprirne una bisogna
  contattare il team. Chi vuole lasciare un messaggio lo fa con `RespondToStoryRequest` sul
  singolo ticket.
- <a id="r4"></a>**R4** — Per ogni Story chiusa dal cliente con l'Action parte una notifica Nova al
  dev assegnato (`user_id`) e al tester (`tester_id`): una sola se sono la stessa persona, nessuna
  a un ruolo non assegnato (una Story senza assegnatari non notifica nessuno). Nessuna mail.
- <a id="r5"></a>**R5** — Cambiano stato solo le Story selezionate: i figli (`parent_id`) di una
  Story chiusa restano nel loro stato.
- <a id="r6"></a>**R6** — L'Action è visibile solo agli utenti cliente; il team continua a usare
  `StoryToDoneStatusAction`.
- <a id="r7"></a>**R7** — Nome dell'Action, testo della modale, messaggi di esito e notifica sono
  tradotti in `it` e `en`.

## Rischi

- **Chiusura di Story altrui** via chiamata diretta all'endpoint dell'Action: Nova esegue l'Action
  sugli id ricevuti senza applicare l'`indexQuery`, e la policy non protegge nulla, quindi il
  controllo su `creator_id` dentro l'Action è l'unica difesa ([R2](#r2)). Si verifica con un test
  che chiama l'endpoint dell'Action, non solo la classe.
- **Lavoro in corso chiuso dal cliente**: con la chiusura da qualsiasi stato, un dev può
  ritrovarsi chiuso un ticket in `progress`; la notifica Nova ([R4](#r4)) è la mitigazione.
  Accettato dal dev che le Story senza assegnatario — sul DB locale tutte in `backlog` — non
  notifichino nessuno: su di esse non lavora nessuno.
- **Nessun annullamento lato cliente**: una Story chiusa esce da «my stories» e nell'archivio il
  cliente non ha Action; la mitigazione è l'avviso nella conferma ([R3](#r3)), la riapertura la fa
  il team.
- **Metriche del team**: una Story assegnata e chiusa dal cliente conta come chiusa per il dev
  assegnato nelle metriche di team performance. Accettato dal dev.

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

- `app/Nova/Actions/` — nuova Action per il cliente
- `app/Nova/Story.php` — registrazione nel ramo cliente di `actions()`
- `lang/it.json`, `lang/en.json` — testi
- `tests/Feature/` — test dell'Action
