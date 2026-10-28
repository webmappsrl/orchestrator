<?php

namespace App\Http\Requests\Api;

use App\Enums\QuoteStatus;
use App\Rules\AdditionalServicesMap;
use App\Rules\CustomerContacts;
use App\Services\Quotes\QuoteRichText;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class QuoteApiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $isCreate = $this->isMethod('POST');

        return [
            'title'                => [$isCreate ? 'required' : 'sometimes', 'string', 'max:255'],
            'status'               => ['sometimes', Rule::in(array_column(QuoteStatus::cases(), 'value'))],
            'priority'             => ['sometimes', 'nullable', 'integer'],
            'additional_services'  => ['sometimes', 'nullable', 'array', new AdditionalServicesMap()],
            'customer_id'          => [$isCreate ? 'required' : 'sometimes', 'integer', 'exists:customers,id'],
            'google_drive_url'     => ['sometimes', 'nullable', 'string', 'max:2048'],
            'discount'             => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'notes'                => ['sometimes', 'nullable', 'string'],
            'template'             => ['sometimes', 'boolean'],
            // Obbligatorio quando la stessa richiesta chiude la trattativa come persa: dopo la
            // chiusura la policy non permette più di modificarla (oc:8732).
            'lost_reason'          => [
                Rule::requiredIf(fn () => $this->input('status') === QuoteStatus::Closed_Lost->value),
                'nullable', 'string', 'max:2000',
            ],
            // Persone del cliente da contattare solo per questa trattativa, non il referente del
            // Customer (oc:8731): `Nome Cognome <email>` separati da virgola.
            'customer_contacts'    => ['sometimes', 'nullable', 'string', 'max:2000', new CustomerContacts()],
        ] + array_fill_keys(QuoteRichText::FIELDS, ['sometimes', ...QuoteRichText::fieldRules()]);
    }
}
