<?php

namespace App\Http\Requests\Api;

use App\Models\Task;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TaskApiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Whitelist esplicita per metodo HTTP. `creator_id` non compare MAI in
     * nessuna delle due liste: resta gestito solo da Task::booted()
     * (assegnato all'utente autenticato alla creazione), non è mai un
     * campo accettato in input — impedisce il mass-assignment via API.
     */
    public function rules(): array
    {
        if ($this->isMethod('POST')) {
            return [
                'quote_id' => ['required', 'integer', 'exists:quotes,id'],
                'title'    => ['required', 'string', 'max:255'],
                'notes'    => ['sometimes', 'nullable', 'string'],
                'due_date' => ['required', 'date'],
            ];
        }

        return [
            'status' => ['sometimes', Rule::in([Task::STATUS_TODO, Task::STATUS_COMPLETED])],
            'notes'  => ['sometimes', 'string'],
        ];
    }
}
