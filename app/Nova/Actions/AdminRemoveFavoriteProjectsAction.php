<?php

namespace App\Nova\Actions;

use App\Models\User;
use App\Models\Project;
use Illuminate\Bus\Queueable;
use Laravel\Nova\Actions\Action;
use Illuminate\Support\Collection;
use Laravel\Nova\Fields\MultiSelect;
use Laravel\Nova\Fields\ActionFields;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Laravel\Nova\Http\Requests\NovaRequest;
use Outl1ne\MultiselectField\Multiselect as MultiselectFieldMultiselect;

class AdminRemoveFavoriteProjectsAction extends Action
{
    use InteractsWithQueue, Queueable;

    public $name = 'Remove Projects from Favorites';

    protected $model;

    public function __construct($model = null)
    {
        $this->model = $model;
    }

    /**
     * Perform the action on the given models.
     *
     * @param  \Laravel\Nova\Fields\ActionFields  $fields
     * @param  \Illuminate\Support\Collection  $models
     * @return mixed
     */
    public function handle(ActionFields $fields, Collection $models)
    {
        $projectsToRemove = Project::whereIn('id', $fields['projects'])->get();
        foreach ($models as $user) {
            foreach ($projectsToRemove as $project) {
                $user->unfavorite($project);
            }
        }
        return Action::message('Projects removed from favorites.');
    }

    /**
     * Get the fields available on the action.
     *
     * @param  \Laravel\Nova\Http\Requests\NovaRequest  $request
     * @return array
     */
    public function fields(NovaRequest $request)
    {
        if ($this->model) {
            $user = User::find($this->model);
            $favoriteProjects = $user->getFavoriteItems(Project::class)->get();
            $favoriteProjectsOptions = $favoriteProjects->pluck('name', 'id')->toArray();

            if ($user) {

                return [
                    MultiSelect::make('Projects')
                        ->options($favoriteProjectsOptions)
                        ->rules('required')
                        ->displayUsingLabels()
                ];
            }
        }
        return [
            Multiselect::make('Projects')
                ->options(Project::all()->pluck('name', 'id')->toArray())
                ->rules('required')
                ->displayUsingLabels()
        ];
    }
}
