<?php

namespace App\Nova\Actions;

use App\Enums\StoryStatus;
use Illuminate\Bus\Queueable;
use Laravel\Nova\Actions\Action;
use Illuminate\Support\Collection;
use Laravel\Nova\Fields\ActionFields;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Laravel\Nova\Http\Requests\NovaRequest;

class StoryToTestStatusAction extends Action
{
    use InteractsWithQueue, Queueable;

    /**
     * Perform the action on the given models.
     *
     * @param  \Laravel\Nova\Fields\ActionFields  $fields
     * @param  \Illuminate\Support\Collection  $models
     * @return mixed
     */
    public function handle(ActionFields $fields, Collection $models)
    {
        foreach ($models as $model) {
            // Verifica che il tester sia assegnato prima di passare a "testing"
            if (empty($model->tester_id)) {
                return Action::danger('Impossibile cambiare lo stato a "Da testare" senza avere assegnato un tester.');
            }
            
            $model->update([
                'status' => StoryStatus::Test->value,
            ]);
        }

        return Action::message('Status changed correctly');
    }

    /**
     * Get the fields available on the action.
     *
     * @param  \Laravel\Nova\Http\Requests\NovaRequest  $request
     * @return array
     */
    public function fields(NovaRequest $request)
    {
        return [];
    }

    public function name()
    {
        return 'Change status to Test';
    }
}
