> Ticket: oc:8734

# Notes — Trattative: validazione numerica del prezzo dei servizi aggiuntivi in Nova

## Divergenze dal piano, task per task

### Task 1 — Test Nova (prima rossi)

`NovaRequest::create()` da solo non basta, come invece prevedeva il piano: il validator della
risorsa chiama `$request->resource()`, che risolve `{resource}` dalla rotta e la cerca fra le
risorse registrate in Nova; nei test nessuna delle due cose c'è e la risposta è un 404 prima di
arrivare al controllo. Aggiunto in `QuoteNovaResourceTest` l'helper `novaRequest()`, che registra
`QuoteResource` con `Nova::resources()` e assegna alla richiesta la rotta
`nova-api/{resource}[/{resourceId}]`.

## Bug trovati

- Con la soluzione della prima overview (regola `AdditionalServicesMap` in `->rules()` del
  `KeyValue`) tutta la risorsa Quote sarebbe andata in 500: `NovaTabTranslatable` passa ogni regola
  a `strpos()` e riconosce come oggetto solo il vecchio `Illuminate\Contracts\Validation\Rule`.
  Trovato dalla challenge sull'overview, prima di scrivere codice; il controllo è stato spostato
  in `afterValidation()`.

## Decisioni

- **Controllo in `afterValidation()`, regola invariata.** La prima scelta era insegnare ad
  `AdditionalServicesMap` a leggere la stringa JSON di Nova; superata dal punto sopra, perché
  decodificando in `afterValidation()` la regola non serve toccarla.
- **PDF: prezzo non valido stampato così com'è, senza €.** Scartati il trattino (nasconde quanto
  scritto dal commerciale) e «0,00 €» (dice che il servizio è gratis). Eccezione documentata alla
  decisione di oc:8413 («meglio il 500»): la riga mostra il valore, quindi il problema resta visibile.
- **Test:** tenuti quelli su Nova e sul PDF; scartato un test unitario della regola sulla stringa
  JSON, perché la regola non cambia e il percorso è coperto dal test Nova.
- **Tag Orchestrator** (`environment-setup: tag-ambiente`): rimandati dalla dev a fine lavoro. Fra
  i candidati trovati per «orchestrator» e «backend», solo `orchestrator` (id 590) è pertinente ed
  è stato associato al ticket; `Backend Cyclando` e i tre tag `Documentation:` scartati perché di
  altri ambiti. Nessun tag di contenuto proposto: la dev ha chiesto di passare ai commit.
- **Review formale con `wm-review-ticket` saltata** per scelta della dev: due file di codice,
  overview rivista due volte, test e verifica manuale fatti; la review ufficiale è quella sulla PR.

## Verifica manuale

Fatta in Nova su un preventivo di prova con «Formazione: da definire»: PDF scaricato con la riga
«da definire» e «1.500,00 €» per Hosting; salvataggio rifiutato con il messaggio sotto il campo;
salvataggio riuscito con «1500,50». Non provati a mano la tab `en` in rosso e la creazione di un
nuovo preventivo: coperti dai test.

## Follow-up

- Il totale dei servizi aggiuntivi (`Quote::getTotalAdditionalServicesPrice()`) converte il prezzo
  con `(float)`: un prezzo vecchio «1.500» vale 1,5. Fuori scope di questo ticket.
- La descrizione del tag «[CALL][orchestrator]proposte di evoluzione area commerciale» punta a un
  notebook NotebookLM che non esiste più (`ff5ed196…`); quello attuale con lo stesso nome è vuoto.
