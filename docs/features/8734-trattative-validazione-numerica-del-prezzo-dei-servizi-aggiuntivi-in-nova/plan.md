> Ticket: oc:8734

# Piano — Trattative: validazione numerica del prezzo dei servizi aggiuntivi in Nova

Riferimento: [overview.md](overview.md). Tutto nel repo `orchestrator`, nessun submodule.
I test girano nel container: `docker exec php81_orchestrator php artisan test --filter=<Classe>`
(DB `orchestrator_test`, mai `orchestrator`: vedi `docs/howto/eseguire-i-test.md`).

I commit indicati sono istruzioni per il dev: nessun commit, push o branch senza conferma
esplicita.

## Task 0 — Branch

```bash
git checkout develop && git pull
git checkout -b feature/oc-8734-trattative-validazione-numerica-del-prezzo-dei-servizi-aggiuntivi-in-nova
```

## Task 1 — Test Nova (prima rossi)

> ⚠️ L'implementazione ha deviato da questo task: [notes.md](notes.md#task-1--test-nova-prima-rossi)

File: `tests/Feature/QuoteNovaResourceTest.php`.

Il controllo passa dal validator della risorsa, lo stesso che il controller Nova chiama prima
del fill (`vendor/laravel/nova/src/PerformsValidation.php:36-40` creazione, `:99-103` modifica):
un errore lì significa 422 e preventivo non salvato. Si usa `NovaRequest::create()` come in
`tests/Feature/QuoteNovaTiptapLocaleTest.php:45`.

Payload: per ogni lingua di `config('tab-translatable.locales')` la chiave
`translations_additional_services_<locale>` con una **stringa JSON** (com'è inviata da
`KeyValueField.vue`), `'{}'` per le lingue non usate.

Test da scrivere:
1. **Creazione rifiutata** — `QuoteResource::validatorForCreation(NovaRequest::create('/nova-api/quotes', 'POST', $payload))`
   con `it = {"Formazione":"da definire"}`: `fails()` è true, l'errore è sulla chiave
   `translations_additional_services_it` e il messaggio contiene «Servizi aggiuntivi [it]» e
   «Formazione».
2. **Modifica rifiutata** — stessa cosa con `validatorForUpdate(NovaRequest::create('/nova-api/quotes/{id}', 'PUT', $payload), new QuoteResource($quote))`
   su un preventivo esistente.
3. **Errore sulla lingua giusta** — prezzo non valido solo in `en`: l'errore è su
   `translations_additional_services_en`, nessun errore su `_it`.
4. **Prezzo valido accettato** — `{"Formazione":"1500"}` e `{"Formazione":"1500,50"}`: nessun
   errore sulle chiavi `translations_additional_services_*`.

Se `validatorForCreation` segnala errori su altri campi obbligatori del form, i test 1-3
verificano solo le chiavi dei servizi aggiuntivi e il test 4 verifica l'assenza di errori su quelle
chiavi.

Eseguire: `docker exec php81_orchestrator php artisan test --filter=QuoteNovaResourceTest` →
i test 1-3 falliscono (oggi nessun controllo).

## Task 2 — Controllo in `afterValidation`

File: `app/Nova/Quote.php`.

- Aggiungere `use App\Rules\AdditionalServicesMap;`.
- Aggiungere il metodo statico:

```php
/**
 * I servizi aggiuntivi si validano qui e non con ->rules() sul KeyValue: NovaTabTranslatable
 * passa ogni regola a strpos() e con una ValidationRule va in TypeError, cioè 500 su tutta la
 * risorsa (oc:8734). La stringa JSON inviata da Nova viene decodificata e passata alla stessa
 * regola dell'API.
 */
protected static function afterValidation(NovaRequest $request, $validator)
{
    foreach (config('tab-translatable.locales') as $locale) {
        $key = "translations_additional_services_{$locale}";
        if (! $request->has($key)) {
            continue;
        }

        (new AdditionalServicesMap())->validate(
            __('Additional Services') . " [{$locale}]",
            json_decode((string) $request->input($key), true),
            fn (string $message) => $validator->errors()->add($key, $message),
        );
    }
}
```

