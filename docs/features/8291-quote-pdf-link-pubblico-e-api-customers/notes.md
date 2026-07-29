> Ticket: oc:8291

# Notes — API Quotes: PDF via API con link pubblico firmato ed endpoint customers

## Deviazioni dal piano

- `QuotePdfService::stream()` ha guadagnato un terzo parametro `bool $persist = true`, mai previsto nell'interfaccia originale del Task 1 (`stream(Quote $quote, string $lang): Response`). Introdotto durante la review finale sull'intero branch per evitare che la rotta pubblica anonima scrivesse sul DB.
- `Quote::clearEmptyAdditionalServicesTranslations()` ha cambiato firma da nessun parametro a `bool $persist = true`. Introdotto durante il fix della review formale (`wm-review-ticket`) per correggere una regressione introdotta dal punto precedente: il primo fix rendeva l'intero metodo condizionale a `$persist`, saltando anche la normalizzazione in-memory delle traduzioni — con `additional_services` vuoto per una lingua, il PDF pubblico omette silenziosamente i servizi aggiuntivi invece di ripiegare su una lingua con contenuto. Corretto separando "normalizza sempre in memoria" da "salva solo se `$persist`".
- `Api\QuoteController::pdf()` chiama ora `stream($quote, $lang, persist: false)` invece del default `true` — fix della stessa review formale: l'endpoint bearer, pensato per chiamate ripetute/automatizzate da skill, poteva cascare silenziosamente `template=false` su preventivi fratelli dello stesso cliente ad ogni download.
- Regex di backfill VAT: aggiunto un confine finale (`\b`) sul pattern primario, non presente nel codice del piano — evita che un run di 12+ cifre senza separatore produca un VAT troncato/errato.
- Rotta pubblica firmata (`routes/web.php`): aggiunto `->missing(fn () => abort(403))`, non previsto nel piano — senza di esso un id inesistente restituiva 404 mentre una firma invalida su un id esistente restituiva 403, permettendo l'enumerazione degli id validi a un chiamante anonimo.
- `Api\QuoteController::index()`: aggiunto un fallback `orderByDesc('id')` quando `sort` non è specificato, non previsto nel piano — senza ordinamento esplicito la paginazione produceva ordine non deterministico su PostgreSQL (rischio di righe duplicate/saltate tra pagine).
- Il docblock finale di `AuthController::login()` è più scarno di quanto specificato nel Task 11 del piano: la versione completa (nessuna scadenza, nessuna ability, nessun endpoint di revoca) è stata deliberatamente ridotta perché pubblicata su `/docs/api` (pagina pubblica) — pubblicarla avrebbe offerto ricognizione gratuita a un attaccante. Il dettaglio operativo completo è stato spostato in `CLAUDE.md`.
- Dopo il completamento del piano, l'utente ha richiesto due estensioni non pianificate: (a) esecuzione reale delle migration pendenti sul DB principale e del comando di backfill VAT (`--apply`); (b) due campi Nova (`VAT Number`, `Address`) editabili con traduzioni it/en — quest'ultimo era stato esplicitamente scartato come "out of scope" nell'overview originale (endpoint pensato API-only), poi richiesto a posteriori dall'utente e implementato.

## Bug trovati

- Test manuale post-commit su `/docs/api`: `sort`, `page` e la forma array di `status[]` non comparivano tra i parametri documentati di `GET /api/quotes`, pur funzionando a livello di codice (test verdi). Causa: Scramble infra i query param solo da chiamate `$request->method('key')` con metodo riconosciuto in posizione AST diretta — `sort` è letto solo dentro un confronto (`===`), `page` solo via `$request->filled()` (metodo non mappato) ed è comunque consumato internamente da `paginate()`, mai dal nostro codice. Corretto con attributi `#[QueryParameter(...)]` espliciti su `Api\QuoteController::index()` per tutti e 5 i parametri, incluso `status` documentato come `string|array<string>`.

- Review finale sull'intero branch (SDD): rotta pubblica senza rate limiting dedicato; rotta pubblica priva di guardia su id inesistente (enumerazione); `expires_in_days` non validato; filename non sanitizzato; regex VAT senza confine sul run di cifre; `Storage::put()` non verificato prima di `--apply`; paginazione senza `ORDER BY` deterministico. Tutti corretti prima della fine di quella fase.
- Review formale (`wm-review-ticket`, dopo la review SDD): il fix precedente per il DB-write sulla rotta pubblica aveva introdotto una regressione sui contenuti del PDF (vedi Deviazioni sopra) — corretto. Trovato anche che l'endpoint bearer aveva lo stesso problema di cascata `template=false` della rotta pubblica, non coperto dal fix precedente — corretto.

## Decisioni

- Decisioni di design (link pubblico via Laravel signed URL vs token UUID, `contact_emails` da regex Nova, backfill VAT con dry-run di default, punti 6/8 del ticket fuori scope) sono documentate in `overview.md`.
- Rotta web `/quote/{id}` esistente senza alcuna autorizzazione: gap pre-esistente confermato in Fase: challenge, lasciato esplicitamente fuori scope — vedi Follow-up.
- Nessuna colonna `company_name` creata: confermato durante l'esecuzione che `full_name` è già popolato per 124/136 customer con il nome legale, riusato come alias di sola lettura nella response API.
- Backfill VAT eseguito realmente sul DB principale su richiesta esplicita dell'utente dopo il completamento del piano: 39/52 customer con `heading` popolato e `vat` vuoto hanno prodotto un match affidabile (VAT/CF numerico a 11 cifre); i restanti 13 non hanno prodotto match (verosimilmente C.F. alfanumerico di persona fisica, correttamente esclusi dal comando). Report salvato in `storage/app/customer-vat-backfill/2026-07-28_154410.json`.
- Tutte le migration pendenti sul DB principale (13 in totale, non solo quella di questo ticket) sono state applicate su richiesta dell'utente — le altre 12 non sono legate a oc:8291.

## Follow-up

- Rotta web `/quote/{id}` senza autorizzazione — aprire un ticket dedicato (fuori scope per oc:8291, gap pre-esistente).
- Pattern di autorizzazione per-ruolo duplicato ora in 4 controller (`Product`, `RecurringProduct`, `Tag`, `Customer`) — refactor verso una Policy/Gate condivisa se la lista ruoli cambierà (debito già segnalato in oc:8286).
- Aliquota IVA `0.22` hardcoded in un terzo punto (`Api/QuoteController.php`, oltre alle due già in `app/Nova/Quote.php`) — da estrarre in un metodo/costante condivisa su `Quote`.
- `per_page` non validato in `GET /api/quotes` (nessun range esplicito min/max) — comportamento Eloquent di fallback verificato benigno, ma andrebbe irrigidito con una regola di validazione esplicita.
- 13 customer con `heading` popolato ma nessun match VAT affidabile dal backfill (verosimilmente C.F. di persona fisica) — valutazione manuale caso per caso.
- `address` resta `null` per tutti i customer — popolamento manuale, nessun backfill automatico previsto per questo campo (deciso in Fase: reverse-interaction).
- Punto 6 (invio email) e punto 8 (abilities granulari Sanctum) del ticket originale restano esplicitamente fuori scope — vedi `overview.md`.
