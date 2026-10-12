> Ticket: oc:8732

# Trattative: motivo della chiusura persa

## Cosa cambia

La trattativa (Quote) ha un nuovo campo di testo semplice, **«Motivo della chiusura persa»**
(colonna `lost_reason`), da scrivere quando la trattativa viene chiusa come persa
(`closed lost`).

- **Nova, form:** scegliendo «Chiuso Perso» nella Select «Status», sotto compare la Textarea
  «Motivo della chiusura persa», obbligatoria. Se resta vuota il salvataggio viene rifiutato con un
  messaggio sul campo. Con gli altri stati il campo non compare.
- **Nova, dettaglio:** il motivo si legge in sola lettura, solo nelle trattative in `closed lost`
  che ne hanno uno. Le trattative chiuse non compaiono nell'elenco «Quotes» ma in «Preventivi
  archiviati» (`app/Nova/ArchivedQuotes.php:29-37`): quella risorsa estende `Quote` e ne eredita i
  campi, quindi il motivo compare lì senza modifiche. Non compare negli index.
- **API:** `lost_reason` si può mandare in `POST /api/quotes` e `PATCH /api/quotes/{quote}` ed è
  restituito in tutte le risposte che contengono la Quote. Proposta: obbligatorio quando la stessa
  richiesta porta lo stato a `closed lost` (vedi «Domande aperte per il reviewer»).

Esempio: si apre «Portale Parco XY», si sceglie «Chiuso Perso», si scrive «Il cliente ha scelto un
fornitore più economico» e si salva. Da quel momento la trattativa è chiusa e il motivo si legge nel
dettaglio.

## Perché

L'area commerciale vuole sapere perché una trattativa è andata male (call del 30/09/2026: «motivo
strutturato la chiusura persa»; «un campo di testo, text area»). Allo scrum del 12/10 è stato
indicato come modello il motivo del respingimento fatto su Forestas (oc:8567, `wm-package`):
Textarea obbligatoria `max:2000`, visibile in sola lettura nel dettaglio solo con lo stato
corrispondente.

Il motivo va scritto **nello stesso salvataggio** che chiude la trattativa: una trattativa in
`closed won` o `closed lost` non è più modificabile, né da Nova né dall'API
(`app/Policies/QuotePolicy.php:49-51`, `app/Http/Controllers/Api/QuoteController.php:228`).
L'unica eccezione è il Kanban `Sales`, che cambia lo stato senza passare dalla policy.

Rispetto a Forestas cambia il punto in cui si scrive: lì lo stato si cambia solo con le Action
«Approva» / «Respingi», qui con la Select del form. Per questo il campo sta nel form, legato alla
Select, e non in un'Action.

## Requisiti

- [ ] Migration: colonna `lost_reason` `text` nullable su `quotes`; campo in `$fillable` di
      `Quote`, non traducibile.
- [ ] Nova, form: Textarea «Motivo della chiusura persa» che compare solo quando la Select
      «Status» vale `closed lost` (`dependsOn`), obbligatoria in quel caso, `max:2000`. Vale sia in
      creazione sia in modifica.
- [ ] Model: nell'hook `saving` di `Quote`, se lo stato non è `closed lost`, `lost_reason` diventa
      null. È l'unico punto che garantisce che una trattativa aperta non abbia mai un motivo, per
      ogni canale (form Nova, API, Kanban, comandi). Esempio: «Portale Parco XY», persa con
      «prezzo troppo alto», trascinata sul Kanban in «Presentato» perde il motivo; se poi viene
      richiusa da Nova la Textarea è vuota e il motivo va riscritto. Va in un listener a sé: quello
      già presente esce subito se la Quote non è un template (`app/Models/Quote.php:232-235`).
- [ ] Nova, dettaglio: motivo in sola lettura solo se lo stato è `closed lost` **e** il motivo è
      compilato (le trattative perse prima di questa feature non mostrano un campo vuoto); mai
      nell'index.
- [ ] API: `lost_reason` in `QuoteApiRequest` (`nullable`, `string`, `max:2000`; obbligatorio se
      `status` nella stessa richiesta è `closed lost`), restituito da `formatQuote()` e documentato
      negli shape `@response` del controller.
- [ ] Traduzioni: label (e messaggio, se non coperto da `validation.php`) in `lang/en.json` e
      `lang/it.json`.
