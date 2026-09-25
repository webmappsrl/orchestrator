<?php

namespace App\Services\Quotes;

use App\Rules\SafeRichTextHtml;

/**
 * Regole condivise dei campi rich-text del preventivo (oc:8631): usate dalla
 * FormRequest dell'API, dal controller e dal comando quotes:check-rich-text,
 * così il controllo prima del rilascio applica esattamente le regole dell'API.
 */
final class QuoteRichText
{
    public const FIELDS = ['additional_info', 'delivery_time', 'payment_plan', 'billing_plan'];

    public const MAX_LENGTH = 50000;

    /** Voci al massimo in un messaggio 422, poi «…e altri N». */
    public const MAX_LISTED = 10;

    /** @return array<int, mixed> */
    public static function fieldRules(): array
    {
        return ['nullable', 'string', 'max:' . self::MAX_LENGTH, new SafeRichTextHtml()];
    }

    public static function isEmpty(mixed $value): bool
    {
        return $value === null || $value === '';
    }

    /**
     * Tiene al massimo MAX_LISTED voci e aggiunge una riga con quante ne restano.
     *
     * @param  array<int, string>  $details
     * @return array<int, string>
     */
    public static function limitDetails(array $details, string $moreTranslationKey): array
    {
        $rest = count($details) - self::MAX_LISTED;
        $details = array_slice($details, 0, self::MAX_LISTED);
        if ($rest > 0) {
            $details[] = __($moreTranslationKey, ['count' => $rest]);
        }

        return $details;
    }

    /**
     * Il Validator sostituisce :attribute, :input, :index e :position anche
     * dentro il messaggio già composto: un nome di servizio o di tag scritto
     * dall'utente che li contiene verrebbe alterato. Un word joiner (U+2060)
     * dopo i due punti lo rende invisibilmente diverso dal segnaposto.
     */
    public static function protectPlaceholders(string $text): string
    {
        return str_replace(':', ":\u{2060}", $text);
    }
}
