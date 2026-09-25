> Ticket: oc:8631

# Preventivi via API: gestire i campi di testo formattato del documento

## Cosa cambia

L'API dei preventivi (`/api/quotes`) espone in lettura e in scrittura i quattro campi di testo
formattato che oggi si compilano solo da Nova: `additional_info` (Informazioni aggiuntive),
`delivery_time` (Tempo di consegna), `payment_plan` (Piano di pagamento) e `billing_plan`
(Piano di fatturazione).

- Le risposte che restituiscono un preventivo includono i quattro campi.
- `POST /api/quotes` e `PATCH /api/quotes/{quote}` li accettano come HTML; un contenuto
  pericoloso (che può eseguire codice o caricare risorse esterne) viene rifiutato con un 422 che
  spiega cosa è sbagliato e cosa è corretto. Tutto ciò che Nova può produrre passa.
- Il PDF del preventivo stampa anche il Piano di fatturazione, che oggi non compare.
- `additional_services` viene validato: ogni prezzo deve essere in un formato che il PDF e il
  calcolo del totale sanno leggere, altrimenti 422.
- Se si conferma che Nova salva i quattro campi sotto la lingua sbagliata (`de`), il salvataggio
  viene corretto perché scriva sotto `it`.
- Un comando in sola lettura verifica, sui dati reali, che nessun preventivo esistente verrebbe
  rifiutato dalla nuova validazione.

## Perché

La skill `wm-preventivi` costruisce i preventivi via API fino ai totali, poi bisogna aprire
Nova e incollare a mano i testi che dicono al cliente cosa riceve, quando e a quali
condizioni. È la parte che si dimentica: un preventivo senza tempi di consegna e piano di
pagamento arriva al cliente incompleto. Oggi i quattro campi non sono né scrivibili né
leggibili via API: `QuoteApiRequest::rules()` non li elenca, quindi `validated()` li scarta
in silenzio e la risposta conferma un salvataggio che non è avvenuto.

Cercando di aggirare il limite, un testo descrittivo messo in `additional_services` (che si
aspetta un importo) ha fatto andare in errore la generazione del PDF (preventivo 218,
23/09/2026): il template applica `number_format()` a ogni valore e un testo produce un
`TypeError`.

Il Piano di fatturazione si compila in Nova ma `quote-pdf.blade.php` non lo stampa: il dev
conferma che è una mancanza, perché serve anche al cliente che riceve il PDF.

Dalla challenge era emerso il sospetto che Nova salvasse i quattro campi sotto la chiave `de`
invece che `it`. **Verificato in esecuzione (Task 1): falso allarme** — le chiavi `de` trovate hanno
valore `null` (Nova salva tutte le lingue della tab, quelle vuote come `null`); il testo scritto
nella tab IT viene salvato sotto `it`. Dettaglio in `notes.md`.

## Requisiti

**Salvataggio da Nova (da verificare per primo)**
- [ ] Verificare in locale, salvando un preventivo da Nova, sotto quale chiave di lingua finiscono
      i quattro campi Tiptap dentro `NovaTabTranslatable`.
- [ ] Se finiscono sotto una lingua diversa da quella della tab in cui sono stati scritti,
      correggere il salvataggio dei quattro campi così che il testo scritto nella tab `it` venga
      salvato sotto `it`.
- [ ] Se il bug non si riproduce, annotarlo in `notes.md` e proseguire senza modifiche a Nova.

**Lettura**
- [ ] Le risposte con la forma completa del preventivo (`index`, `show`, `store`, `update`,
      attach/detach di products e recurring-products) includono `additional_info`,
      `delivery_time`, `payment_plan`, `billing_plan`, valorizzati con la traduzione della
      lingua di default (`it`) così com'è salvata.
- [ ] Un campo senza traduzione `it` (o vuoto) viene restituito come `null`, mai `""`.

**Scrittura**
- [ ] `POST` e `PATCH` accettano i quattro campi, tutti opzionali (`sometimes`); in `PATCH` un
      campo non inviato resta invariato.
- [ ] Il valore viene scritto solo nella lingua di default (`it`), aggiungendo i quattro campi a
      `TRANSLATABLE_FIELDS` come già `notes` e `additional_services`.
- [ ] `null` e `""` sono equivalenti: rimuovono la traduzione `it` del campo, senza salvare una
      stringa vuota. Di conseguenza un `""` inviato torna come `null` nella risposta.
- [ ] Un contenuto accettato viene salvato **esattamente come inviato**: nessuna ripulitura o
      riscrittura lato server. L'unica trasformazione è `""` → `null`.
- [ ] Lunghezza massima 50.000 caratteri per campo.

