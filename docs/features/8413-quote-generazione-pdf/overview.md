> Ticket: oc:8413

# [quote] generazione pdf — 500 con `additional_services` a `null`

## Cosa cambia

La generazione del PDF di un preventivo non va più in errore 500 quando
`additional_services` non è un array (`null` o stringa JSON). Il template
`resources/views/quote-pdf.blade.php` normalizza il valore in tutti e tre i
punti in cui lo legge, invece che solo nel primo. Un preventivo senza servizi
aggiuntivi produce lo stesso PDF di uno con `additional_services = []`:
nessuna sezione «Additional services», nessuna tabella costi relativa,
prodotti e servizi ricorrenti invariati.

## Perché

`resources/views/quote-pdf.blade.php` chiama `count()` su
`additional_services` alle righe 105 e 244 con la sola guardia
`!is_string($value)`. Su PHP 8.1 `count(null)` è un `TypeError`:

```
count(): Argument #1 ($value) must be of type Countable|array, null given
at resources/views/quote-pdf.blade.php:105
```

Stack: `QuoteController@show` → `QuotePdfService::stream()` → DomPDF
`loadView('quote-pdf')`. Colpisce anche le rotte API bearer e pubblica
firmata, che riusano lo stesso service.

`null` è uno stato legittimo del campo: il `KeyValue` Nova
(`app/Nova/Quote.php:216`) lasciato vuoto salva `null`, e
`QuoteApiRequest` valida esplicitamente `nullable`. Sul DB locale (dump di
produzione) **2 quote su 187** sono in questo stato (id 209 e 211, entrambe
`status = new`), 39 hanno `[]`.

Le righe 63–72 del template già normalizzano `null`/stringa → `[]`, ma solo
per il check «No items available»; poi il valore grezzo viene riletto due
volte senza quella guardia. I test PDF esistenti creano sempre quote con
`'additional_services' => []`, quindi il path non è coperto.

## Requisiti

- [ ] `QuotePdfService::stream()` su una quote con `additional_services = null` restituisce una response `application/pdf` e non lancia
- [ ] `GET /quote/{id}` su una quote con `additional_services = null` risponde 200
- [ ] Lo stesso vale se il valore è una stringa JSON o un tipo non-array qualsiasi
- [ ] Con servizi aggiuntivi popolati la sezione «Additional services» e la relativa tabella costi restano identiche a oggi
- [ ] Con `additional_services` a `null` quelle due sezioni non vengono renderizzate; prodotti, ricorrenti e tabella riepilogo restano invariati
- [ ] Nessuna scrittura aggiuntiva sul DB rispetto al comportamento attuale di `clearEmptyAdditionalServicesTranslations($persist)`
- [ ] La normalizzazione è coerente in tutti e tre i punti di lettura del template (righe 63–72, 105, 244)

## Rischi

- **Regressione silenziosa sui preventivi popolati.** Il fix tocca le tre
  condizioni che decidono se la lista servizi e la tabella costi compaiono
  nel PDF. Una guardia sbagliata non produrrebbe un errore, ma un documento
  commerciale con una voce di costo mancante — difetto peggiore del 500,
  perché invisibile. *Mitigazione:* test di non-regressione che asserisce
  sull'HTML della vista (pre-DomPDF) la presenza delle sezioni con servizi
  popolati e la loro assenza con `null`.
- **Divergenza tra i tre punti di lettura.** Una guardia `is_array()` secca
  su 105/244 avrebbe reso una stringa JSON «presente» per il check delle
  righe 63–72 e «assente» per lista e tabella. *Mitigazione:* si riusa la
  stessa normalizzazione (`is_string` → `json_decode`, non-array → `[]`) in
  tutti e tre i punti.
- **Il fix vive nel template, non nel modello.** La coercizione di tipo
  resta duplicata in una vista Blade: chiunque aggiunga un quarto punto di
  lettura può reintrodurre lo stesso bug. *Mitigazione:* accettato
  consapevolmente per tenere il diff minimo su un bugfix urgente; la
  centralizzazione su `Quote` è annotata come follow-up.
- **`additional_services` a `null` continuerà a essere prodotto** da Nova e
  dall'API dopo questo fix: si cura il sintomo in lettura, non la fonte.
  *Mitigazione:* è la scelta intenzionale — `null` è un valore valido nel
  contratto API pubblico (documentato in 8 docblock `@response` di
  `Api/QuoteController`) e `[]` non gli è semanticamente equivalente per un
  consumatore esterno.

## Out of scope

- Backfill / `UPDATE` delle quote con `additional_services` a `null` (le 2 righe esistenti restano come sono)
- Mutator o accessor sul modello `Quote` che forzi `null → []` in scrittura
- Modifiche al campo `KeyValue` in `app/Nova/Quote.php` o al default di `QuoteFactory`
- Modifiche a `Quote::clearEmptyAdditionalServicesTranslations()` e a `QuotePdfService`
- Refactor del template PDF (IVA hardcoded, logo, filename)
- Autorizzazione della rotta `/quote/{id}` (gap pre-esistente, oc:8291)

## Moduli toccati

Tutto nel repo principale (`orchestrator`). Nessun submodule coinvolto:
`wm-package` non contiene il template dei preventivi, `wm-reports` riguarda
i PDF report delle App.

| File | Modifica |
|---|---|
| `resources/views/quote-pdf.blade.php` | normalizzazione di `additional_services` alle righe 105 e 244, allineata a quella già presente alle 63–72 |
| `tests/Feature/QuotePdfServiceTest.php` | test di regressione: `stream()` con `null` e con stringa JSON; rendering della vista con servizi popolati vs `null`; test HTTP su `GET /quote/{id}` |
