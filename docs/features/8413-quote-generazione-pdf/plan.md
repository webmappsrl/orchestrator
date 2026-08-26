> Ticket: oc:8413

# Piano — fix 500 su generazione PDF preventivo con `additional_services` a `null`

Repo: `orchestrator` (principale). Nessun submodule coinvolto.

## Task 1 — Normalizzare `additional_services` nel template PDF

File: `resources/views/quote-pdf.blade.php`

1. Estrarre la normalizzazione già presente alle righe 63–72 in un unico
   blocco `@php` in cima al template, che produce una variabile riusabile:

   ```php
   $normalizeAdditionalServices = function ($value): array {
       if (is_string($value)) {
           $value = json_decode($value, true) ?? [];
       }
       return is_array($value) ? $value : [];
   };
   ```

2. Riga ~64: sostituire il blocco `$additionalServicesForCount` con una
   chiamata alla closure. Comportamento del check «No items available»
   invariato.

3. Riga ~103–105: `$additionalServices = $normalizeAdditionalServices($quote->additional_services);`
   e condizione `@if (count($additionalServices) > 0)` — via la guardia
   `!is_string(...)`.

4. Riga ~242–244: `$additionalServices = $normalizeAdditionalServices($quote->getTranslation('additional_services', App::getLocale()));`
   e condizione `@if (count($additionalServices) > 0)`.

5. Verificare che nessun altro punto del template legga
   `additional_services` grezzo (`grep -n additional_services` sul file).

Nessuna modifica a `app/Models/Quote.php`, `app/Services/QuotePdfService.php`,
`app/Nova/Quote.php`, `database/factories/QuoteFactory.php`.

## Task 2 — Test di regressione

File: `tests/Feature/QuotePdfServiceTest.php`

La factory popola sempre un array: per ottenere `null` forzare la colonna
dopo il `create` e ricaricare il modello:

```php
DB::table('quotes')->where('id', $quote->id)->update(['additional_services' => null]);
$quote->refresh();
```

Test da aggiungere:

1. `stream_non_esplode_con_additional_services_null` — `stream($quote, 'it')`
   → `Content-Type` contiene `application/pdf`.
2. `stream_non_esplode_con_additional_services_stringa_json` — stessa cosa con
   la colonna forzata a una stringa JSON (`'{"Servizio":"100"}'`).
3. `rotta_web_quote_pdf_risponde_200_con_additional_services_null` — 
   `$this->get("/quote/{$quote->id}")->assertOk()`. Copre
   `QuoteController@show` → `clearEmptyAdditionalServicesTranslations(true)`
   → `stream()`, cioè il path che ha realmente prodotto il 500.

## Task 3 — Verifica

```bash
docker exec php81_orchestrator php artisan test --filter=QuotePdfServiceTest
docker exec php81_orchestrator php artisan test --filter=QuotePdfApiTest
docker exec php81_orchestrator php artisan test --filter=Quote
```

L'ultimo comando copre anche `QuoteGetTotalAdditionalServicesPriceTest` e
`SalesQuoteColumnAggregatorTest`, che hanno già casi con `null` e non devono
regredire.

## Commit

Un solo commit: `fix(oc:8413): PDF preventivo resiliente ad additional_services null`
