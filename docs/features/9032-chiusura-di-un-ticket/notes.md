> Ticket: oc:9032
> Overview approvata il 2026-11-27 · impronta `9de27c815d824afb5374f95a835d295c7ecd8002`

# Notes — Chiusura di un ticket da parte del cliente

## Modifiche ai requisiti

### 2026-11-27 — Le Story altrui in una selezione multipla non risultano «saltate»
- **Requisito:** [R2](overview.md#r2)
- **Effetto:** modificato
- **Decide:** agente, da confermare con il dev alla review del diff
- **Perché:** con più id Nova applica l'`indexQuery` della Resource
  (`vendor/laravel/nova/src/Http/Requests/ActionRequest.php`, `toQueryWithoutScopes()`), che per
  il cliente filtra già per `creator_id`: la Story altrui non arriva all'Action e non si può
  contare. Con un id solo Nova applica il `detailQuery`, che non filtra, ed è il controllo in
  `handle()` a saltarla e contarla. In entrambi i casi la Story altrui non cambia stato.
- **Scartato:** contare comunque le Story scartate confrontando gli id della richiesta con quelli
  ricevuti: un cliente arriva a quel caso solo forgiando la richiesta, e il messaggio di esito non
  è rivolto a lui.
- **Testo nuovo:**
  > Una Story di cui il cliente non è creatore non cambia stato, anche se il suo id arriva nella
  > richiesta dell'Action; le altre Story selezionate vengono chiuse. Se la Story altrui raggiunge
  > l'Action, il messaggio finale la conta fra le saltate.

### 2026-11-27 — Nessun conteggio delle Story saltate
- **Requisito:** [R2](overview.md#r2)
- **Effetto:** modificato
- **Decide:** dev
- **Perché:** dall'interfaccia il cliente vede solo le proprie Story (l'`indexQuery` di
  `StoryShowedByCustomer` e di `Story` filtra per `creator_id`): una Story altrui arriva all'Action
  solo con una richiesta costruita a mano, e il messaggio «saltate» non lo vedrebbe mai un cliente
  che usa il pannello. Il controllo in `handle()` resta, perché con un id solo Nova non filtra e la
  policy non protegge; il secondo messaggio di esito e la sua chiave di traduzione sono tolti.
  Supera la voce precedente su [R2](overview.md#r2).
- **Scartato:** tenere il messaggio con il conteggio delle saltate.
- **Testo nuovo:**
  > Una Story di cui il cliente non è creatore non cambia stato, anche se il suo id arriva nella
  > richiesta dell'Action.

## Divergenze dal piano, task per task

### Task 1: Action di chiusura per il cliente
Il test `test_story_of_another_user_is_skipped` del piano è diventato tre test: selezione
multipla, id singolo e Resource `stories`, tutti con la Story altrui che resta invariata. Vedi la modifica a [R2](overview.md#r2). Inoltre
`resources` va passato come array: una stringa con gli id separati da virgola Nova la tratta come
un id solo.

## Bug trovati

### 2026-11-27 — Un'affermazione sbagliata nell'overview
Il primo rischio dell'overview dice che Nova esegue l'Action «senza applicare l'`indexQuery`». È
vero solo per un id singolo: con più id l'`indexQuery` viene applicato. Il controllo in `handle()`
resta necessario per il caso dell'id singolo e per la Resource `stories`. Da correggere alla
chiusura dell'overview.

## Decisioni

### 2026-11-27 — Notifica nell'Action, non nell'hook del model
- La notifica di [R4](overview.md#r4) parte dall'Action. La chiusura di una Story `released`
  dal form di modifica, che esiste già, resta senza notifica.
- **Scartato:** spostarla in `Story::updated`, così da coprire anche il form di modifica: il dev
  ha scelto di tenerla nell'Action.

### 2026-11-27 — Controllo del proprietario in `handle()`
- Il controllo su `creator_id` sta in `handle()` per poter contare le Story saltate
  ([R2](overview.md#r2)).
- **Scartato:** `canRun`, perché Nova scarta i record prima di `handle()` e il conteggio non
  sarebbe possibile.

### 2026-11-27 — Il controllo resta in `handle()` anche senza conteggio
- Tolto il conteggio delle saltate, il controllo poteva passare in `canRun`. Resta in `handle()`:
  funziona allo stesso modo, è già coperto dai test, e con `canRun` una richiesta con soli id
  altrui riceverebbe un errore generico di autorizzazione invece dell'esito «0 richieste».
- **Scartato:** spostarlo in `canRun`.

## Follow-up

- `StoryPolicy::update` restituisce sempre `true`: ogni Action lato cliente deve ripetere a mano
  il controllo su `creator_id`.
