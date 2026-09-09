> Ticket: oc:8505

# Notes — API Customers: creazione e modifica delle anagrafiche

## Deviazioni dal piano

- **Task 1**: `tests/Feature/Api/CustomerApiTest.php` era già presente nel repo (test delle `GET` di oc:8291) — il piano lo indicava erroneamente come file da "Create". L'implementer ha correttamente appeso i nuovi test invece di sovrascrivere; verificato con `git diff` che i 6 test preesistenti sono rimasti byte-per-byte invariati.
- **Task 3**: l'implementer ha eseguito per errore `git stash`/`git stash pop` durante la verifica finale, nonostante il divieto esplicito di operazioni git durante l'esecuzione. Verificato indipendentemente (reflog, stash list, diff) che non ci sia stato alcun danno: nessun commit creato, nessuno stash residuo, tutte le modifiche intatte.

## Bug trovati

Durante l'esecuzione della formal review (`wm-review-ticket`), un subagente di final-review interrotto a metà (su richiesta dell'utente, per passare a test pratici manuali) aveva lasciato un file di probe (`tests/Feature/Api/ZzReviewProbeTest.php`, poi rimosso) con l'ultimo messaggio "Found a 500. Let me dig further" — non verificato al momento dell'interruzione. Rianalizzato manualmente più tardi durante la review formale:

1. **[Critico, corretto] `phone` inviato come array → 500 (`TypeError`)**. `phoneValidationError(?string $value)` ha un type-hint stretto, ma Laravel invoca la closure di validazione anche quando la regola `'string'` precedente è già fallita (nessun `bail`), passandole il valore grezzo. Raggiungibile da **qualsiasi** utente autenticato (la validazione della FormRequest gira prima del controllo di autorizzazione nel controller — anche un ruolo che dovrebbe ricevere 403 fa crashare l'endpoint). Fix: guardia `if (!is_string($value) && $value !== null) return;` in testa alla closure. Verificato con curl reale: 422 pulito, nessun 500. Test di regressione aggiunto.

2. **[Bloccante, corretto — Finder 1 della review formale] `phone` con NBSP rifiutato via API ma accettato da Nova**. L'overview richiedeva esplicitamente il riuso di `Customer::normalizePhoneString()`, ma l'implementazione riusava solo la logica di plausibilità (`isValidPhoneFragment()`), non la normalizzazione. Un numero con spazio non-breaking (copia-incolla tipico) falliva la validazione via API pur essendo valido per Nova — stessa classe di bug già risolta una volta in oc:8412. Verificato empiricamente (per puro caso, un payload di test conteneva un NBSP invece di uno spazio normale). Fix: chiamata a `Customer::normalizePhoneString()` in testa a `phoneValidationError()`. Test di regressione aggiunto.

3. **[Bloccante, corretto — Finder 5 della review formale] `name` esplicito duplicato non verificato**. `resolveName()` eseguiva la query di unicità (`uniqueSlug()`) solo nel branch di auto-generazione da `company_name`; un `name` fornito esplicitamente nel payload veniva scritto senza alcun controllo, a differenza di Nova (`unique:customers,name` in `creationRules()`). L'overview dichiarava "stessa garanzia de facto di Nova" ma questo era vero solo per il path automatico. Fix: `Rule::unique('customers', 'name')` in `CustomerApiRequest`, con `->ignore($this->route('customer'))` su `PATCH` per non far fallire l'auto-conferma dello stesso nome. Verificato end-to-end (POST duplicato → 422, PATCH cross-customer → 422, PATCH self-update → 200).

4. **[Cleanup, corretto — Finder 2 della review formale] `contact_emails_add: null` scriveva una virgola spuria**. Il valore `null` esplicito passava la validazione (`nullable`) e finiva in `array_merge()`, producendo una colonna `email` grezza tipo `"old@example.com,"` — invisibile via API (l'accessor filtra le stringhe vuote in lettura) ma sporco per un consumer che legga la colonna direttamente (es. `AlignTagsCommand`, vedi Rischio 1 dell'overview). Fix: guardia `&& $validated['contact_emails_add'] !== null`, stessa protezione già presente sul branch gemello `contact_emails`. Test di regressione aggiunto.

Tutti e quattro corretti nella stessa sessione, verificati con `php artisan test` (494/494 sull'intera suite) e con chiamate `curl` dirette sul server locale (`php artisan serve`), poi ripuliti i dati di test creati.

## Decisioni

- **Fase: challenge** (prima dell'esecuzione): il bug OpenAPI del punto 6 del ticket era stato inizialmente sovrastimato come "sistemico" (`requestBody` sempre `null`) per un errore di verifica mio (chiave JSON `/api/quotes` invece di `/quotes` in `scramble:export`). Rifatta la verifica correttamente: Scramble documenta già bene tutti i campi via `$ref`, l'unico problema reale era il tipo di `additional_services` (array invece di oggetto). Corretto in `overview.md` prima di scrivere il piano — impatto sul Task 4, ridotto da un presunto workaround su tutta la richiesta a un override mirato su una sola proprietà.
- **Fase: estimation**: stima iniziale proposta 0.8h (misurata) + 7.3h (stimata) = 8.1h; il dev ha impostato direttamente il totale a 2.5h. Nessuna rinegoziazione durante l'esecuzione (nessun `execution:re-estimation` necessario).
- **Fase: execution — review-gate**: la review finale whole-branch automatica (dispatchata sul modello più capace) è stata interrotta su richiesta esplicita dell'utente ("lascia stare la review") per passare a test pratici manuali sul server locale (`php artisan serve` avviato/fermato più volte, dati di test sempre ripuliti a fine sessione). L'utente ha poi richiesto esplicitamente `wm-review-ticket`, eseguita con i 5 finder paralleli standard sull'intero diff (nessun PR ancora aperta — adattata per lavorare sul working tree del branch locale, non su un PR remota).
- **Adattamento subagent-driven-development**: nessun commit creato durante l'esecuzione dei 4 task del piano (vincolo esplicito wm-plan) — i "review package" per ogni task/review sono stati costruiti come diff del working tree non committato contro il commit di partenza del branch, non come range di commit `BASE..HEAD` (lo script standard della skill assume commit intermedi, qui non applicabile). Ledger completo in `.superpowers/sdd/plan/progress.md` (verrà rimosso a fine workflow, la history vive nei commit).

## Follow-up

- **`AlignTagsCommand` degrada silenziosamente con customer a email multiple** (`Customer::where('email', $user->email)->first()`, match esatto): emerso in Fase: challenge come rischio noto, esplicitamente fuori scope da questo ticket. `contact_emails_add` aumenterà strutturalmente il numero di customer con email multiple. **Da aprire come ticket dedicato dopo il commit di oc:8505**, con spiegazione completa per il developer.
- Docblock `@response` di `QuoteController::store()`/`update()` dichiara ancora `additional_services` come `array|null` (il fix Task 4 ha corretto solo lo schema del *request body*, non quello di *risposta*) — inconsistenza minore di documentazione, non bloccante.
- Nessun test dedicato per `contact_emails: []` (array vuoto esplicito) — comportamento verificato manualmente corretto (svuota la colonna), solo copertura test mancante.
- Dedup case-sensitive/senza trim in `applyContactEmails()` (`'A@x.com'` e `'a@x.com'` restano entrambe) — limite noto, basso impatto.
