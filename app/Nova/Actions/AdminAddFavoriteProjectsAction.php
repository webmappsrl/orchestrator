<?php

namespace App\Nova\Actions;

use App\Models\Project;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Laravel\Nova\Actions\Action;
use Illuminate\Support\Collection;
use Laravel\Nova\Fields\MultiSelect;
use Laravel\Nova\Fields\ActionFields;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Laravel\Nova\Http\Requests\NovaRequest;

class AdminAddFavoriteProjectsAction extends Action
{
    use InteractsWithQueue, Queueable;

    /**
     * The displayable name of the action.
     *
     * @var string
     */

    public $name = 'Add Projects to users favorites';

    /**
     * Perform the action on the given models.
     *
     * @param  \Laravel\Nova\Fields\ActionFields  $fields
     * @param  \Illuminate\Support\Collection  $models
     * @return mixed
     */
    public function handle(ActionFields $fields, Collection $models)
    {
        //associate the selected projects to the user
        foreach ($models as $user) {
            foreach ($fields->projects as $project) {
                $projectToAdd = Project::find($project);
                if (!$user->hasFavorited($projectToAdd)) {
                    $user->favorite($projectToAdd);
                } else {
                    return Action::danger('This Project is already favorited');
                }
            }
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
        return [
            MultiSelect::make('Projects')
                ->options(Project::all()->pluck('name', 'id'))
                ->rules('required')
                ->displayUsingLabels(),
        ];
    }
}
