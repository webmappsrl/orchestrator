<?php

namespace App\Nova\Actions;

use App\Models\User;
use App\Enums\UserRole;
use Illuminate\Bus\Queueable;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Actions\Action;
use Illuminate\Support\Collection;
use Laravel\Nova\Fields\ActionFields;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Laravel\Nova\Http\Requests\NovaRequest;

class AddProjectsToFavorites extends Action
{
    use InteractsWithQueue, Queueable;

    /**
     * The displayable name of the action.
     *
     * @var string
     */
    public $name = 'Add Projects to favorites';

    /**
     * Perform the action on the given models.
     *
     * @param  \Laravel\Nova\Fields\ActionFields  $fields
     * @param  \Illuminate\Support\Collection  $models
     * @return mixed
     */
    public function handle(ActionFields $fields, Collection $models)
    {
        //if logged user is admin then the user to associate the project to is the one selected in the select field
        if (auth()->user()->hasRole(UserRole::Admin)) {
            $user = User::find($fields->user);
        } else {
            //otherwise the user is the logged user
            $user = auth()->user();
        }

        foreach ($models as $project) {
            //if the project is not already favorited by the user
            if (!$user->hasFavorited($project)) {
                $user->favorite($project);
            } else {
                return Action::danger('Project already favorited');
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
        //if the user is admin can see a list of users and select the one to associate the project to
        if (auth()->user()->hasRole(UserRole::Admin)) {
            return [
                Select::make('User')->options(User::all()->pluck('name', 'id')->toArray())->displayUsingLabels()
            ];
        } else {
            return [];
        }
    }
}
