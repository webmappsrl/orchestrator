<?php

namespace App\Nova\Actions;

use App\Models\Epic;
use App\Models\Project;
use Illuminate\Bus\Queueable;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Actions\Action;
use Illuminate\Support\Collection;
use Laravel\Nova\Fields\ActionFields;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Laravel\Nova\Http\Requests\NovaRequest;

class moveStoriesFromProjectToEpicAction extends Action
{
    use InteractsWithQueue, Queueable;

    /**
     * The displayable name of the action.
     *
     * @var string
     */
    public $name = 'Move to Epic';

    /**
     * Perform the action on the given models.
     *
     * @param  \Laravel\Nova\Fields\ActionFields  $fields
     * @param  \Illuminate\Support\Collection  $models
     * @return mixed
     */
    public function handle(ActionFields $fields, Collection $models)
    {
        $epic = Epic::find($fields['epic_id']);

        foreach ($models as $story) {
            $story->epic()->associate($epic);
            $story->save();
        }
    }

    /**
     * Get the fields available on the action.
     *
     * @param  \Laravel\Nova\Http\Requests\NovaRequest  $request
     * @return array
     */
    public function fields(NovaRequest $request)
    {
        $resourceId = $request->query('viaResourceId');

        return [
            //create a select showing as options only epic that belongs to the current project instance
            Select::make(__('Epic'), 'epic_id')
                ->options(Epic::where('project_id', $resourceId)->pluck('name', 'id'))
                ->displayUsingLabels()
                ->rules('required')
                ->searchable()
        ];
    }
}
