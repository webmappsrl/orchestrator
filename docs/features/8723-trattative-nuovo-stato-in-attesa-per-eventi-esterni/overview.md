> Ticket: oc:8723

# Trattative: nuovo stato «in attesa» per eventi esterni

## Cosa cambia

Le trattative (Quote) hanno un nuovo stato per quelle già presentate che aspettano un evento che
non dipende da noi: l'esito di un bando, una risposta del cliente. Il commerciale ci sposta le
trattative da «Presentata», che così conta solo quelle su cui si può agire.

Il nuovo stato è diverso da «In attesa di ordine», dove il cliente ha già detto sì, e da
«Fredda», dove la trattativa è quasi persa.

Nomi decisi in review (PR #260):

| | Valore |
|---|---|
| case PHP | `On_Hold` |
| valore salvato in DB e nell'API | `on hold` |
| etichetta EN | `On Hold` |
| etichetta IT | `In attesa di esito` |
| colore | azzurro `#0EA5E9` |

Nel Kanban della dashboard `Sales` compare una nuova colonna azzurra fra «Presentata» e «In attesa
di ordine», con il suo conteggio e la sua somma in euro, come le altre.

Il filtro `status` di `GET /api/quotes` viene validato sui valori di `QuoteStatus`: la
documentazione OpenAPI elenca i valori ammessi ricavandoli dall'enum, e un valore sconosciuto
risponde con 422 invece che con un elenco vuoto.

## Perché

Richiesta dell'area commerciale nella call interna del 30/09/2026 (tag
*[CALL][orchestrator]proposte di evoluzione area commerciale*, macro area 2): «stai aspettando
qualcosa che non dipende da te». Oggi queste trattative restano in «Presentata» e ne aumentano il
totale, quindi quel numero non dice quanto valgono le trattative su cui si sta lavorando. Nicola
non ne sente il bisogno ma è d'accordo; Alessio lo considera un «nice to have».

La validazione del filtro `status` è stata chiesta in review: oggi la documentazione del filtro
cita solo due esempi (`app/Http/Controllers/Api/QuoteController.php:46`) e il valore arriva a
`where`/`whereIn` senza controlli (`:60-67` su `develop`). Con la validazione `Rule::enum` e il tipo enum
nell'attributo OpenAPI, Scramble ricava dall'enum l'elenco dei valori ammessi, che resta aggiornato
anche con gli stati futuri (come ci si arriva è in `notes.md`, «Task 6-7»).

## Requisiti

- [ ] Nuovo case `On_Hold = 'on hold'` in `App\Enums\QuoteStatus`, dichiarato fra `Presented` e
      `Waiting_For_Order`: la posizione decide l'ordine della colonna nel Kanban
      (`app/Nova/Dashboards/Sales.php:84`).
- [ ] Riga corrispondente in `QuoteStatus::label()` (`__('On Hold')`), che non ha un ramo
      `default`: senza la riga la dashboard `Sales` va in 500.
- [ ] Riga in `QuoteStatus::color()`: azzurro `#0EA5E9`.
- [ ] Chiavi di traduzione in `lang/it.json` e `lang/en.json` nelle tre forme con cui il repo
      legge gli stati della trattativa: nome del case (`On_Hold`, letto da
      `app/Nova/Filters/QuoteStatusFilter.php:41`), etichetta scritta in `label()` (`On Hold`,
      letta da Kanban, scheda, elenco e form: `app/Nova/Quote.php:142,160,176`) e valore salvato nel
      DB (`on hold`, letto dalla card «Quotes by Status» dell'elenco: `app/Nova/Quote.php:334-338`
      → `app/Nova/Metrics/DynamicPartitionMetric.php:143-144`, `__($key)`). IT «In attesa di
      esito», EN «On Hold». La forma `ucfirst()` del valore non si aggiunge: per gli stati della trattativa nessun codice
      la usa.
      Vedi `docs/knowledge/traduzioni-stati-enum.md`.
