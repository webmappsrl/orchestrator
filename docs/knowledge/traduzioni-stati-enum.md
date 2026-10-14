# Traduzione degli stati enum in Nova

Perché lo stesso stato compare in `lang/*.json` sotto più chiavi diverse, e perché `label()` non è
opzionale.

## Stato attuale

Il repo costruisce la chiave di traduzione di uno stato in **quattro modi diversi**, a seconda del
punto di lettura. Le forme producono stringhe diverse a partire dallo stesso case, e ognuna cerca
la propria chiave. Non tutti gli enum passano da tutte e quattro (`StoryStatus` sì, `QuoteStatus`
no): per sapere quali chiavi servono a uno stato bisogna guardare la colonna «Dove» della tabella.

| Forma | Dove | Chiave prodotta (esempi) |
|---|---|---|
| stringa scritta a mano nel `match` di `label()` | `StoryStatus::label()`, `QuoteStatus::label()` (Kanban, `Status`/`Select` di `app/Nova/Quote.php`) | `"Pending Release"`, `"To Present"`, `"Closed Won"` |
| `__($case->name)` — il **nome del case**, non il valore | `QuoteStatusFilter:41`, `StoryStatusFilter:43`, `CustomerStatusFilter:51`, `EditCustomerStatus:47` (`EpicStatusFilter:41` non traduce affatto) | `"To_Present"`, `"Waiting_For_Order"`, `"Closed_Won"`, `"Closed_Lost"`, `"PendingRelease"` |
| `__(ucfirst($case->value))` — il **valore**, con la sola iniziale maiuscola | `fieldTrait::getOptions()` e `fieldTrait:336` (solo `StoryStatus`), `app/Nova/Customer.php:408,414,419` (solo `CustomerStatus`) | `"Pending_release"`, `"Backlog"`, `"Unknown"` |
| `__($valore)` — il **valore grezzo** salvato nel DB, minuscolo e con gli spazi | `DynamicPartitionMetric.php:143-144` (`__($key)` sul raggruppamento per `status`): card «Quotes by Status» di `app/Nova/Quote.php:334-338` (`QuoteStatus`) e card `Status` di `app/Nova/CustomerTickets.php:58` (`StoryStatus`); `CustomersByStatus.php:21` (`CustomerStatus`) | `"presented"`, `"on hold"`, `"closed won"`, `"unknown"` |

Per `QuoteStatus` le forme lette sono quindi **tre**: nome del case, etichetta di `label()` e valore
grezzo (`"On_Hold"`, `"On Hold"`, `"on hold"`). La forma `ucfirst()` per gli stati della trattativa
non serve. `tests/Feature/QuoteStatusTest.php` controlla le tre chiavi per ogni case, compresi
quelli futuri: per ricavare la chiave da `label()` imposta una lingua inesistente (`zz`), così
`__()` restituisce la chiave grezza invece della traduzione.

Alcuni dettagli che spiegano da soli metà delle chiavi presenti nei json:

- **`ucfirst()` non tocca gli underscore.** `pending_release` → `Pending_release`, mai
  `Pending Release`. È l'unico valore multi-parola fra quelli che passano da questa forma, perché
  i valori di `StoryStatus` e `CustomerStatus` sono altrimenti parole singole.
- **I valori di `QuoteStatus` contengono spazi, non underscore** (`'to present'`,
  `'waiting for order'`, `'closed won'`, `'closed lost'`). Le chiavi con underscore come
  `To_Present` o `Closed_Won` **non** vengono da `ucfirst()` del valore: sono i **nomi dei case**
  (`QuoteStatus::To_Present`), prodotti dai Filter Nova.
- `CustomersByStatus.php:21` fa `ucfirst(__($status->value))`: **traduce prima e capitalizza dopo**,
  quindi cerca il valore grezzo minuscolo (`"unknown"`, `"active"`…), chiavi presenti in entrambi i
  json. È l'ordine opposto rispetto a `fieldTrait` e `Customer.php`, e per questo cerca una chiave
  diversa: è la forma del valore grezzo, non quella `ucfirst()`.
- **La forma del valore grezzo non si trova cercando `__(` vicino a `status`:** in
  `DynamicPartitionMetric` la chiamata è un generico `__($key)` su qualunque colonna raggruppata.
  Per sapere se un enum passa da lì bisogna cercare chi istanzia la metrica con `'status'`.

**Conseguenza pratica: non rimuovere una variante pensando che sia un duplicato o un typo.**
`"Closed Won"` serve a `label()`, `"Closed_Won"` al filtro Nova, `"closed won"` a chi traduce il
valore grezzo: sono tre punti di lettura distinti, e togliere la chiave sbagliata fa comparire la
stringa grezza in quella sola vista, in silenzio e senza errori.

Due eccezioni note, verificate nel codice (locale, 2026-09-12):

- **`"Partially Paid"` / `"Partially_Paid"` non corrispondono ad alcun case** di `QuoteStatus`,
  `StoryStatus` o `CustomerStatus`, né a nessun altro punto del codice: sono chiavi orfane,
  residuo di uno stato rimosso. Non sono un modello da imitare.
- **`"PendingRelease"` non esiste nei json**, quindi il `StoryStatusFilter` mostra il nome del case
  non tradotto. È il sintomo tipico della chiave mancante, su una vista secondaria.

`StoryStatus::label()` è inoltre un `match ($this)` **esaustivo senza ramo `default`**, a differenza
di `color()` e `collapse()` che ne hanno uno: aggiungere un `case` senza la riga corrispondente in
`label()` solleva `\UnhandledMatchError`, cioè un **500 sul dashboard Kanban** (la home operativa
del team) e su ogni index che risolva lo stato. `QuoteStatus::label()` è anch'esso senza `default`
(il `default` di `QuoteStatus` sta in `color()`).

## Come ci siamo arrivati

- **«Per `QuoteStatus` le chiavi minuscole del valore non le legge nessuno»** (oc:8723, superata):
  durante la pianificazione del nuovo stato `On_Hold` la forma `"on hold"` era stata tolta dai
  requisiti, sulla base di una ricerca di `__(` vicino alla parola «status» che non vedeva il
  `__($key)` generico di `DynamicPartitionMetric`. La review l'ha trovato: la fetta del nuovo stato
  nella card «Quotes by Status» si leggeva «on hold» anche in italiano. Da qui la quarta riga della
  tabella e il controllo sul valore in `QuoteStatusTest`.

- **La premessa che la variante con underscore fosse quella prodotta da `ucfirst($status->value)`**
  (oc:8426): falsa per cinque coppie su sei, perché vale solo per `pending_release`. La doppia
  chiave era un fatto reale, la spiegazione no. Corretta qui dopo verifica sugli enum e sui punti
  di lettura.
- L'unica strada per ridurre davvero il numero di forme sarebbe rifattorizzare i punti di lettura
  perché usino tutti `label()`, verificando prima che *ogni* case abbia una `label()` corrispondente
  a una chiave presente — altrimenti si rompono le etichette oggi funzionanti. Non è stato fatto:
  la convenzione era già nel repo prima di oc:8426, che l'ha solo documentata.
