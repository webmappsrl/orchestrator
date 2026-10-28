<?php

namespace App\Rules;

use App\Services\Quotes\QuoteRichText;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Exception\RfcComplianceException;

/**
 * Persone del cliente da contattare per una trattativa (oc:8731): `Nome Cognome <email>` separati
 * da virgole, con il nome anche fra virgolette (`"Rossi, Mario" <m.rossi@comune.pisa.it>`).
 *
 * Address::create() da solo non basta: accetta testo dopo `>`, ignora le righe dopo la prima e
 * restituisce un nome vuoto senza errore. Qui la forma si controlla per intero e Symfony valida
 * solo l'email. Il valore non viene mai riscritto. Va preceduta da `string` e da un `max:`: la
 * Rule ignora i valori che non sono stringhe.
 */
class CustomerContacts implements ValidationRule
{
    // Quantificatori possessivi, per non tornare indietro sui caratteri già letti; il nome senza
    // virgolette comprende gli spazi prima di `<`, che check() toglie.
    private const CONTACT_PATTERN = '/\A(?<name>"(?:[^"\\\\\r\n]|\\\\[^\r\n])*+"[ \t]*+|[^"<>\r\n]*+)<(?<email>[^<>\s]++)>\z/u';

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || $value === '') {
            return;
        }

        $contacts = $this->split($value);
        if ($contacts === null) {
            $fail(__('The :attribute field has an unclosed quote.'));

            return;
        }

        foreach ($contacts as $i => $contact) {
            $message = $this->check(trim($contact, " \t\r\n"), $i + 1);
            if ($message !== null) {
                $fail($message);

                return;
            }
        }
    }

    /**
     * Divide sulle virgole fuori dalle virgolette; dentro, `\"` è una virgoletta scritta.
     * Restituisce null se le virgolette restano aperte.
     */
    private function split(string $value): ?array
    {
        $contacts = [];
        $current = '';
        $inQuotes = false;
        $length = strlen($value);

        for ($i = 0; $i < $length; $i++) {
            $char = $value[$i];

            if ($inQuotes && $char === '\\' && $i + 1 < $length) {
                $current .= $char . $value[++$i];
                continue;
            }

            if ($char === '"') {
                $inQuotes = ! $inQuotes;
            } elseif ($char === ',' && ! $inQuotes) {
                $contacts[] = $current;
                $current = '';
                continue;
            }

            $current .= $char;
        }

        if ($inQuotes) {
            return null;
        }

        $contacts[] = $current;

        return $contacts;
    }

    private function check(string $contact, int $position): ?string
    {
        if ($contact === '') {
            return __('The :attribute field has an empty contact at position :position.', ['position' => $position]);
        }

        // Il testo viene dall'utente: un `:` non deve diventare un segnaposto del validator.
        $shown = ['contact' => QuoteRichText::protectPlaceholders($contact)];

        if (! preg_match(self::CONTACT_PATTERN, $contact, $matches)) {
            return __('The contact ":contact" is not in the format Name Surname <email>.', $shown);
        }

        if (trim(trim(trim($matches['name']), '"')) === '') {
            return __('The contact ":contact" has no name.', $shown);
        }

        try {
            new Address($matches['email']);
        } catch (RfcComplianceException) {
            return __('The contact ":contact" has an invalid email address.', $shown);
        }

        return null;
    }
}