- [ ] Nuovo stato aggiunto all'elenco `loadingWhen` del campo `Status` nell'elenco delle
      trattative (`app/Nova/Quote.php:146-154`). Senza, Nova lo mostrerebbe con l'icona verde di
      «completato», come una trattativa vinta.
- [ ] Il nuovo stato resta fra le trattative aperte, non in archivio: `Quote::indexQuery` esclude
      solo `Closed_Won`/`Closed_Lost` e `ArchivedQuotes` include solo quelli, quindi non va toccato
      nulla. Da verificare nel test manuale.
- [ ] Filtro `status` di `GET /api/quotes` validato con `Rule::enum(QuoteStatus::class)`, sia per
      il valore singolo (`?status=new`) sia per l'elenco (`?status[]=…`). Un valore sconosciuto
      risponde con 422. Un elemento vuoto nell'elenco (`?status[]=`) non filtra, come `?status=`.
- [ ] Test su `QuoteStatus`, sul modello di `tests/Feature/PendingReleaseStatusTest.php:50` e
      `:79`: per ogni case, `label()` non vuota, `color()` hex valido, e le chiavi nome del case ed
      etichetta e valore presenti in `lang/it.json` e `lang/en.json`. La chiave dell'etichetta non si scrive
      a mano: `label()` chiama già `__()` e restituisce il testo tradotto, quindi il test imposta
      una lingua inesistente (`zz`, anche come lingua di riserva) e legge da `label()` la chiave
      grezza. Così anche gli stati futuri sono coperti senza toccare il test.
- [ ] Test del filtro `status` in `tests/Feature/Api/QuoteApiTest.php`: valore singolo valido,
      elenco valido, valore sconosciuto con 422, `?status=` vuoto e
      `?status[]=` vuoto che non filtrano, elemento vuoto insieme a valori validi.
- [ ] Verifica manuale in Nova, in italiano e in inglese: colonna e drag & drop nel Kanban `Sales`,
      filtro per stato, Select dello stato nel form, scheda e elenco della trattativa.

## Rischi

- **Cambio di contratto dell'API sul filtro `status`:** oggi `GET /api/quotes?status=xyz` risponde
  200 con un elenco vuoto, dopo risponde 422. Un client esterno (skill Claude commerciale, skill
  Cowork) che manda un valore non valido, per esempio scritto in un altro formato, smette di
  ricevere una risposta. È un effetto voluto, deciso in review. Mitigazione: segnalarlo come cambio
  di contratto nella PR e nelle note del ticket, così chi mantiene i client lo sa prima del deploy.
- **Traduzioni mancanti in silenzio:** una chiave dimenticata non dà errore, mostra il testo
  grezzo. Mitigazione: il test su `QuoteStatus` controlla le tre forme per ogni case, compresi
  quelli futuri.

## Out of scope

- La metric-card del nuovo stato in cima al Kanban `Sales`: il ticket non la chiede. Se l'area
  commerciale la vorrà, si aggiunge con una riga in `metricStatuses`
  (`app/Nova/Dashboards/Sales.php:72-76`).
- Spostare le trattative oggi in «Presentata» nel nuovo stato: lo fanno i commerciali a mano.
- Salvare cosa si sta aspettando o una data di ricontrollo: il ticket chiede solo lo stato.
- Controlli sulle transizioni di stato: oggi non esistono per nessuno stato della trattativa.
- La Metric `app/Nova/Metrics/SentQuotes.php`, che usa `QuoteStatus::Sent` (case inesistente):
  è un difetto preesistente e la Metric non è usata da nessuna parte.

## Moduli toccati

Tutto nel repo principale, nessun submodule.

- `app/Enums/QuoteStatus.php` — case, `label()`, `color()`
- `lang/it.json`, `lang/en.json` — chiavi di traduzione
- `app/Nova/Quote.php` — `loadingWhen` del campo `Status`
- `app/Http/Controllers/Api/QuoteController.php` — validazione del filtro `status`
- `tests/Feature/QuoteStatusTest.php` — nuovo, test sull'enum e sulle traduzioni
- `tests/Feature/Api/QuoteApiTest.php` — test del filtro `status`
