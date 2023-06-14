<?php

namespace App\Nova;

use App\Enums\UserRole;
use App\Models\Project;
use App\Nova\Actions\AdminAddFavoriteProjectsAction;
use App\Nova\Actions\AdminRemoveFavoriteProjects;
use App\Nova\Actions\AdminRemoveFavoriteProjectsAction;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules;
use Laravel\Nova\Fields\BelongsToMany;
use Laravel\Nova\Fields\Gravatar;
use Laravel\Nova\Fields\HasMany;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\MultiSelect;
use Laravel\Nova\Fields\Password;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\NovaRequest;
use Overtrue\LaravelFavorite\Favorite;

class User extends Resource
{
    /**
     * The model the resource corresponds to.
     *
     * @var string
     */
    public static $model = \App\Models\User::class;

    /**
     * The single value that should be used to represent the resource when being displayed.
     *
     * @var string
     */
    public static $title = 'name';

    /**
     * The columns that should be searched.
     *
     * @var array
     */
    public static $search = [
        'id', 'name', 'email',
    ];

    /**
     * Get the fields displayed by the resource.
     *
     * @param  \Laravel\Nova\Http\Requests\NovaRequest  $request
     * @return array
     */
    public function fields(NovaRequest $request)
    {
        return [
            ID::make()->sortable(),

            Gravatar::make()->maxWidth(50),

            Text::make('Name')
                ->sortable()
                ->rules('required', 'max:255'),

            Text::make('Email')
                ->sortable()
                ->rules('required', 'email', 'max:254')
                ->creationRules('unique:users,email')
                ->updateRules('unique:users,email,{{resourceId}}'),
            //Creates a multi-select field for 'Roles' with options populated from the UserRole::cases() method.
            //The options are in the form of a key-value pair, with the 'name' attribute being used as the visible text and the 'value' attribute being used as the value of each option.
            MultiSelect::make('Roles')->options(collect(UserRole::cases())->pluck('name', 'value')),

            Password::make('Password')
                ->onlyOnForms()
                ->creationRules('required', Rules\Password::defaults())
                ->updateRules('nullable', Rules\Password::defaults()),

            HasMany::make('Epics'),
            HasMany::make('Stories'),
            Text::make('Favorite Projects', function () {
                $projects = [];
                $userFavorites = $this->getFavoriteItems(Project::class)->get();
                foreach ($userFavorites as $project) {
                    $projects[] = '<a href="/resources/projects/' . $project->id . '" style="color:green; font-weight:bold; margin: 0 5px">' . $project->name . '</a>';
                }
                return implode('|', $projects);
            })->asHtml()->onlyOnDetail(),
        ];
    }

    /**
     * Get the cards available for the request.
     *
     * @param  \Laravel\Nova\Http\Requests\NovaRequest  $request
     * @return array
     */
    public function cards(NovaRequest $request)
    {
        return [];
    }

    /**
     * Get the filters available for the resource.
     *
     * @param  \Laravel\Nova\Http\Requests\NovaRequest  $request
     * @return array
     */
    public function filters(NovaRequest $request)
    {
        return [];
    }

    /**
     * Get the lenses available for the resource.
     *
     * @param  \Laravel\Nova\Http\Requests\NovaRequest  $request
     * @return array
     */
    public function lenses(NovaRequest $request)
    {
        return [];
    }

    /**
     * Get the actions available for the resource.
     *
     * @param  \Laravel\Nova\Http\Requests\NovaRequest  $request
     * @return array
     */
    public function actions(NovaRequest $request)
    {
        return [
            (new AdminAddFavoriteProjectsAction($request->resourceId))->canSee(
                function ($request) {
                    return $request->user()->hasRole(UserRole::Admin);
                }
            )
                ->showInline()
                ->confirmText('Are you sure you want to add this project to the user\'s favorites?')
                ->confirmButtonText('Add')
                ->cancelButtonText("Don't add"),

            (new AdminRemoveFavoriteProjectsAction($request->resourceId))->canSee(
                function ($request) {
                    return $request->user()->hasRole(UserRole::Admin);
                }
            )
                ->showInline()
                ->confirmText('Are you sure you want to remove this project from the user\'s favorites?')
                ->confirmButtonText('Remove')
                ->cancelButtonText("Don't remove"),
        ];
    }
}
