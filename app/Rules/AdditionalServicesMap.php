<?php

namespace App\Rules;

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
    private const MAX_LISTED = 10;
    private const PRICE_PATTERN = '/^-?\d+([.,]\d{1,2})?$/';

    public static function isValidPrice(mixed $price): bool
    {
        if (is_int($price)) {
            return true;
        }
        if (is_float($price)) {
            return is_finite($price);
        }
        return is_string($price) && preg_match(self::PRICE_PATTERN, $price) === 1;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_array($value) || $value === []) {
            return;
        }

        if (array_is_list($value)) {
            $fail(__('The :attribute field is not valid: :details.', [
                'attribute' => $attribute,
                'details'   => __(':attribute must be an object {"description": price}, not a list (e.g. {"Initial setup": 1500})', ['attribute' => $attribute]),
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
                    'service' => $service,
                    'value'   => json_encode($price, JSON_UNESCAPED_UNICODE),
                ]);
            }
        }

        if ($problems === []) {
            return;
        }

        $problems = array_values(array_unique($problems));
        $rest = count($problems) - self::MAX_LISTED;
        $details = array_slice($problems, 0, self::MAX_LISTED);
        if ($rest > 0) {
            $details[] = __('...and :count more problems', ['count' => $rest]);
        }

        $fail(__('The :attribute field is not valid: :details.', [
            'attribute' => $attribute,
            'details'   => implode('; ', $details),
        ]));
    }
}
