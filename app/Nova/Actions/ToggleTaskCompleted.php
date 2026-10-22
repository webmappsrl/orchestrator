<?php

namespace App\Nova\Actions;

use App\Models\Task;
use App\Nova\Task as TaskResource;
use App\Nova\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Fields\ActionFields;
use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Fields\DateTime;
use Laravel\Nova\Fields\Heading;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\NovaRequest;

/**
 * Chiude un task aperto, con la possibilità di creare subito il task
 * successivo sulla stessa trattativa, oppure riapre un task chiuso
 * (oc:8730). Lavora su un task per volta.
 *
 * All'esecuzione Nova ricostruisce l'action senza task e la ritrova per
 * uriKey: per questo l'uriKey è fisso, mentre il nome segue lo stato del
 * task, e fields() restituisce i campi quando il task non c'è.
 *
 * Su una trattativa chiusa il task successivo non nasce: decide
 * TaskPolicy::create() con la Quote, come nell'API (review del 19/10/2026).
 */
class ToggleTaskCompleted extends Action
{
    use InteractsWithQueue, Queueable;

    public function name()
    {
        return $this->isClosedTask() ? __('Reopen') : __('Mark as completed');
    }

    public function uriKey()
    {
        return 'toggle-task-completed';
    }

    /**
     * Stessa regola dell'API: TaskPolicy::updateStatus() (oc:8722).
     */
    public function authorizedToRun(Request $request, $model)
    {
        return $request->user()?->can('updateStatus', $model) ?? false;
    }

    public function handle(ActionFields $fields, Collection $models)
    {
        if ($models->count() !== 1) {
            return Action::danger(__('Only one task at a time can be closed or reopened.'));
        }

        $task = $models->first();

        if ($task->status === Task::STATUS_COMPLETED) {
            $task->status = Task::STATUS_TODO;
            $task->save();

            return;
        }

        // Prima della transazione: se la policy nega, il task resta aperto.
        if (filled($fields->title) && $this->isQuoteClosedFor($task)) {
            return Action::danger(__('The quote is closed: the next task cannot be created.'));
        }

        DB::transaction(function () use ($task, $fields) {
            $task->status = Task::STATUS_COMPLETED;
            $task->save();

            if (blank($fields->title)) {
                return;
            }

            Task::create([
                'quote_id' => $task->quote_id,
                'title' => $fields->title,
                'due_date' => $fields->due_date,
                'assignee_id' => $fields->assignee?->id,
                'notes' => $fields->notes,
            ]);
        });
    }

    public function fields(NovaRequest $request)
    {
        if ($this->isClosedTask() || $this->isOnClosedQuote()) {
            return [];
        }

        return [
            Heading::make(__('Next task (optional)')),

            Text::make(__('Task'), 'title')
                ->rules('nullable', 'max:255'),

            DateTime::make(__('Due date'), 'due_date')
                ->rules('nullable', 'required_with:title'),

            // La ricerca degli utenti nel popup passa da
            // /nova-api/tasks/associatable/assignee, che usa il campo
            // `assignee` della risorsa Task (assigneeField()), non questo:
            // i due campi devono restare con lo stesso attributo e lo
            // stesso filtro withTaskAccess().
            BelongsTo::make(__('Assignee'), 'assignee', User::class)
                ->searchable()
                ->nullable()
                ->rules('nullable', 'required_with:title')
                ->relatableQueryUsing(fn (NovaRequest $request, $query) => $query->withTaskAccess())
                ->default(fn (NovaRequest $request) => $this->resource instanceof Task
                    ? Task::defaultAssigneeId($this->resource->quote, $request->user())
                    : null),

            TaskResource::notesField(),
        ];
    }

    private function isClosedTask(): bool
    {
        return $this->resource instanceof Task
            && $this->resource->status === Task::STATUS_COMPLETED;
    }

    private function isOnClosedQuote(): bool
    {
        return $this->resource instanceof Task
            && $this->isQuoteClosedFor($this->resource);
    }

    /**
     * Unico punto in cui l'action chiede a TaskPolicy::create() se sulla
     * trattativa del task può nascere un task nuovo.
     */
    private function isQuoteClosedFor(Task $task): bool
    {
        return Gate::denies('create', [Task::class, $task->quote]);
    }
}
