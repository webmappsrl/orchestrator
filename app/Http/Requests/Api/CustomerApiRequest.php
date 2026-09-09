<?php

namespace App\Http\Requests\Api;

use App\Enums\CustomerStatus;
use App\Models\Customer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Whitelist esplicita sui soli campi richiesti da oc:8505. Customer::$fillable
 * espone molti più campi (hs_id, wmpm_id, domain_name, associated_user_id, ...)
 * che NON devono essere scrivibili via API — il controller non farà mai
 * $customer->fill($validated), assegna solo i campi elencati qui uno a uno.
 */
class CustomerApiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // Mirrors App\Nova\Customer's Text::make('Name')->creationRules('unique:customers,name'):
        // Nova rejects a duplicate explicit name outright. Auto-generated names
        // (resolveName()/uniqueSlug() in the controller, when 'name' is omitted)
        // stay silently deduplicated with a numeric suffix — this rule only
        // covers the case the caller supplied 'name' themselves.
        $uniqueName = Rule::unique('customers', 'name');
        if ($this->isMethod('PATCH') || $this->isMethod('PUT')) {
            $uniqueName = $uniqueName->ignore($this->route('customer'));
        }

        return [
            'name'                  => ['sometimes', 'nullable', 'string', 'max:255', $uniqueName],
            'company_name'          => ['sometimes', 'nullable', 'string', 'max:255'],
            'vat'                   => ['sometimes', 'nullable', 'regex:/^[0-9]{11}$/'],
            'address'               => ['sometimes', 'nullable', 'string'],
            'contact_emails'        => ['sometimes', 'nullable', 'array'],
            'contact_emails.*'      => ['email'],
            'contact_emails_add'    => ['sometimes', 'nullable', function ($attribute, $value, $fail) {
                foreach (is_array($value) ? $value : [$value] as $email) {
                    if (!is_string($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                        $fail(__('Each contact_emails_add entry must be a valid email address.'));
                        return;
                    }
                }
            }],
            'phone'                 => ['sometimes', 'nullable', 'string', 'max:255', function ($attribute, $value, $fail) {
                // Laravel runs every rule for an attribute regardless of earlier
                // failures (no `bail` here): a non-string $value already fails
                // the 'string' rule above, but this closure still gets invoked
                // with the raw value — phoneValidationError()'s `?string` type
                // hint would otherwise throw a TypeError instead of a clean 422.
                if (!is_string($value) && $value !== null) {
                    return;
                }
                if ($error = $this->phoneValidationError($value)) {
                    $fail($error);
                }
            }],
            'status'                => ['sometimes', Rule::in(array_column(CustomerStatus::cases(), 'value'))],
            'notes'                 => ['sometimes', 'nullable', 'string'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($this->has('contact_emails') && $this->has('contact_emails_add')) {
                $validator->errors()->add(
                    'contact_emails_add',
                    __('contact_emails and contact_emails_add are mutually exclusive in the same request.')
                );
            }
        });
    }

    private function phoneValidationError(?string $value): ?string
    {
        if (blank($value)) {
            return null;
        }

        // Overview requires reusing Customer::normalizePhoneString(): without it,
        // a number with NBSP/zero-width chars (typical of copy-paste) is rejected
        // here even though App\Nova\Customer's own validation (which normalizes
        // first) would accept it — same drift class oc:8412 already fixed once.
        $value = Customer::normalizePhoneString($value);

        $fragments = collect(explode(',', $value))
            ->map(fn ($fragment) => trim($fragment))
            ->filter(fn ($fragment) => $fragment !== '');

        foreach ($fragments as $fragment) {
            if (!$this->isValidPhoneFragment($fragment)) {
                return __('One or more numbers are not in a valid phone format.');
            }
        }

        return null;
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
}