- [ ] Test: model (motivo azzerato quando lo stato non è `closed lost`), Nova (campo
      visibile/obbligatorio solo con `closed lost`, salvataggio rifiutato senza motivo, riuscito con
      motivo), API (422 senza motivo su `closed lost`, motivo salvato e restituito, motivo scartato
      con un altro stato, campo presente nella documentazione OpenAPI).

## Rischi

- **Cambio di contratto dell'API.** Se `lost_reason` diventa obbligatorio con `closed lost`, la
  skill Claude commerciale (fuori da questo repo) che oggi chiude con il solo `status` riceverà
  422 finché non viene aggiornata. Mitigazione: segnalarlo nel ticket e nella PR; la scelta finale
  è nelle domande per il reviewer.
- **`dependsOn` non è mai stato usato dentro i `Tab` di una risorsa.** Nel repo c'è in
  `app/Nova/User.php:119` e in due Action (`ExportProductsToPdf`, `MoveStoriesFromEpic`), ma i
  campi della Quote stanno dentro i `Tab` nativi di Nova 5 (`Laravel\Nova\Tabs\Tab`). Va verificato a mano in Nova che il campo
  compaia e scompaia cambiando stato (un testo rimasto nascosto lo azzera comunque l'hook del
  model). La Select «Status» e il nuovo campo stanno fuori da `NovaTabTranslatable`, quindi
  `->rules()` si può usare normalmente (il problema di `strpos()` visto in oc:8734 riguarda solo i
  campi dentro `NovaTabTranslatable`).

## Domande aperte per il reviewer

1. **Dove si scrive il motivo in Nova.** Proposta: nel form, legato alla Select «Status»
   (`dependsOn`). L'alternativa è un'Action «Chiudi come persa» come in Forestas: impone di
   togliere «Chiuso Perso» dalla Select, e crea due modi diversi di cambiare stato nella stessa
   schermata. Consiglio: form.
2. **Obbligatorio anche via API?** Proposta: sì, 422 se `status: "closed lost"` arriva senza
   `lost_reason` — è un cambio di contratto per la skill commerciale. L'alternativa (facoltativo)
   non rompe nulla, ma Nova e API si comporterebbero diversamente e le trattative chiuse dalla skill
   potrebbero restare senza motivo. Da considerare: se la skill non è aggiornata, l'agente che
   riceve il 422 potrebbe riprovare scrivendo un motivo inventato invece di chiederlo all'utente, e
   quel dato non si potrebbe più correggere perché la trattativa è chiusa. Consiglio: obbligatorio,
   aggiornando la skill perché chieda il motivo all'utente.
3. **Kanban `Sales`.** Trascinando una card in «Chiuso Perso» lo stato si salva senza motivo
   (`nova-components/kanban-card/src/Http/Controllers/KanbanController.php:425-435`, nessun
   controllo di policy), e dopo il motivo non si può più aggiungere. Il ticket non tocca il Kanban:
   va aperto un ticket a parte (es. rifiutare il drop in «Chiuso Perso»)?

## Out of scope

- Il Kanban `Sales`: il drop in «Chiuso Perso» resta com'è (vedi domanda 3 per il reviewer).
- Un elenco di motivi predefiniti: il ticket chiede un testo libero.
- Il motivo nel PDF del preventivo, in mail o in report.
- La modifica del motivo dopo la chiusura: la trattativa chiusa non è modificabile.
- Il recupero del motivo per le trattative già perse (10 su 123 nel DB locale): restano senza.

## Moduli toccati

Tutto nel repo principale (`orchestrator`), nessun submodule.

- `database/migrations/<timestamp>_add_lost_reason_to_quotes_table.php` (nuovo)
- `app/Models/Quote.php` — `$fillable` e hook `saving`
- `app/Nova/Quote.php` — Textarea nel form (con `dependsOn` sullo stato) e nel dettaglio
- `app/Http/Requests/Api/QuoteApiRequest.php` — regola di `lost_reason`
- `app/Http/Controllers/Api/QuoteController.php` — `formatQuote()` e shape `@response`
- `lang/en.json`, `lang/it.json`
- `tests/Feature/QuoteNovaResourceTest.php`, `tests/Feature/Api/QuoteApiTest.php`,
  `tests/Feature/Api/QuoteApiDocsTest.php`, più un test nuovo per l'hook del model
- `app/Nova/ArchivedQuotes.php` — nessuna modifica: eredita il campo da `Quote`
- `docs/knowledge/quote-api-e-pdf.md` — il nuovo campo nell'API e in Nova
