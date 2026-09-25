<?php

namespace App\Rules;

use App\Services\Quotes\QuoteRichText;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * `additional_services` è una mappa {descrizione: prezzo}. Il prezzo deve
 * essere leggibile sia da `number_format(str_replace(',', '.', $p))` nel PDF
 * sia da `Quote::getTotalAdditionalServicesPrice()` (oc:8631): niente
 * separatore delle migliaia, al massimo un separatore decimale.
 */
class AdditionalServicesMap implements ValidationRule
{
    private const PRICE_PATTERN = '/^-?\d+([.,]\d{1,2})?$/';

    public static function isValidPrice(mixed $price): bool
    {
        if (is_int($price)) {
            return true;
        }
        if (is_float($price)) {
            // Al massimo due decimali, come per le stringhe: il PDF arrotonda a due
            // cifre mentre il totale userebbe il valore pieno.
            return is_finite($price) && abs($price * 100 - round($price * 100)) < 1e-6;
        }
        return is_string($price) && preg_match(self::PRICE_PATTERN, $price) === 1;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_array($value) || $value === []) {
            return;
        }

        if (array_is_list($value)) {
            // Un oggetto JSON con chiavi "0", "1"… arriva come lista PHP: il
            // messaggio lo spiega invece di dire solo «non una lista».
            $fail(__('The :attribute field is not valid: :details.', [
                'attribute' => $attribute,
                'details'   => __('it must be an object {"description": price}, not a list (e.g. {"Initial setup": 1500}); if the descriptions are numbers such as "0", "1", add some text (e.g. "Voce 1")'),
            ]));
            return;
        }

        $problems = [];
        foreach ($value as $service => $price) {
            if (trim((string) $service) === '') {
                $problems[] = __('every service must have a non-empty description');
                continue;
            }
            if (! self::isValidPrice($price)) {
                $problems[] = __('the price of service ":service" is not valid (received: :value): use a number without thousands separator and at most two decimals. Valid examples: 1234.56, 1234,56, 1234', [
                    'service' => QuoteRichText::protectPlaceholders((string) $service),
                    'value'   => QuoteRichText::protectPlaceholders((string) json_encode($price, JSON_UNESCAPED_UNICODE)),
                ]);
            }
        }

        if ($problems === []) {
            return;
        }

        $details = QuoteRichText::limitDetails(array_values(array_unique($problems)), '...and :count more problems');

        $fail(__('The :attribute field is not valid: :details.', [
            'attribute' => $attribute,
            'details'   => implode('; ', $details),
        ]));
    }
}
