<?php

namespace App\Http\Requests\Api;

use App\Enums\StoryStatus;
use App\Enums\StoryType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoryApiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $isCreate = $this->isMethod('POST');

        return [
            'name'             => [$isCreate ? 'required' : 'sometimes', 'string', 'max:255'],
            'description'      => ['sometimes', 'nullable', 'string'],
            'customer_request' => ['sometimes', 'nullable', 'string'],
            'type'             => ['sometimes', 'nullable', Rule::enum(StoryType::class)],
            'status'           => ['sometimes', 'nullable', Rule::enum(StoryStatus::class)],
            'user_id'          => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
            'tester_id'        => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
            'creator_id'       => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
            'parent_id'        => ['sometimes', 'nullable', 'integer', 'exists:stories,id'],
            'estimated_hours'  => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'tags'             => ['sometimes', 'nullable', 'array'],
            'tags.*'           => ['integer', 'exists:tags,id'],
        ];
    }
}