**Contenuto HTML: si rifiuta solo ciò che è pericoloso**
- [ ] Formato: solo HTML (nessun Markdown da convertire).
- [ ] L'HTML viene analizzato con lo stesso parser usato da DomPDF (`masterminds/html5`), così
      validatore e rendering leggono il contenuto allo stesso modo.
- [ ] Tag ammessi: elenco ampio che copre tutto ciò che Tiptap ed `editHtml` possono produrre, tra
      cui `p`, `br`, `div`, `span`, `strong`, `b`, `em`, `i`, `u`, `s`, `strike`, `mark`, `code`,
      `pre`, `blockquote`, `h1`-`h6`, `ul`, `ol`, `li`, `hr`, `a`, `img`, `table`, `thead`,
      `tbody`, `tfoot`, `tr`, `th`, `td`, `colgroup`, `col`, `caption`, `font`, `sup`, `sub`,
      `small`.
- [ ] Tag sempre rifiutati: `script`, `iframe`, `frame`, `object`, `embed`, `style`, `link`,
      `meta`, `base`, `form`, `input`, `svg`, `math`, e ogni tag non in elenco.
- [ ] Attributi: ammessi tutti tranne gli handler `on*`. `class`, `dir`, `title`, `tt-mode`,
      `colspan`/`rowspan`/`colwidth` e simili passano.
- [ ] `style`: ammesso con qualsiasi proprietà, rifiutato se contiene `url(`, `image-set(`,
      `expression(`, `@import`, `javascript:`, backslash o commenti (i commenti vengono tolti prima
      del controllo, come fa DomPDF).
