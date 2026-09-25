> Ticket: oc:8631

# Notes — Preventivi via API: gestire i campi di testo formattato del documento

## Deviazioni dal piano

## Divergenze dal piano, task per task

### Task 1 — verifica lingua di salvataggio Nova

Nessun bug. Backend: `QuoteNovaTiptapLocaleTest` verde al primo colpo (il testo della tab `it` finisce sotto `it` per tutti e quattro i campi). Browser (Nova locale, preventivo 6, richiesta intercettata e bloccata prima dell'invio, nessun salvataggio): il testo scritto nella tab IT viaggia come `translations_payment_plan_it`. Le "traduzioni `de`" del censimento erano chiavi con valore `null`. Nessuna modifica a `app/Nova/Quote.php`; il test resta come non-regressione. Per la verifica è stato necessario `php artisan migrate` sul DB locale (tabella `tasks` mancante dopo il riallineamento su develop), autorizzato dal dev.

### Task 6 — documentazione OpenAPI

- `@response 201 array{...}` su `QuoteController::store` veniva documentato da Scramble come `{"type":"integer","const":201}` (difetto preesistente, stesso pattern in `pdfLink` e in `CustomerController`, `StoryController`, `TagController`). Su `store` sostituito con `@status 201` + `@response array{...}`: ora lo schema ha la forma giusta, ma Scramble lo documenta sotto `200` anche se l'endpoint risponde `201`. Gli altri quattro endpoint non sono stati toccati (fuori scope).
- Il body di `store`/`update` è un `$ref` a `components/schemas/QuoteApiRequest`: il test del piano non lo risolveva, corretto.

### Task 7 — comando `quotes:check-rich-text`

- Il test del piano usava `expectsOutputToContain` su più frammenti della stessa riga di tabella, ma una riga soddisfa una sola aspettativa: riscritto con `Artisan::call()` + `Artisan::output()`, letto una volta sola perché `fetch()` svuota il buffer.
- Esito sul DB locale (151 preventivi, fino al 162): «Nessun preventivo verrebbe rifiutato.»

### Review finale — correzioni di sicurezza

La review finale del branch ha trovato modi per aggirare la regola HTML, verificati con DomPDF e con il comportamento standard dei browser. Corretti in `RichTextHtmlInspector`, con un test che prima falliva per ciascuno:

- **`style` con `url(` nascosto** da commenti CSS (`url/**/(`, `u/**/rl(`), escape (`u\72l(`) o `image-set(`: DomPDF toglie i commenti prima di leggere lo style e con `enable_remote` avrebbe scaricato l'URL dal server (SSRF, anche dalla rotta pubblica). Ora i commenti vengono tolti prima del controllo e backslash, commenti non chiusi e `image-set(` sono rifiutati.
- **`javascript:` con tab, a capo o caratteri di controllo** (`java\tscript:`, `&#9;`, `\x01javascript:`): il browser li ignora, quindi il link restava eseguibile nella vista di dettaglio di Nova. Ora gli URL vengono normalizzati come fa il browser prima del controllo.
- **Immagini esterne lette dal browser** (`\\host`, `/\host`, `/\t/host`, `srcset`, `background`): rifiutate.
- **`src` sullo stesso host**: prima era ammesso qualsiasi percorso e porta, quindi `<img src="https://<app>/quote/218">` dentro il preventivo 218 faceva richiamare a DomPDF la rotta del PDF stesso, in ricorsione. Ora sono ammessi solo percorsi sotto `/storage/` (dove Tiptap salva le immagini), sulla porta di `APP_URL`, senza `..`.
- **Messaggio per i tag innocui non supportati** (es. `<o:p>` da Word): prima diceva «può eseguire codice»; ora dice «non supportato» e suggerisce i tag ammessi. Il messaggio «pericoloso» resta per script, iframe, object e simili.

Decisione su un punto emerso dalla review: da Nova si può inserire un'immagine da URL esterno (`<img tt-mode="url" src="https://…">`), che l'API rifiuta. Non si allarga la regola, perché DomPDF scaricherebbe l'immagine, e non si tocca Nova in questo ticket: il 422 spiega cosa fare, e **la skill deve mandare nel PATCH solo i campi che modifica**.

### Dopo il primo commit — descrizioni in `/docs/api`

Su richiesta del dev, i quattro campi hanno una descrizione nel body di `store`/`update` (`#[BodyParameter(..., description: self::RICH_TEXT_DESCRIPTION)]`): formato HTML, `null`/`""` svuotano, limite di 50.000 caratteri, cosa viene rifiutato e l'indicazione di mandare solo i campi modificati. Il piano la prevedeva solo come ripiego: i campi comparivano già, ma senza nessuna regola. Elenca le categorie rifiutate e non il funzionamento interno del controllo, perché la pagina è pubblica. Test: `test_quotes_store_and_update_describe_the_rich_text_rules_in_the_body`.

## Bug trovati

- `@response 201 array{...}` produce uno schema OpenAPI sbagliato in 5 endpoint del repo (vedi Task 6): corretto solo su `QuoteController::store`.

## Decisioni

- **Tag ambiente**: `orchestrator` già presente sul ticket; candidati trovati dalla ricerca (`Backend Cyclando`, tre tag `Documentation: …`) scartati perché estranei al lavoro. Nessun tag associato in questa fase.
- **Formato in ingresso**: solo HTML, limitato ai tag producibili dal Tiptap di Nova (`$allButtons` in `app/Nova/Quote.php`). Niente Markdown.
- **Contenuto non ammesso**: rifiuto con 422 (campo + elementi fuori whitelist), mai ripulitura silenziosa; differenze solo di forma non devono causare rifiuto.
- **Lingue**: solo lingua di default (`it`), i quattro campi si aggiungono a `TRANSLATABLE_FIELDS` come `notes`/`additional_services`.
- **Piano di fatturazione nel PDF**: `billing_plan` oggi non è stampato da `quote-pdf.blade.php`; il dev conferma che è una mancanza (serve al cliente) e va aggiunto in questo ticket, eccezione voluta al "nessuna modifica all'impaginazione" del ticket.
- **Posizione nel PDF**: `billing_plan` subito dopo il blocco `payment_plan` (oggi dopo i totali), stessa grafica, solo se valorizzato; chiave di traduzione dedicata `"Billing plan"` in `it.json`/`en.json`. Nel preventivo di esempio del 18/11/2025 (quote 156, vecchia vista web) il Piano di pagamento stava prima dei Costi: l'eventuale spostamento lo valuta il dev a parte.
- **`additional_services` numerico**: in scope la sola validazione in ingresso (`additional_services.*` numerico, virgola decimale ammessa) con 422; il template non viene toccato.
- **Errori 422 "parlanti"** (richiesta esplicita del dev): ogni messaggio deve dire cosa è sbagliato (campo, elemento/valore rifiutato) e cosa è corretto (elenco tag/attributi ammessi, esempio di valore valido). Nessun codebase oggi personalizza i messaggi di validazione (`messages()` assente ovunque in `app/Http`).
- **Svuotamento campi**: `null` e `""` equivalenti, rimuovono la traduzione `it` (non salvano stringa vuota); la lettura restituisce sempre `null` per un campo vuoto.
- **Whitelist HTML allargata ai dati esistenti**: censimento read-only sul DB locale dei quattro campi → tag `p` 177, `br` 121, `td` 56, `span` 23, `strong` 18, `li` 15, `tr` 14, `div` 13, `ul` 4, `h3` 4, `table` 2, `tbody` 2, `ol` 1, `a` 1, `em` 1; attributi `td@colspan/rowspan` 56, `td@colwidth` 7, `p@dir` 6, `p@style` 3, `a@href/target/tt-mode` 1. Whitelist = tag Tiptap + questi elementi innocui (`style` limitato a `text-align`, `href` solo `http(s):`/`mailto:`); rifiutati `script`, `iframe`, `object`, `style`, `on*`, `javascript:`, `img` con `src` remoto. Motivo: un preventivo letto e riscritto senza modifiche deve sempre passare.
- **Challenge (2026-09-25)** — recepiti nell'overview:
  - Nova salva probabilmente ancora oggi i Tiptap sotto `de` (id 4 e 6, aprile 2026, solo `de`; `de` è l'ultima lingua di `config/tab-translatable.php`): verifica come primo task, correzione in scope se confermato; normalizzazione dei dati esistenti fuori scope.
  - Whitelist sostituita da "rifiuto di ciò che è pericoloso": `editHtml` in `$allButtons` rende Nova un campo HTML libero, le immagini Tiptap hanno `tt-mode="file"`, il censimento locale non conteneva immagini. Parser `masterminds/html5` come DomPDF. Comando read-only `quotes:check-rich-text` da lanciare in produzione prima del merge.
  - Prezzi: numero JSON o stringa `^-?\d+([.,]\d{1,2})?$`, niente separatore delle migliaia (`"1.234,56"` romperebbe `number_format` e darebbe 1.234 nel totale); `additional_services` dev'essere un oggetto, non una lista.
  - 422 raggruppati per tipo con conteggio, max 10 voci per campo; limite 50.000 caratteri per campo.
  - Ipotetici ignorati: XSS in Nova per differenze tra parser, locale dei messaggi 422 sporcata da `setLocale` nel worker, deriva docblock (coperta testando tutti gli 8 endpoint), rollback con client che già legge le chiavi.
- **Stima (2026-09-25, `wm-estimate` cieco sulla sola overview)**: Misurato 1,13h + Stimato 9,8h = Totale 10,9h, confidenza bassa (prima Rule custom del progetto, primo uso diretto di `masterminds/html5`). **Non ancora scritta su Orchestrator** per scelta del dev: da riprendere a fine lavoro.
- **Riallineamento su develop** (2026-09-25, HEAD `aae37a8`): le evidenze raccolte prima (colonne, `$translatable`/`$fillable`, Tiptap e `$allButtons`, `QuoteApiRequest::rules()`, `TRANSLATABLE_FIELDS`, blade, chiavi di traduzione, htmlpurifier non usato) sono state riverificate e restano valide; cambiano solo i numeri di riga.

## Follow-up

- **Rimasti aperti dalla review finale (minori)**: `{"0":150}` riceve il messaggio «non una lista»; un nome di servizio che contiene `:attribute` viene alterato nel messaggio 422; `quotes:check-rich-text` esce con 1 anche per i prezzi testuali già presenti, non solo per l'HTML (leggere la colonna «Campo» per distinguere).
- **Immagini da URL esterno inserite da Nova**: valutare se disattivare in Nova l'inserimento da URL (solo upload) per coerenza con l'API.

- **Prima del merge**: il dev lancia `php artisan quotes:check-rich-text` in produzione e riporta l'esito; exit 1 = allargare la regola o correggere i dati prima del rilascio.
- **Stima** (10,9h) ancora da scrivere su Orchestrator.
- **Status 201 in OpenAPI**: `pdfLink` e gli store di Customer/Story/Tag hanno ancora lo schema `{"type":"integer","const":201}`.
- `.phpunit.cache/test-results` è tracciato in git e viene modificato da ogni esecuzione dei test: non includerlo nei commit.

- **Prezzi testuali da Nova**: il KeyValue di `additional_services` in Nova accetta ancora testo, che manda in errore il PDF; e manca un modo per servizi «incluso»/«da definire» (la skill, rifiutata, metterà `0` → "0,00 €" nel PDF).

- ~~**Anomalia dati: traduzioni sotto la chiave `de`**~~ — **falso allarme, verificato in Task 1**: le chiavi `de` contano come presenti in `jsonb_object_keys` ma hanno valore `null` (es. preventivo 6: `{"de":null}`). Nessun preventivo del DB locale ha testo non vuoto sotto `de` per i quattro campi (`coalesce(campo::jsonb->>'de','') <> ''` → 0). Nova salva tutte le lingue della tab, quelle vuote come `null`: rumore innocuo, nessun ticket dedicato necessario.