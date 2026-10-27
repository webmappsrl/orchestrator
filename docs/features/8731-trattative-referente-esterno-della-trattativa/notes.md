> Ticket: oc:8731

# Notes — Trattative: referente esterno della trattativa

## Deviazioni dal piano

Nessuna sul comportamento. Sulla forma: task 0 (rebase invece di merge), task 2 (posizione del
test) e task 4 (descrizione nel body API).

## Divergenze dal piano, task per task

### Task 0 rebase invece di merge

Il piano prevedeva `git merge origin/develop`. Su richiesta della dev si è fatto
`git rebase origin/develop`: il commit dell'overview ha un hash nuovo (`a1068da`), quindi il push
sul branch della PR #271 richiede `--force-with-lease`.

### Task 2 posizione del test

Il piano metteva il test della Rule in `tests/Unit/`. Le altre Rule del repo hanno i test in
`tests/Unit/Rules/`, quindi il file è `tests/Unit/Rules/CustomerContactsRuleTest.php`.

### Task 4 descrizione nel body API

Il piano prevedeva una riga nel docblock di `store()` e `update()`. Il controller descrive però i
campi del body con `#[BodyParameter(..., description: ...)]` e una costante (`RICH_TEXT_DESCRIPTION`):
per coerenza `customer_contacts` usa lo stesso schema (`CUSTOMER_CONTACTS_DESCRIPTION`), e
`QuoteApiDocsTest` verifica che la descrizione del body citi il formato `<email>`.

## Bug trovati

Trovati dalla review formale (`wm-review-ticket`) prima del commit, e corretti:

- **L'help di Nova perdeva `<email>`**: Nova rende l'help con `v-html`, e il browser leggeva
  `<email>` come tag sconosciuto. L'utente leggeva «nel formato Nome Cognome , separate da virgola».
  Ora il testo passa da `e()`, e un test verifica `&lt;email&gt;`.
- **Un a capo dentro un nome fra virgolette passava**: il ramo fra virgolette della regex non
  escludeva `\r` e `\n`. Ora li esclude, e c'è un caso di test.

## Decisioni

- Trascrizione della call del 30/09/2026 non leggibile: il Doc collegato al tag della call non si
  importa in NotebookLM, e i tre scrum del 30/09 non parlano del campo. Si è proseguito solo con il
  ticket, per scelta della dev.
- Tag associato al ticket: `orchestrator` (id 590), caratteristica repository.
- Ambiente locale: con il compose di `develop` (oc:8844) `docker compose up -d` fallisce, perché
  `postgis/postgis:17-3.5` non ha una build arm64. Test e migration sono girati sui container già
  esistenti (`php81_orchestrator` con PHP 8.4.25, `postgres_orchestrator` con PostgreSQL 14.9).
- Review dell'overview chiusa allo scrum del 26/10/2026 08:27: Alessandro Peci approva il nome
  `customer_contacts` e la posizione in Nova subito sotto il Cliente — «Comunque va il nome va bene
  e la posizione sì, si trovavo scritta lì sul slackma che è andata bene sotto cliente». Tolta la
  sezione «Domande aperte per il reviewer» dall'overview.

- Modifiche dopo l'approvazione del piano, dalla review formale:
  - `max:2000` in API e Nova, come `lost_reason`, e quantificatori possessivi nella regex. Senza,
    circa 600 KB di spazi e tab alternati tenevano occupata una richiesta per 42 secondi;
  - tolto il controllo `is_string` della Rule e la sua chiave di traduzione, perché con la regola
    `string` davanti un array dava due errori per la stessa causa. La Rule ora ignora i valori che
    non sono stringhe, e `string` è anche nelle regole Nova;
  - un'unica dicitura del formato, «Nome Cognome <email>», in help ed errori;
  - test della Rule che confrontano il messaggio esatto, non solo il numero di errori;
  - nella descrizione API e nella pagina di conoscenza, l'eccezione del middleware `TrimStrings`.

## Follow-up

- **Verifica a mano in Nova (task 5.4), da fare prima del rilascio:** il campo sotto il Cliente;
  l'help con `<email>` visibile; un valore sbagliato che mostra l'errore in italiano; un valore
  valido visibile nel dettaglio senza «Show content».
- Non corretti, scelti consapevolmente in review:
  - spazi Unicode incollati da un client mail (NBSP prima di `<`) fanno rifiutare un contatto
    valido;
  - nell'API i messaggi usano il nome tecnico «customer contacts», come già per `lost_reason`;
  - il parser della stringa è privato nella Rule: chi userà il campo per inviare mail dovrà
    esporlo, non riscriverlo.
