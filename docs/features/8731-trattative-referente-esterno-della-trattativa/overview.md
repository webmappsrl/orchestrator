> Ticket: oc:8731

# Trattative: referente esterno della trattativa

## Cosa cambia

La trattativa (Quote) ha un nuovo campo facoltativo con la persona, o le persone, del cliente da
contattare per quella trattativa, quando non coincidono con il referente del Customer. Il valore è
una stringa nel formato `Nome Cognome <email>`, con più persone separate da virgola:

```
Mario Rossi <m.rossi@comune.pisa.it>, Anna Bianchi <a.bianchi@comune.pisa.it>
```

Il formato viene controllato al salvataggio, sia da Nova sia dall'API. Un valore valido viene salvato
così com'è scritto.

## Perché

A volte la persona del cliente da contattare per una trattativa non è il referente del cliente: in
alcune trattative non tutti devono essere coinvolti, o sono proprio persone diverse. Oggi la Quote non
ha nessun campo per queste persone (`app/Models/Quote.php:38-51`), e il Customer ha solo `email`,
testo libero senza nome e senza validazione (`app/Models/Customer.php:92-105`). La skill commerciale
ricava queste persone da mail e calendario quando crea la trattativa, e deve poterle scrivere
dall'API.

Dalla call del 30/09/2026, come riportato nel ticket: «Referente qua non si intende referente
interno»; «un campo non obbligatorio nella trattativa»; «metterei tranquillamente una stringa dove
magari facciamo proprio una validazione».

## Requisiti

- [ ] Colonna di testo nullable `customer_contacts` su `quotes`, con migration reversibile. Il
      prefisso `customer_` dice di chi sono le persone (del cliente, quindi esterne) e le distingue
      dal Proprietario interno (`user_id`); il dato resta della singola trattativa, non del
      Customer, e la documentazione API lo dice esplicitamente.
- [ ] Il campo è facoltativo: vuoto o `null` è sempre accettato.
- [ ] Validazione del formato RFC con una Rule nostra: il valore viene diviso sulle virgole **fuori
      dalle virgolette**, così un nome come `"Rossi, Mario" <m.rossi@comune.pisa.it>` (formato
      tipico delle intestazioni mail, da cui la skill ricava le persone) resta una persona sola; il
      nome è accettato sia fra virgolette sia senza. Virgolette non chiuse rendono il valore non
      valido. Una persona vuota (virgola finale, due virgole di fila) fa rifiutare il valore con un
      errore che la indica: nel database finiscono solo elenchi puliti. Spazi e a capo ai bordi di
      ogni persona sono tollerati (si può scrivere una persona per riga dopo la virgola); un a capo
      dentro la persona no. Per ogni persona la Rule controlla che
      1. il pezzo, tolti spazi e a capo ai bordi, sia **esattamente** `Nome <email>`, senza testo
         prima o dopo e senza a capo all'interno;
      2. il nome non sia vuoto;
      3. l'email sia valida, con `new Symfony\Component\Mime\Address($email)` (già disponibile
         tramite Laravel).
      `Address::create()` da solo non basta: accetta testo dopo `>`, ignora le righe successive alla
      prima e restituisce un nome vuoto senza errore (`vendor/symfony/mime/Address.php:34`).
      Una persona senza nome (`m.rossi@comune.pisa.it`), con un'email non valida
      (`Mario Rossi <m.rossi@>`) o con testo in più fa rifiutare l'intero valore con un errore che
      indica la persona sbagliata.
- [ ] La stessa validazione vale in Nova e nell'API (un'unica Rule riusata nei due punti).
- [ ] Il valore valido viene salvato così com'è scritto, senza riscriverlo. Fa eccezione il
      middleware globale `TrimStrings`, che toglie spazi e a capo all'inizio e alla fine dell'intero
      valore, come per ogni campo.
- [ ] Al massimo 2000 caratteri (`max:2000`, come `lost_reason`), in Nova e nell'API.
- [ ] API: il campo è accettato in `POST /api/quotes` e `PATCH /api/quotes/{quote}`
      (`QuoteApiRequest`, `sometimes` + `nullable`), compare in tutte le risposte di `formatQuote()`
      ed è documentato nelle annotazioni Scramble del controller.
- [ ] Nova: campo `Textarea` nella tab Main, subito sotto il Cliente, visibile nel dettaglio e nel
      form, nascosto nell'elenco delle trattative. Con `->alwaysShow()`, come `lost_reason`
      (`app/Nova/Quote.php:206`): senza, nel dettaglio il valore resta chiuso dietro «Show content».
      Posizione e nome `customer_contacts` approvati in review (scrum 26/10/2026 08:27, Alessandro
      Peci: «Comunque va il nome va bene e la posizione sì, … è andata bene sotto cliente»).
- [ ] Testi: etichetta e messaggi di errore con chiave inglese, tradotti in `lang/it.json` (lingua di
      default `it`) e presenti in `lang/en.json`.
- [ ] Test della Rule in `tests/Unit/Rules/`, test feature su API (creazione, modifica,
      azzeramento, valori rifiutati) in `QuoteApiTest` e `QuoteApiDocsTest`, e su Nova in
      `QuoteNovaResourceTest`.
- [ ] Pagina `docs/knowledge/quote-api-e-pdf.md` aggiornata con il nuovo campo del contratto API.

## Rischi

- **Validazione troppo permissiva** (verificato nel container): `Address::fromString()` non esiste
  in `symfony/mime` 7.4.9, e `Address::create()` accetta valori malformati purché contengano un
  `<email>` valido. Mitigazione: Rule nostra che controlla la forma esatta di ogni persona e usa
  `new Address()` solo per l'email (vedi Requisiti).
- **Ordine dei deploy con la skill.** `store()` e `update()` salvano solo `$request->validated()`
  (`app/Http/Controllers/Api/QuoteController.php:228,253`): se la skill manda `customer_contacts`
  prima che l'orchestrator lo conosca, la chiave viene ignorata con risposta 200 e il dato si perde
  senza errori. Mitigazione: rilasciare l'orchestrator prima di attivare il campo nella skill.

## Out of scope

- Modifiche alla skill commerciale che compila il campo: vive fuori da questo repo.
- Il campo `email` del Customer e il suo `contact_emails`: restano com'erano.
- Uso del campo nel PDF del preventivo o per inviare mail: il ticket chiede solo il dato in Nova e
  nell'API.
- L'azione Nova «Duplica» copia il campo nella trattativa nuova, come ogni altro campo
  (`app/Nova/Actions/DuplicateQuote.php:31-34`): il ticket non parla della duplicazione.

## Moduli toccati

Tutti nel repo `orchestrator` (nessun submodule).

- `database/migrations/2026_10_26_120000_add_customer_contacts_to_quotes_table.php` — nuova
- `app/Models/Quote.php` — `$fillable`
- `app/Rules/CustomerContacts.php` — nuova Rule di validazione dell'elenco di persone
- `app/Http/Requests/Api/QuoteApiRequest.php` — regola del campo
- `app/Http/Controllers/Api/QuoteController.php` — `formatQuote()` e annotazioni Scramble
- `app/Nova/Quote.php` — campo nella tab Main
- `lang/it.json`, `lang/en.json` — etichetta e messaggi
- `tests/Unit/Rules/CustomerContactsRuleTest.php` — nuovo, test della Rule
- `tests/Feature/Api/QuoteApiTest.php`, `tests/Feature/Api/QuoteApiDocsTest.php`,
  `tests/Feature/QuoteNovaResourceTest.php` — test API e Nova del nuovo campo
- `docs/knowledge/quote-api-e-pdf.md` — contratto API aggiornato
