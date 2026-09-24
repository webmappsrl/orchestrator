<?php

namespace App\Http\Requests\Api;

use App\Models\Task;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
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
            'status'   => ['sometimes', Rule::in([Task::STATUS_TODO, Task::STATUS_COMPLETED])],
            'notes'    => ['sometimes', 'string'],
            'due_date' => ['sometimes', 'date'],
        ];
    }

    /**
     * `due_date` convertita nel fuso dell'applicazione. La regola `date`
     * accetta anche ISO 8601 con offset o `Z`, ma il cast `datetime` di
     * Task scrive l'ora così com'è, senza convertirla: senza questo
     * passaggio "2026-09-30T22:00:00Z" finirebbe nel DB come le 22:00 di
     * Roma invece che la mezzanotte del giorno dopo. Le stringhe senza
     * fuso vengono già interpretate nel fuso dell'applicazione.
     */
    public function dueDate(): ?Carbon
    {
        return $this->date('due_date')?->setTimezone(config('app.timezone'));
    }
}
