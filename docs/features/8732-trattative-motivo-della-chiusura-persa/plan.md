> Ticket: oc:8732

# Piano — Trattative: motivo della chiusura persa

Riferimento: [overview.md](overview.md). Tutto nel repo principale `orchestrator`, branch
`feature/oc-8732-trattative-motivo-della-chiusura-persa`, PR #262 verso `develop`.

I test girano nel container, sul DB `orchestrator_test` (vedi `docs/howto/eseguire-i-test.md`):

```bash
docker exec php81_orchestrator php artisan test --filter=<NomeTest>
```

I commit sono istruzioni per la dev, non azioni automatiche: nessun `git commit` senza il suo sì.

---

## Task 1 — Colonna, model e regola «motivo solo se persa»

**File:**
- `database/migrations/2026_10_12_120000_add_lost_reason_to_quotes_table.php` (nuovo)
- `app/Models/Quote.php`
- `tests/Feature/QuoteLostReasonTest.php` (nuovo)

1. Migration con classe anonima, sul modello di `2023_05_02_145242_add_notes_to_quotes_table.php`:
   `$table->text('lost_reason')->nullable()` in `up()`, `dropColumn('lost_reason')` in `down()`.
2. `lost_reason` in `$fillable` di `Quote`. Non va fra i campi traducibili.
3. In `booted()` un **secondo** listener `static::saving(...)`, separato da quello del template (che
   esce subito se la Quote non è un template, `Quote.php:232-235`):

   ```php
   static::saving(function (Quote $quote) {
       if ($quote->status !== QuoteStatus::Closed_Lost->value) {
           $quote->lost_reason = null;
       }
   });
   ```

4. Test (`DatabaseTransactions`, come `QuoteNovaResourceTest`):
   - una Quote salvata in `closed lost` con motivo lo conserva;
   - una Quote aperta salvata con un motivo lo perde (null);
   - una Quote persa con motivo, portata a `presented` con `save()` (il caso del Kanban), perde il
     motivo.
5. Migration sul DB di test e test:

   ```bash
   docker exec php81_orchestrator bash -c "DB_DATABASE=orchestrator_test php artisan migrate"
   docker exec php81_orchestrator php artisan migrate
   docker exec php81_orchestrator php artisan test --filter=QuoteLostReasonTest
   ```

Commit: `feat(oc:8732): colonna lost_reason e azzeramento del motivo sulle trattative non perse`

## Task 2 — Campo in Nova

**File:** `app/Nova/Quote.php`, `tests/Feature/QuoteNovaResourceTest.php`

1. Subito dopo la Select «Status» (fuori da `NovaTabTranslatable`), una Textarea solo per i form:

   ```php
   Textarea::make(__('Lost reason'), 'lost_reason')
       ->onlyOnForms()
       ->hide()
       ->rules('nullable', 'max:2000')
       ->dependsOn(['status'], function (Textarea $field, NovaRequest $request, FormData $formData) {
           if ($formData->status === QuoteStatus::Closed_Lost->value) {
               $field->show()->rules('required', 'max:2000');
           }
       }),
   ```

   Con «Chiuso Perso» già selezionato all'apertura del form (creazione con quello stato) il
   campo deve risultare visibile: verificarlo in Nova, perché `dependsOn` gira anche al primo
   caricamento.
2. Per il dettaglio, una seconda Textarea in sola lettura:
   `->onlyOnDetail()->alwaysShow()->canSee(fn () => $this->resource->status === QuoteStatus::Closed_Lost->value && filled($this->resource->lost_reason))`.
   `ArchivedQuotes` la eredita senza modifiche.
3. Test in `QuoteNovaResourceTest` (stesso stile: `flattenFields()` sui campi resi):
   - la Textarea dei form esiste, ha `dependsOn` su `status` ed è nascosta di default;
   - con `status = closed lost` nei dati del form il campo è visibile e `required`;
   - il campo di dettaglio si vede solo con trattativa persa e motivo compilato, mai nell'index.
   - Se il repo ha già un modo di chiamare `nova-api` nei test, aggiungere anche: salvataggio
     rifiutato senza motivo con `closed lost`, riuscito con motivo.

Commit: `feat(oc:8732): motivo della chiusura persa nel form e nel dettaglio Nova`

## Task 3 — API

**File:** `app/Http/Requests/Api/QuoteApiRequest.php`, `app/Http/Controllers/Api/QuoteController.php`,
`tests/Feature/Api/QuoteApiTest.php`, `tests/Feature/Api/QuoteApiDocsTest.php`

1. Regola in `QuoteApiRequest::rules()`:

   ```php
   'lost_reason' => [
       Rule::requiredIf(fn () => $this->input('status') === QuoteStatus::Closed_Lost->value),
       'nullable', 'string', 'max:2000',
   ],
   ```

   Si usa l'enum, non la stringa `'closed lost'` scritta a mano.
2. `'lost_reason' => $quote->lost_reason` in `formatQuote()`, dopo `notes`.
3. `lost_reason: string|null` in tutti gli shape `@response` che descrivono la Quote: oggi sono
   le righe 43 (due shape: elenco semplice e paginato), 107, 194, 219, 262, 278, 294 e 310 del
   controller. Ricontrollare con
   `grep -n "notes: string|null" app/Http/Controllers/Api/QuoteController.php`.