- Accanto al `KeyValue::make(__('Additional Services'), 'additional_services')` un commento di una
  riga: «Validato in afterValidation(), non con ->rules(): vedi il commento lì (oc:8734)».

Eseguire `--filter=QuoteNovaResourceTest` → tutti verdi. Eseguire anche
`--filter=QuoteNovaTiptapLocaleTest`: costruisce i campi della risorsa e deve restare verde.

Commit (da proporre): `fix(oc:8734): validazione numerica del prezzo dei servizi aggiuntivi in Nova`

## Task 3 — Test PDF (prima rossi)

1. `tests/Feature/QuotePdfBillingPlanTest.php` (HTML, helper `html()` a `:14-17`): preventivo con
   servizi aggiuntivi `it` = `{"Formazione": "da definire", "Hosting": "1500"}` salvati
   direttamente sul model (dato vecchio, senza passare da Nova). Verificare che l'HTML contenga
   «da definire», non contenga «da definire €», e contenga «1.500,00 €».
2. `tests/Feature/QuotePdfServiceTest.php`: con lo stesso preventivo
   `(new QuotePdfService())->stream($quote, 'it')` non lancia eccezioni e restituisce
   `application/pdf`.

Eseguire `--filter=QuotePdfBillingPlanTest` e `--filter=QuotePdfServiceTest` → i nuovi test
falliscono con `TypeError`.

## Task 4 — Riga del prezzo tollerante nel PDF

File: `resources/views/quote-pdf.blade.php:272`.

```blade
<td class="aligned-right td">
    @if (\App\Rules\AdditionalServicesMap::isValidPrice($price))
        {{ number_format(str_replace(',', '.', $price), 2, ',', '.') }} €
    @else
        {{ is_scalar($price) ? $price : '' }}
    @endif
</td>
```

`is_scalar` evita un secondo errore se un dato vecchio contiene `null` o un array al posto del
prezzo. Il totale (`app/Models/Quote.php:152-157`) non si tocca.

Eseguire i due filtri del Task 3 → verdi. Poi tutti i test dei preventivi:
`docker exec php81_orchestrator php artisan test --filter=Quote`.

Commit (da proporre): `fix(oc:8734): PDF del preventivo tollerante ai prezzi non validi già salvati`

## Task 5 — Pagina di conoscenza

File: `docs/knowledge/quote-api-e-pdf.md`.

- `:71` — sostituire «Nova non applica la stessa regola al KeyValue» con: Nova applica la stessa
  regola in `afterValidation()` della risorsa, non in `->rules()` (motivo: `NovaTabTranslatable`
  e `strpos()`) (oc:8734).
- Rendering PDF — una riga: un prezzo che non passa `isValidPrice()` viene stampato così com'è,
  senza €, invece di mandare il PDF in 500 (oc:8734).
- `:171-175` — sotto la decisione di oc:8413, l'eccezione: qui il problema non viene nascosto,
  perché la riga mostra il prezzo come è stato scritto (oc:8734).

Il testo passa dal controllo di `wm-context-guard` in fase update-context.

## Task 6 — Verifica manuale in Nova

1. Preventivo esistente, tab `it`: «Formazione» con prezzo «da definire» → salvataggio rifiutato,
   messaggio «Il campo Servizi aggiuntivi [it] non è valido: …» sotto il campo.
2. Stesso preventivo, tab `en` con prezzo non valido mentre è aperta la tab `it` → la tab `en` si
   colora di rosso.
3. «1500» e «1500,50» → salvataggio riuscito, PDF con «1.500,00 €» / «1.500,50 €».
4. Creazione di un nuovo preventivo con prezzo non valido → rifiutata.
5. Un preventivo con «da definire» scritto direttamente nel DB locale → PDF scaricabile, riga
   «da definire».
6. Elenco e scheda dei preventivi si aprono senza errori.
