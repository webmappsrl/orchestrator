> Ticket: oc:8734

# Trattative: validazione numerica del prezzo dei servizi aggiuntivi in Nova

## Cosa cambia

Nella scheda Nova del preventivo, i servizi aggiuntivi (descrizione → prezzo) accettano solo
prezzi numerici, con la stessa regola già usata dall'API (`AdditionalServicesMap`). Esempi:
«1500» e «1500,50» passano; «da definire», «1.500» (punto delle migliaia) e il prezzo vuoto
vengono rifiutati con un messaggio sul campo, e il preventivo non viene salvato.

Il PDF del preventivo non va più in errore se trova un prezzo non valido già salvato prima di
questa correzione: nella riga del servizio mostra il testo così com'è (es. «da definire»),
senza il simbolo €.

## Perché

Oggi da Nova si può salvare un prezzo testuale, e il download del PDF di quel preventivo
fallisce con un errore 500: `number_format()` riceve una stringa non numerica e PHP 8 lancia un
`TypeError` (`resources/views/quote-pdf.blade.php:272`). La richiesta viene dalla call del
30/09/2026 sull'area commerciale: «Però non deve essere possibile mettere un non numero qua»;
«sia qui sia sulle api». L'API controlla già i prezzi (`app/Http/Requests/Api/QuoteApiRequest.php:26`);
manca la parte Nova, e la regola del team è che Nova e API accettino gli stessi valori.

Il ticket nasce da un PDF andato in errore, quindi in produzione ci sono preventivi già salvati
con prezzi non validi (il DB locale non è rappresentativo: nessun caso, ultima modifica a maggio
2025). Il controllo in Nova impedisce di crearne di nuovi; la tolleranza del PDF rende di nuovo
scaricabili quelli esistenti.

**Eccezione a una decisione precedente.** `docs/knowledge/quote-api-e-pdf.md:171-175` (oc:8413)
dice che su un documento commerciale il 500 è preferibile a un totale mancante, perché il 500 si
vede. Qui il problema non viene nascosto: la riga mostra il prezzo come è stato scritto, quindi
chi legge il PDF vede che quella voce non è un importo. Per un prezzo come «1.500» il problema
anzi diventa visibile: oggi la riga stampa «1,50 €», d'accordo con un totale sbagliato.

## Requisiti

- [ ] In Nova, al salvataggio di un preventivo (creazione e modifica), i servizi aggiuntivi di
      ogni lingua del `NovaTabTranslatable` vengono controllati con `AdditionalServicesMap`,
      senza modificarla. Il controllo sta nel metodo `afterValidation` della risorsa
      `app/Nova/Quote.php`: per ogni lingua legge la stringa JSON inviata da Nova
      (`translations_additional_services_<locale>`), la converte in array e la passa alla regola.
      Gli errori si aggiungono al validator con quella stessa chiave, quindi compaiono sotto il
      campo della lingua giusta e il salvataggio risponde 422.
- [ ] La regola non va messa in `->rules()`, `->creationRules()` né `->updateRules()` del campo:
      `NovaTabTranslatable` costruisce i campi per lingua nel costruttore e tratta ogni regola
      come stringa (`strpos()` in `vendor/kongulov/nova-tab-translatable/src/NovaTabTranslatable.php:189-191`
      e `:222-223`, controllo `instanceof` solo sul vecchio `Illuminate\Contracts\Validation\Rule`).
      Con `AdditionalServicesMap` (`ValidationRule`) PHP lancia `TypeError` e tutta la risorsa
      Quote risponde 500.
- [ ] Il messaggio d'errore usa il nome del campo come lo mostra Nova, «Servizi aggiuntivi [it]»
      (`NovaTabTranslatable.php:61-63`), passato come `$attribute` alla regola; la chiave
      dell'errore resta quella interna.
- [ ] Se l'errore riguarda una lingua diversa da quella aperta, la sua tab si colora di rosso ma
      non si apre da sola (comportamento del package, non si cambia).
- [ ] Un commento accanto al `KeyValue` rimanda al controllo in `afterValidation`.
- [ ] Il PDF mostra il prezzo formattato (`1.500,00 €`) quando è valido secondo
      `AdditionalServicesMap::isValidPrice()`, e il testo così com'è, senza €, quando non lo è.
      Il download non va più in errore.
- [ ] Test Feature su Nova, sia in creazione (`POST /nova-api/quotes`) sia in modifica
      (`PUT /nova-api/quotes/{id}`): con «Formazione: da definire» la risposta è 422 con l'errore
      sul campo e il preventivo non cambia; con «1500» il salvataggio va a buon fine. Il test
      invia il campo come lo invia Nova (`translations_additional_services_it`, stringa JSON),
      non come array.
- [ ] Test sul PDF: con un prezzo non valido già salvato il download non va in errore, e l'HTML
      generato (`view('quote-pdf', …)->render()`, come in `tests/Feature/QuotePdfBillingPlanTest.php:14-17`)
      contiene il testo originale nella riga.
- [ ] `docs/knowledge/quote-api-e-pdf.md` aggiornata: la riga che dice «Nova non applica la
      stessa regola al KeyValue», e accanto alla decisione di oc:8413 (righe 171-175) l'eccezione
      descritta in «Perché».

## Rischi

- **Totale del PDF con un prezzo vecchio non valido:** il totale
  (`getTotalAdditionalServicesPrice()`, `app/Models/Quote.php:152-157`) converte il prezzo con
  `(float)`: «da definire» vale 0, ma «1.500» vale 1,5. Dopo la correzione la riga mostra il testo
  originale mentre il totale usa quel valore, quindi la differenza si vede nel PDF. Il calcolo
  del totale resta fuori da questo ticket.
- **500 su tutta la risorsa Quote** se la regola finisse nelle regole del campo dentro
  `NovaTabTranslatable`: evitato facendo il controllo in `afterValidation` (vedi Requisiti).

## Out of scope

- Il calcolo dei totali dei servizi aggiuntivi (model, Nova, metriche, API): resta com'è.
- L'API e la regola `AdditionalServicesMap`: non cambiano. La richiesta del ticket sul lato API è
  già soddisfatta dalla regola esistente.
- La correzione dei preventivi già salvati con prezzi non validi: si correggono in Nova, che dopo
  questa modifica obbliga a scrivere un prezzo valido al primo salvataggio.

## Moduli toccati

Tutto nel repo `orchestrator`, nessun submodule.

- `app/Nova/Quote.php` — controllo in `afterValidation` e commento sul `KeyValue` dei servizi
  aggiuntivi
- `resources/views/quote-pdf.blade.php` — riga del prezzo tollerante ai valori non validi
- `tests/Feature/QuoteNovaResourceTest.php` — test del salvataggio da Nova
- `tests/Feature/QuotePdfServiceTest.php` / `tests/Feature/QuotePdfBillingPlanTest.php` — test del
  PDF con prezzo non valido (download e HTML)
- `docs/knowledge/quote-api-e-pdf.md` — aggiornamento della pagina di conoscenza
