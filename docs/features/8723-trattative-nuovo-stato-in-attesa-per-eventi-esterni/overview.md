> Ticket: oc:8723

# Trattative: nuovo stato «in attesa» per eventi esterni

## Cosa cambia

Le trattative (Quote) hanno un nuovo stato per quelle già presentate che aspettano un evento che
non dipende da noi: l'esito di un bando, una risposta del cliente. Il commerciale ci sposta le
trattative da «Presentata», che così conta solo quelle su cui si può agire.

Il nuovo stato è diverso da «In attesa di ordine», dove il cliente ha già detto sì, e da
«Fredda», dove la trattativa è quasi persa.

Nome provvisorio, da confermare in review (vedi «Domande aperte»):

| | Valore |
|---|---|
| case PHP | `On_Hold` |
| valore salvato in DB e nell'API | `on hold` |
| etichetta EN | `On Hold` |
| etichetta IT | `In attesa di esito` |

Nel Kanban della dashboard `Sales` compare una nuova colonna fra «Presentata» e «In attesa di
ordine», con il suo conteggio e la sua somma in euro, come le altre.

## Perché

Richiesta dell'area commerciale nella call interna del 30/09/2026 (tag
*[CALL][orchestrator]proposte di evoluzione area commerciale*, macro area 2): «stai aspettando
qualcosa che non dipende da te». Oggi queste trattative restano in «Presentata» e ne aumentano il
totale, quindi quel numero non dice quanto valgono le trattative su cui si sta lavorando. Nicola
non ne sente il bisogno ma è d'accordo; Alessio lo considera un «nice to have».

## Requisiti

- [ ] Nuovo case in `App\Enums\QuoteStatus`, dichiarato fra `Presented` e `Waiting_For_Order`:
      la posizione decide l'ordine della colonna nel Kanban (`app/Nova/Dashboards/Sales.php:84`).
- [ ] Riga corrispondente in `QuoteStatus::label()`, che non ha un ramo `default`: senza la riga
      la dashboard `Sales` va in 500.
- [ ] Chiavi di traduzione in `lang/it.json` e `lang/en.json` nelle due forme con cui il repo
      legge gli stati della trattativa: nome del case (`On_Hold`, letto da
      `app/Nova/Filters/QuoteStatusFilter.php:41`) ed etichetta scritta in `label()` (`On Hold`,
      letta da Kanban, elenco, scheda e form: `app/Nova/Quote.php:141,174`). Il valore grezzo
      (`on hold`) e la sua forma `ucfirst()` non si aggiungono: per gli stati della trattativa
      nessun codice li traduce (la forma `ucfirst()` è delle Story, il valore minuscolo dei
      Customer). Vedi `docs/knowledge/traduzioni-stati-enum.md`.
- [ ] Nuovo stato aggiunto all'elenco `loadingWhen` del campo `Status` nell'elenco delle
      trattative (`app/Nova/Quote.php:146-152`). Senza, Nova lo mostrerebbe con l'icona verde di
      «completato», come una trattativa vinta.
- [ ] Il nuovo stato resta fra le trattative aperte, non in archivio: `Quote::indexQuery` esclude
      solo `Closed_Won`/`Closed_Lost` e `ArchivedQuotes` include solo quelli, quindi non va toccato
      nulla. Da verificare nel test manuale.
- [ ] Verifica manuale in Nova, in italiano e in inglese: colonna e drag & drop nel Kanban `Sales`,
      filtro per stato, Select dello stato nel form, scheda e elenco della trattativa.

## Domande aperte per chi fa la review

1. **Nome dello stato.** Né il ticket né il tag lo decidono. Proposta: `On_Hold` / `on hold` /
   «In attesa di esito». Il nome `Waiting` va evitato: la chiave di traduzione `"Waiting": "In
   attesa"` esiste già per lo stato delle Story (`lang/it.json:85`), e un case con lo stesso nome
   condividerebbe la traduzione con le Story. Anche l'etichetta italiana va tenuta diversa da «In
   attesa di ordine», perché nel Kanban le due colonne sono affiancate.

2. **Metric-card del nuovo stato in cima al Kanban `Sales`.** In alto c'è una card per ogni stato
   elencato in `metricStatuses` (`app/Nova/Dashboards/Sales.php:72-75`), con nome, somma in euro e
   numero di trattative; oggi sono «Da presentare», «Presentata» e «In attesa di ordine». Non
   esiste un totale complessivo: ogni card conta solo il suo stato.

   Esempio: «Presentata» ha 32 trattative per € 400.000; se ne spostano 10, per € 150.000, nel
   nuovo stato.
   - **A — nessuna card:** in alto resta «Presentata € 250.000 · 22». I € 150.000 si vedono solo
     scorrendo fino alla loro colonna. Pro: in alto solo gli stati su cui si agisce, come oggi per
     «Fredda». Contro: soldi ancora vivi spariscono dal riepilogo.
   - **B — card in più:** in alto compare anche «In attesa di esito € 150.000 · 10». Pro:
     «Presentata» è pulita comunque, e il valore in attesa di eventi esterni (per esempio i bandi)
     resta visibile. Contro: quattro card invece di tre, e uno stato non lavorabile ha lo stesso
     peso visivo degli altri.

   **Consiglio: B.** Le card sono separate per stato, quindi aggiungerne una non sporca il totale
   di «Presentata»; l'unico effetto di A è nascondere dal riepilogo dei soldi ancora vivi. Costa
   una riga. Da verificare in ogni caso: se le card si aggiornano subito dopo un trascinamento o
   solo ricaricando la pagina (vale già per le card esistenti).

