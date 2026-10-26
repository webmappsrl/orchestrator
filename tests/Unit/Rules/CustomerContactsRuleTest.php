<?php

namespace Tests\Unit\Rules;

use App\Rules\CustomerContacts;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Persone del cliente da contattare per una trattativa (oc:8731): elenco `Nome <email>` separato
 * da virgole, con la virgola dentro un nome fra virgolette che non separa.
 */
class CustomerContactsRuleTest extends TestCase
{
    private const FORMAT = 'The contact ":contact" is not in the format Name Surname <email>.';
    private const NO_NAME = 'The contact ":contact" has no name.';
    private const BAD_EMAIL = 'The contact ":contact" has an invalid email address.';

    // Come in API e Nova: la Rule si appoggia a `string` per i valori che non sono stringhe.
    private function errors(mixed $value): array
    {
        return Validator::make(
            ['customer_contacts' => $value],
            ['customer_contacts' => ['nullable', 'string', new CustomerContacts()]]
        )->errors()->get('customer_contacts');
    }

    #[DataProvider('validValues')]
    public function test_valid_values_are_accepted(mixed $value): void
    {
        $this->assertSame([], $this->errors($value));
    }

    public static function validValues(): array
    {
        return [
            'null' => [null],
            'stringa vuota' => [''],
            'una persona' => ['Mario Rossi <m.rossi@comune.pisa.it>'],
            'due persone' => ['Mario Rossi <m.rossi@comune.pisa.it>, Anna Bianchi <a.bianchi@comune.pisa.it>'],
            'una persona per riga' => ["  Mario Rossi <m.rossi@comune.pisa.it>,\n  Anna Bianchi <a.bianchi@comune.pisa.it>\n"],
            'virgola dentro le virgolette' => ['"Rossi, Mario" <m.rossi@comune.pisa.it>'],
            'nome fra virgolette' => ['"Mario Rossi" <m.rossi@comune.pisa.it>'],
            'nome fra virgolette attaccato' => ['"Mario Rossi"<m.rossi@comune.pisa.it>'],
            'virgoletta scritta nel nome' => ['"Mario \"Super\" Rossi" <m.rossi@comune.pisa.it>'],
        ];
    }

    #[DataProvider('invalidContacts')]
    public function test_malformed_contact_is_rejected_naming_it(string $value, string $message, string $contact): void
    {
        $this->assertSame([__($message, ['contact' => $contact])], $this->errors($value));
    }

    public static function invalidContacts(): array
    {
        return [
            'senza nome' => ['m.rossi@comune.pisa.it', self::FORMAT, 'm.rossi@comune.pisa.it'],
            'nome vuoto' => ['<m.rossi@comune.pisa.it>', self::NO_NAME, '<m.rossi@comune.pisa.it>'],
            'nome vuoto fra virgolette' => ['"" <m.rossi@comune.pisa.it>', self::NO_NAME, '"" <m.rossi@comune.pisa.it>'],
            'email non valida' => ['Mario Rossi <m.rossi@>', self::BAD_EMAIL, 'Mario Rossi <m.rossi@>'],
            'testo dopo la parentesi' => ['Mario Rossi <m.rossi@comune.pisa.it> extra', self::FORMAT, 'Mario Rossi <m.rossi@comune.pisa.it> extra'],
            'a capo dentro la persona' => ["Mario\nRossi <m.rossi@comune.pisa.it>", self::FORMAT, "Mario\nRossi <m.rossi@comune.pisa.it>"],
            'a capo dentro un nome fra virgolette' => ["\"Mario\nRossi\" <m.rossi@comune.pisa.it>", self::FORMAT, "\"Mario\nRossi\" <m.rossi@comune.pisa.it>"],
            'testo dopo le virgolette' => ['"Mario" Rossi <m.rossi@comune.pisa.it>', self::FORMAT, '"Mario" Rossi <m.rossi@comune.pisa.it>'],
            'seconda persona sbagliata' => ['Mario Rossi <m.rossi@comune.pisa.it>, a.bianchi@comune.pisa.it', self::FORMAT, 'a.bianchi@comune.pisa.it'],
        ];
    }

    #[DataProvider('emptyContacts')]
    public function test_empty_contact_is_rejected_with_its_position(string $value, int $position): void
    {
        $expected = __('The :attribute field has an empty contact at position :position.', ['position' => $position]);

        $this->assertSame([str_replace(':attribute', 'customer contacts', $expected)], $this->errors($value));
    }

    public static function emptyContacts(): array
    {
        return [
            'virgola finale' => ['Mario Rossi <m.rossi@comune.pisa.it>,', 2],
            'due virgole di fila' => ['Mario Rossi <m.rossi@comune.pisa.it>,, Anna Bianchi <a.bianchi@comune.pisa.it>', 2],
            'virgola iniziale' => [', Mario Rossi <m.rossi@comune.pisa.it>', 1],
        ];
    }

    public function test_unclosed_quote_is_rejected(): void
    {
        $expected = str_replace(':attribute', 'customer contacts', __('The :attribute field has an unclosed quote.'));

        $this->assertSame([$expected], $this->errors('"Rossi, Mario <m.rossi@comune.pisa.it>'));
    }

    public function test_non_string_value_gets_only_the_string_error(): void
    {
        $this->assertCount(1, $this->errors(['Mario Rossi <m.rossi@comune.pisa.it>']));
    }

    public function test_colon_in_the_name_is_not_taken_for_a_placeholder(): void
    {
        $errors = $this->errors('Ufficio :attribute <m.rossi@>');

        $this->assertCount(1, $errors);
        $this->assertStringNotContainsString('customer contacts <', $errors[0]);
    }

    public function test_long_whitespace_runs_are_checked_quickly(): void
    {
        $start = microtime(true);
        $errors = $this->errors('a' . str_repeat(" \t", 1000) . 'b');

        $this->assertCount(1, $errors);
        $this->assertLessThan(1.0, microtime(true) - $start);
    }
}
