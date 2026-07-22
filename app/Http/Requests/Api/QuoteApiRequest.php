<?php

namespace App\Http\Requests\Api;

use App\Enums\QuoteStatus;
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
            'additional_services'  => ['sometimes', 'nullable', 'array'],
            'customer_id'          => [$isCreate ? 'required' : 'sometimes', 'integer', 'exists:customers,id'],
            'google_drive_url'     => ['sometimes', 'nullable', 'string', 'max:2048'],
            'discount'             => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'notes'                => ['sometimes', 'nullable', 'string'],
            'template'             => ['sometimes', 'boolean'],
        ];
    }
}
