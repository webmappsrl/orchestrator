> Ticket: oc:8412

# Piano — Validazione e helper per campi telefono/cellulare Customer

Nota: `superpowers:writing-plans` non è installata in questo ambiente — piano scritto manualmente seguendo le stesse convenzioni (path, header, commit convention).

## Task 1 — Nessuna dipendenza composer

La validazione è implementata internamente al progetto: `composer.json`/`composer.lock` restano identici a `develop`, **nessun file da committare per questo task**.

## Task 2 — Helper di normalizzazione e validazione in `app/Nova/Customer.php`

Aggiungere tre metodi privati/pubblici alla classe `Customer` (stesso pattern di estrazione già in uso per `statusField()`, riga 320):

```php
private function normalizePhoneFieldValue(?string $value): string
{
    if ($value === null) {
        return '';
    }
    $stripped = preg_replace('/[\x{200B}\x{200C}\x{200D}\x{FEFF}]/u', '', $value) ?? $value;
    return trim(preg_replace('/\s+/', ' ', $stripped) ?? $stripped);
}

private function isValidPhoneFragment(string $fragment): bool
{
    if (!preg_match('/^[\d+\s\-.()]+$/', $fragment)) {
        return false;
    }

    $digits = preg_replace('/[^\d+]/', '', $fragment) ?? '';
    if ($digits === '') {
        return false;
    }

    if ($digits[0] === '+') {
        return (bool) preg_match('/^\+\d{8,15}$/', $digits);
    }

    return (bool) preg_match('/^\d{6,11}$/', $digits);
}

public function phoneValidationError(?string $value, ?string $existingValue): ?string
{
    if (blank($value)) {
        return null;
    }

    $normalized = $this->normalizePhoneFieldValue($value);
    if ($normalized === $this->normalizePhoneFieldValue($existingValue)) {
        return null;
    }

    $fragments = collect(explode(',', $normalized))
        ->map(fn ($fragment) => trim($fragment))
        ->filter(fn ($fragment) => $fragment !== '');

    foreach ($fragments as $fragment) {
        if (!$this->isValidPhoneFragment($fragment)) {
            return __('One or more numbers are not in a valid phone format. :example', [
                'example' => __('Example: +39 328 5360803'),
            ]);
        }
    }

    return null;
}
```

**Decisioni di design:**
- `phoneValidationError` è `public` (non `protected`) apposta per essere testabile direttamente instanziando `new \App\Nova\Customer(new \App\Models\Customer())` senza reflection.
- Il confronto "changed" avviene su valori **normalizzati** (trim + collasso spazi + strip caratteri invisibili), non sulla stringa raw.
- Frammenti vuoti dopo lo split (virgola finale, doppia virgola) vengono scartati silenziosamente — nessun errore.
- `isValidPhoneFragment()` (nativo, nessuna dipendenza esterna): un prefisso `+` esplicito richiede 8-15 cifre totali (limiti E.164 generali, non un vero piano di numerazione); senza prefisso, assume IT e richiede 6-11 cifre. Un carattere non ammesso (lettera, simbolo) nel frammento fa fallire subito la validazione, prima ancora del conteggio cifre.
- Nessuna distinzione mobile/fisso: stessa funzione per entrambi i campi.

File: `app/Nova/Customer.php`

## Task 3 — Sostituire i field `phone` e `mobile_phone`

Sostituire (righe 173–182 attuali):

```php
Text::make(__('Phone'), 'phone')
    ->nullable()
    ->rules('nullable', 'regex:/^[\d\s\p{Z}+,\-\.()\x{200B}\x{200C}\x{200D}\x{FEFF}]+$/u', 'max:255')
    ->help(__('One or more numbers separated by comma; spaces and common separators allowed.'))
    ->onlyOnForms(),
Text::make(__('Mobile phone'), 'mobile_phone')
    ->nullable()
    ->rules('nullable', 'regex:/^[\d\s\p{Z}+,\-\.()\x{200B}\x{200C}\x{200D}\x{FEFF}]+$/u', 'max:255')
    ->help(__('One or more numbers separated by comma; spaces and common separators allowed.'))
    ->onlyOnForms(),
```

con:

```php
Text::make(__('Phone'), 'phone')
    ->nullable()
    ->rules('nullable', 'max:255', function ($attribute, $value, $fail) {
        if ($error = $this->phoneValidationError($value, $this->phone)) {
            $fail($error);
        }
    })
    ->help(__('One or more phone numbers, separated by comma. :example', [
        'example' => __('Example: +39 328 5360803'),
    ]))
    ->onlyOnForms(),
Text::make(__('Mobile phone'), 'mobile_phone')
    ->nullable()
    ->rules('nullable', 'max:255', function ($attribute, $value, $fail) {
        if ($error = $this->phoneValidationError($value, $this->mobile_phone)) {
            $fail($error);
        }
    })
    ->help(__('One or more phone numbers, separated by comma. :example', [
        'example' => __('Example: +39 328 5360803'),
    ]))
    ->onlyOnForms(),
```

Nota: `$this->phone` / `$this->mobile_phone` dentro la Closure di validazione leggono l'attributo corrente del model proxato dalla Nova Resource (`$this->resource`) — riflette il valore in DB al momento in cui `fields()` viene costruito, **prima** che Nova applichi i nuovi valori del form al model (stesso pattern già usato in questo file, righe 98-99, per il subtitle). Su creazione, `$this->phone`/`$this->mobile_phone` sono `null` (resource non ancora esistente) → normalizzato a stringa vuota → qualsiasi valore non vuoto inserito viene sempre validato.

