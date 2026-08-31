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

    public function test_number_with_non_breaking_space_separator_passes()
    {
        $this->assertNull($this->resource()->phoneValidationError("+39\u{00A0}328\u{00A0}5360803", null));
    }

    public function test_valid_foreign_number_with_explicit_prefix_passes()
    {
        $this->assertNull($this->resource()->phoneValidationError('+41 79 123 45 67', null));
    }

    public function test_invalid_text_fails()
    {
        $this->assertNotNull($this->resource()->phoneValidationError('non un numero', null));
    }

    public function test_number_with_letters_mixed_in_fails()
    {
        $this->assertNotNull($this->resource()->phoneValidationError('39a5360803', null));
    }

    public function test_too_short_number_without_prefix_fails()
    {
        $this->assertNotNull($this->resource()->phoneValidationError('12345', null));
    }

    public function test_too_long_number_without_prefix_fails()
    {
        $this->assertNotNull($this->resource()->phoneValidationError('123456789012', null));
    }

    public function test_too_short_number_with_explicit_prefix_fails()
    {
        $this->assertNotNull($this->resource()->phoneValidationError('+1234567', null));
    }

    public function test_too_long_number_with_explicit_prefix_fails()
    {
        $this->assertNotNull($this->resource()->phoneValidationError('+1234567890123456', null));
    }

    public function test_lone_separator_characters_are_treated_as_empty_and_valid()
    {
        $this->assertNull($this->resource()->phoneValidationError(',', null));
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

    public function test_non_breaking_space_only_change_on_legacy_value_is_not_considered_changed()
    {
        $legacy = 'non valido ma gia in db';
        $withNbsp = "non\u{00A0}valido ma gia in db";
        $this->assertNull($this->resource()->phoneValidationError($withNbsp, $legacy));
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
        $legacy = 'non valido ma gia in db';
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