4. Test in `QuoteApiTest`:
   - `PATCH` con `status: "closed lost"` senza `lost_reason` → 422 con errore su `lost_reason`,
     trattativa invariata;
   - `PATCH` con stato e motivo → 200, motivo salvato e restituito;
   - `POST` con `status: "closed lost"` senza motivo → 422;
   - `PATCH` con `lost_reason` e `status: "presented"` → 200, `lost_reason` null;
   - `lost_reason` più lungo di 2000 caratteri → 422.
5. Test in `QuoteApiDocsTest`: `lost_reason` nello schema della risposta di `GET /api/quotes/{quote}`
   e fra i campi del body di `PATCH /api/quotes/{quote}`.

```bash
docker exec php81_orchestrator php artisan test --filter=QuoteApi
```

Commit: `feat(oc:8732): lost_reason nell'API delle trattative, obbligatorio con closed lost`

## Task 4 — Traduzioni

**File:** `lang/en.json`, `lang/it.json`

- `"Lost reason": "Lost reason"` / `"Lost reason": "Motivo della chiusura persa"`.
- Il messaggio `required` usa il nome del campo di Nova: verificare in Nova, in italiano, che si
  legga «Motivo della chiusura persa» e non `lost_reason`. Nell'API resta il messaggio standard.

Commit insieme al task 2.

## Task 5 — Verifica completa e verifica manuale

1. Suite completa:

   ```bash
   docker exec php81_orchestrator php artisan test
   ```

2. Verifica manuale in Nova (richiesta dal reviewer), in italiano e in inglese:
   - modifica di una trattativa aperta: scegliere «Chiuso Perso» → compare la Textarea; salvare
     vuota → errore sul campo; scrivere il motivo e salvare → trattativa in «Preventivi
     archiviati», motivo nel dettaglio;
   - **prova del reviewer:** scegliere «Chiuso Perso», scrivere un testo, tornare a un altro stato
     (la Textarea sparisce) e salvare → `lost_reason` null nel DB:

     ```bash
     docker exec php81_orchestrator php artisan tinker --execute="echo json_encode(App\Models\Quote::find(<id>)->only('status','lost_reason'));"
     ```

   - creazione di una trattativa direttamente in «Chiuso Perso»: il campo compare ed è obbligatorio;
   - una trattativa persa prima della feature: nel dettaglio il campo non compare.

## Task 6 — Documentazione e note

- `docs/knowledge/quote-api-e-pdf.md`: il campo `lost_reason`, la regola nel model, l'obbligo
  nell'API e il blocco del drop in «Chiuso Perso» sul Kanban (task 7).
- `docs/features/8732-trattative-motivo-della-chiusura-persa/notes.md`: deviazioni, decisioni, e
  nei follow-up l'aggiornamento della skill commerciale, a cura di chi la gestisce.
- Segnalare nella PR #262 il cambio di contratto dell'API (422 su `closed lost` senza motivo).

Commit: `docs(oc:8732): pagina di conoscenza delle quote, piano e note`

## Task 7 — Kanban `Sales`: drop in «Chiuso Perso» rifiutato

> ⚠️ L'implementazione ha deviato da questo task: [notes.md](notes.md#deviazioni-dal-piano)

Aggiunto dopo la risposta 3 della review (12/10/2026), che porta il Kanban dentro questo ticket.

**File:** `nova-components/kanban-card/src/KanbanCard.php`,
`nova-components/kanban-card/src/Http/Controllers/KanbanController.php`,
`nova-components/kanban-card/dist/js/card.js`, `app/Nova/Dashboards/Sales.php`, `lang/*.json`,
`tests/Feature/KanbanCardDeniedDropTest.php` (nuovo)

1. `KanbanCard::deniedDropStatuses(array $statusToMessage)`: stati in cui il drop è rifiutato, con
   la chiave del messaggio. Passa nel config dell'API (`getApiConfig()`), come
   `deniedUpdateRoles`.
2. `KanbanController::updateStatus()`: se il nuovo stato è fra quelli rifiutati risponde 422 con
   `error` = messaggio tradotto, prima di toccare il model.
3. `Sales.php`: `->deniedDropStatuses([QuoteStatus::Closed_Lost->value => '…'])`. Il Kanban delle
   storie (`app/Nova/Dashboards/Kanban.php`) non cambia.
4. `card.js`, in `onDrop()`: in caso di risposta non ok legge `error` dal JSON e lo mostra al posto
   di «Error updating status.»; la card torna nella colonna di partenza come oggi. Poi
   `node --check nova-components/kanban-card/dist/js/card.js`.
5. Traduzione del messaggio in `lang/en.json` e `lang/it.json`.
6. Test (stile `KanbanCardPriorityReorderTest`, controller chiamato direttamente): drop in
   `closed lost` con il blocco → 422 con il messaggio e stato invariato; drop in un altro stato →
   salvato; config senza `deniedDropStatuses` (Kanban delle storie) → nessun blocco.
7. Prova a mano: trascinare una card in «Chiuso Perso» sul Kanban `Sales`.

Commit: `feat(oc:8732): il Kanban Sales rifiuta il drop in Chiuso Perso`