- [ ] URL, normalizzati come li legge il browser (tab e a capo tolti, caratteri di controllo ai
      bordi tolti, `\` letto come `/`): `href` solo `http:`, `https:`, `mailto:` (o ancora `#`);
      `src` (e `background`, `poster`) solo sotto `/storage/`, come percorso relativo o su host e
      porta identici a quelli di `APP_URL`, senza `..`; `srcset` sempre rifiutato.
- [ ] Un preventivo i cui campi vengono letti e rimandati **senza modifiche** passa sempre la
      validazione.
- [ ] Comando artisan in sola lettura `quotes:check-rich-text` che applica la regola ai quattro
      campi di tutti i preventivi (tutte le lingue) e riporta quelli che verrebbero rifiutati, con
      il motivo. Da lanciare in produzione prima del merge: se riporta qualcosa, si allarga la
      regola prima di rilasciare.

**Errori 422 comprensibili**
- [ ] Formato standard Laravel (`{message, errors: {campo: [...]}}`), invariato per chi consuma
      già l'API.
- [ ] Ogni messaggio dice **cosa è sbagliato** (campo, tag/attributo/valore rifiutato e perché) e
      **cosa è corretto** (cosa togliere o un esempio di valore valido).
- [ ] Gli elementi rifiutati sono raggruppati per tipo, con il numero di occorrenze; al massimo 10
      voci per campo, più una riga «…e altri N».

**Servizi aggiuntivi**
- [ ] `additional_services` deve essere un oggetto `{descrizione: prezzo}` con descrizioni non
      vuote, non una lista.
- [ ] Ogni prezzo è un numero JSON oppure una stringa `^-?\d+([.,]\d{1,2})?$`: al massimo un
      separatore decimale (punto o virgola), fino a due decimali, **nessun separatore delle
      migliaia**. Altrimenti 422 con il nome del servizio, il valore ricevuto, il motivo ed esempi
      validi. Il template PDF non viene toccato.

**PDF**
- [ ] `quote-pdf.blade.php` stampa il Piano di fatturazione subito dopo il Piano di pagamento,
      con la stessa grafica (`<h2 class="description">` + contenuto HTML) e solo se valorizzato.
- [ ] Il titolo della sezione usa una chiave di traduzione dedicata `"Billing plan"` in
      `lang/it.json` («Piano di fatturazione») e `lang/en.json` («Billing plan»), distinta
      dall'etichetta Nova `"Billing Plan"`.
- [ ] I testi scritti via API vengono stampati nel PDF come quelli scritti da Nova (paragrafi,
      elenchi, grassetti).

**Documentazione e test**
- [ ] I docblock `@response` degli 8 metodi con la forma completa del preventivo in
      `Api/QuoteController.php` includono i quattro campi (`string|null`); i body di
      `store`/`update` li documentano.
- [ ] Test feature: lettura, scrittura in create e update, svuotamento, PATCH parziale, limite di
      lunghezza, rifiuto con messaggio comprensibile per tag, attributo, `style`, URL e servizio
      aggiuntivo non ammessi, round-trip di HTML reale già presente nei dati (incluse immagini
      Tiptap con `tt-mode`), Piano di fatturazione presente nel PDF.
- [ ] Test del comando `quotes:check-rich-text`.
- [ ] `QuoteApiDocsTest` verifica che i quattro campi compaiano nello schema `/docs/api.json` per
      tutti gli 8 endpoint.

## Rischi

- ~~**Nova salva sotto la lingua sbagliata**~~ (emerso dalla challenge): verificato in Task 1,
  non si riproduce; le chiavi `de` esistenti hanno valore `null`.
- **Un contenuto creato da Nova rifiutato quando la skill lo rimanda.** `editHtml` permette HTML
  libero e il censimento è stato fatto su una copia locale senza immagini. Mitigazione: la regola
  rifiuta solo ciò che è pericoloso invece di elencare ciò che è ammesso, e
  `quotes:check-rich-text` va lanciato sui dati di produzione prima del merge.
- **Regola troppo larga.** I campi sono stampati senza escape nel PDF e resi da Tiptap in Nova, e
  DomPDF ha `enable_remote => true`. Mitigazione: rifiuto di script, frame, oggetti, `on*`,
  `javascript:`/`data:`, `url(` in `style` e immagini fuori dall'host dell'app; lo stesso parser
  di DomPDF per evitare letture diverse dello stesso HTML.
- **Prezzi che passano la validazione ma rompono il PDF o il totale.** Mitigazione: la regola
  ricalca esattamente ciò che template (`number_format`) e `getTotalAdditionalServicesPrice()`
  sanno leggere; il separatore delle migliaia è rifiutato.
- **Contratto API condiviso con client esterni.** Aggiungere quattro chiavi alla risposta non
  rompe chi la legge già, ma la validazione su `additional_services` può rifiutare richieste che
  oggi passano. È voluto: sono proprio quelle che mandano in errore il PDF.
- **Deriva documentazione/runtime.** Le chiavi vanno aggiunte a mano negli 8 docblock
  `@response`. Mitigazione: `QuoteApiDocsTest` ispeziona lo schema generato per tutti gli 8.
- **Messaggi 422 troppo lunghi** con HTML incollato da Word. Mitigazione: raggruppamento per tipo
  e tetto di 10 voci per campo.
- **Rollback.** Nessuna migration. Le traduzioni `it` scritte via API sopravvivono a un revert e
  il PDF continua a stamparle; `lang/*.json` va rollbackato insieme al blade, altrimenti il titolo
  del Piano di fatturazione compare come chiave grezza.

## Out of scope

- Altri campi del preventivo oltre ai quattro (e ad `additional_services` per la sola
  validazione).
- Gestione multilingua via API (parametro `lang`, oggetti `{it, en}`).
- Conversione Markdown → HTML, e normalizzazione dei contenuti legacy già salvati in stile
  Markdown.
- Interfaccia Nova, salvo l'eventuale correzione della lingua di salvataggio dei quattro campi.
- Validazione dei prezzi inseriti da Nova nel KeyValue di `additional_services`, e servizi con
  prezzo testuale («incluso», «da definire»): follow-up.
- Impaginazione e grafica del PDF, salvo l'aggiunta del blocco Piano di fatturazione; in
  particolare lo spostamento del Piano di pagamento prima dei Costi (come nel preventivo 156
  del 18/11/2025) lo valuterà il dev a parte.
- Rendere il template PDF tollerante ai prezzi non numerici già salvati in `additional_services`.

## Moduli toccati

Tutto nel repo principale `orchestrator` (nessun submodule coinvolto).

- `app/Nova/Quote.php` (ed eventuale classe di supporto) — solo se si conferma il salvataggio
  sotto la lingua sbagliata.
- `app/Http/Requests/Api/QuoteApiRequest.php` — regole per i quattro campi e per
  `additional_services`, messaggi di errore.
- `app/Rules/` — nuove regole di validazione dell'HTML e dei prezzi (nomi da definire nel piano).
- `app/Console/Commands/` — comando `quotes:check-rich-text`.
- `app/Http/Controllers/Api/QuoteController.php` — `TRANSLATABLE_FIELDS`, rimozione della
  traduzione su valore vuoto, `formatQuote()`, docblock `@response` e body.
- `resources/views/quote-pdf.blade.php` — blocco Piano di fatturazione.
- `lang/it.json`, `lang/en.json` — chiave `"Billing plan"` e testi dei messaggi di errore.
- `tests/Feature/Api/QuoteApiTest.php`, `tests/Feature/Api/QuoteApiDocsTest.php`, test delle
  nuove regole e del comando.
- `docs/knowledge/quote-api-e-pdf.md` — aggiornamento a fine lavoro.