3. **Skill Claude commerciale.** L'API accetta il nuovo stato da sola (`QuoteApiRequest.php:24`
   prende l'elenco da `QuoteStatus::cases()`), ma la skill vive fuori da questo repo e non si sa
   se abbia un elenco fisso degli stati nelle sue istruzioni. Se ce l'ha, finché non viene
   aggiornata non proporrà il nuovo stato e leggerà un valore sconosciuto sulle trattative
   spostate da Nova. Chi la mantiene?

4. **Test automatico (facoltativo).** Il ticket non chiede test. Proposta: un test che, per ogni
   case di `QuoteStatus`, verifica che `label()` risponda senza errori e che `lang/it.json` e
   `lang/en.json` abbiano le chiavi nelle due forme lette dal codice (nome del case ed etichetta di `label()`). Copre anche gli stati aggiunti in
   futuro: chi aggiunge un case e dimentica una traduzione lo scopre dal test, non da un utente
   che legge il testo grezzo. Circa una decina di righe. Aggiungerlo o no?

5. **Colore della colonna.** Il ticket non ne parla. Senza una riga in `QuoteStatus::color()` non
   si rompe nulla: il ramo `default` (`app/Enums/QuoteStatus.php:29`) dà il grigio `#9CA3AF`, lo
   stesso di «Prospect». Colori già usati, nell'ordine delle colonne:

   | Stato | Colore |
   |---|---|
   | Prospect | grigio `#9CA3AF` |
   | Da presentare | ambra `#F59E0B` |
   | Presentata | viola `#8B5CF6` |
   | *nuovo stato* | **azzurro `#0EA5E9`** (proposta) |
   | In attesa di ordine | arancio `#F97316` |
   | Fredda | grigio scuro `#6B7280` |
   | Chiuso vinto | verde `#10B981` |
   | Chiuso perso | rosso `#EF4444` |

   **Consiglio: azzurro `#0EA5E9`**, per tre motivi:
   - grigi, giallo-arancio, viola, verde e rosso sono già presi: restano blu/azzurro, indigo e rosa;
   - la colonna sta fra il viola di «Presentata» e l'arancio di «In attesa di ordine»: l'indigo
     sarebbe troppo vicino al viola, il rosa ricorda il rosso di «Chiuso perso»;
   - l'azzurro non trasmette allarme né successo (rosso e verde sono già legati alla chiusura) e
     richiama una pausa in attesa.

   Aggiungere il colore o lasciare il grigio di default?

6. **Valori ammessi nella documentazione OpenAPI del filtro `status`.** Il ticket non lo chiede e
   l'API funziona anche senza. Oggi la descrizione del filtro di `GET /api/quotes`
   (`app/Http/Controllers/Api/QuoteController.php:46`) cita solo due esempi:

   > Filter by status. Accepts a single value (?status=new) or multiple via array syntax
   > (?status[]=new&status[]=presented).

   Proposta: aggiungere l'elenco completo («Allowed values: new, to present, presented, on hold,
   waiting for order, cold, closed won, closed lost»), così chi aggiorna la skill commerciale
   (domanda 3) trova il nuovo stato nella documentazione generata da Scramble. Aggiungerlo o no?

## Rischi

- **Skill commerciale non allineata** (domanda aperta 3): il nuovo stato funziona in Orchestrator
  ma la skill potrebbe ignorarlo. Mitigazione: verificare la skill prima del rilascio e segnalarne
  l'aggiornamento come lavoro separato nel suo repo.
- **Traduzioni mancanti in silenzio:** una chiave dimenticata non dà errore, mostra il testo
  grezzo. Mitigazione: checklist delle due forme nei Requisiti, verifica manuale in it/en,
  eventuale test (domanda aperta 4).

## Out of scope

- Spostare le trattative oggi in «Presentata» nel nuovo stato: lo fanno i commerciali a mano.
- Salvare cosa si sta aspettando o una data di ricontrollo: il ticket chiede solo lo stato.
- Controlli sulle transizioni di stato: oggi non esistono per nessuno stato della trattativa.
- La Metric `app/Nova/Metrics/SentQuotes.php`, che usa `QuoteStatus::Sent` (case inesistente):
  è un difetto preesistente e la Metric non è usata da nessuna parte.

## Moduli toccati

Tutto nel repo principale, nessun submodule.

- `app/Enums/QuoteStatus.php` — case, `label()`; `color()` solo se in review si sceglie il colore (domanda 5)
- `lang/it.json`, `lang/en.json` — chiavi di traduzione
- `app/Nova/Quote.php` — `loadingWhen` del campo `Status`
- `app/Http/Controllers/Api/QuoteController.php` — solo se in review si sceglie di documentare i valori (domanda 6)
- `app/Nova/Dashboards/Sales.php` — solo se in review si sceglie la metric-card (domanda 2)
- `tests/…` — solo se in review si sceglie il test (domanda 4)