File: `app/Nova/Customer.php`
Commit (Task 2+3 insieme): `fix(oc:8412): validate phone/mobile_phone with a native plausibility check instead of regex`

## Task 4 — Traduzioni

Aggiungere in `lang/en.json` (dopo la riga `"Phone": "Phone",`):

```json
"Example: +39 328 5360803": "Example: +39 328 5360803",
"One or more phone numbers, separated by comma. :example": "One or more phone numbers, separated by comma. :example",
"One or more numbers are not in a valid phone format. :example": "One or more numbers are not in a valid phone format. :example",
```

Aggiungere in `lang/it.json` (dopo la riga `"Phone": "Telefono",`):

```json
"Example: +39 328 5360803": "Esempio: +39 328 5360803",
"One or more phone numbers, separated by comma. :example": "Uno o più numeri di telefono, separati da virgola. :example",
"One or more numbers are not in a valid phone format. :example": "Uno o più numeri inseriti non sono in un formato telefonico valido. :example",
```

Non rimuovere le vecchie chiavi `"One or more numbers separated by comma (digits only)."` e `"One or more numbers separated by comma; optional + for country code."` — già orfane prima di questo ticket (nessun riferimento nel codice, verificato con grep), fuori scope la loro rimozione. Rimuovere invece `"One or more numbers separated by comma; spaces and common separators allowed."` solo se il grep post-modifica conferma zero riferimenti residui (Task 3 la rende orfana).

File: `lang/en.json`, `lang/it.json`
Commit: `fix(oc:8412): add translations for phone validation help/error text`

## Task 5 — Test

Creare `tests/Feature/CustomerPhoneValidationTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Nova\Customer as CustomerResource;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class CustomerPhoneValidationTest extends TestCase
{
    use DatabaseTransactions;

    private function resource(?Customer $customer = null): CustomerResource
    {
        return new CustomerResource($customer ?? new Customer());
    }

    public function test_valid_single_number_with_explicit_prefix_passes()
    {
        $this->assertNull($this->resource()->phoneValidationError('+39 328 5360803', null));
    }

    public function test_valid_local_number_without_prefix_defaults_to_it()
    {
        $this->assertNull($this->resource()->phoneValidationError('328 5360803', null));
    }

    public function test_valid_foreign_number_with_explicit_prefix_passes()
    {
        $this->assertNull($this->resource()->phoneValidationError('+41 79 123 45 67', null));
    }

    public function test_invalid_text_fails()
    {
        $this->assertNotNull($this->resource()->phoneValidationError('non un numero', null));
    }

    public function test_multiple_valid_numbers_separated_by_comma_pass()
    {
        $this->assertNull($this->resource()->phoneValidationError('+39 328 5360803, +39 02 1234567', null));
    }

    public function test_one_invalid_fragment_among_valid_ones_fails()
    {
        $this->assertNotNull($this->resource()->phoneValidationError('+39 328 5360803, abc', null));
    }

    public function test_trailing_comma_produces_empty_fragment_and_is_ignored()
    {
        $this->assertNull($this->resource()->phoneValidationError('+39 328 5360803,', null));
    }

    public function test_double_comma_produces_empty_fragment_and_is_ignored()
    {
        $this->assertNull($this->resource()->phoneValidationError('+39 328 5360803,,+39 02 1234567', null));
    }

    public function test_empty_value_is_always_valid()
    {
        $this->assertNull($this->resource()->phoneValidationError('', null));
        $this->assertNull($this->resource()->phoneValidationError(null, null));
    }

    public function test_unchanged_legacy_invalid_value_is_not_revalidated()
    {
        $legacy = 'non valido ma gia in db';
        $this->assertNull($this->resource()->phoneValidationError($legacy, $legacy));
    }

    public function test_whitespace_only_change_on_legacy_value_is_not_considered_changed()
    {
        $legacy = 'non valido ma gia in db';
        $withExtraSpace = 'non  valido ma gia in db';
        $this->assertNull($this->resource()->phoneValidationError($withExtraSpace, $legacy));
    }

    public function test_invisible_unicode_characters_are_ignored_in_change_comparison()
    {
        $legacy = "non valido ma gia in db";
        $withZeroWidth = "non\u{200B} valido ma gia in db";
        $this->assertNull($this->resource()->phoneValidationError($withZeroWidth, $legacy));
    }

    public function test_actually_changing_a_legacy_invalid_value_triggers_validation()
    {
        $legacy = 'non valido ma gia in db';
        $this->assertNotNull($this->resource()->phoneValidationError('ancora non valido ma diverso', $legacy));
    }

    public function test_changing_legacy_invalid_value_to_a_valid_one_passes()
    {
        $legacy = 'non valido ma gia in db';
        $this->assertNull($this->resource()->phoneValidationError('+39 328 5360803', $legacy));
    }
}
```

File: `tests/Feature/CustomerPhoneValidationTest.php`
Commit: `fix(oc:8412): add tests for phone validation and legacy-value skip logic`

## Task 6 — Verifica manuale

- `docker exec php81_orchestrator php artisan test --filter=CustomerPhoneValidationTest`
- Verifica manuale nel form Nova (`/resources/customers/new` e `/resources/customers/{id}/edit`): help text visibile con esempio, errore su input invalido, salvataggio riuscito su numero valido, salvataggio di un Customer con telefono legacy invalido su un altro campo (es. nome) senza toccare il telefono → deve passare.
