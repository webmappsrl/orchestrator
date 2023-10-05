<?php

namespace App\Nova;

use App\Nova\User;
use App\Enums\UserRole;
use Eminiarts\Tabs\Tab;
use Laravel\Nova\Panel;
use Eminiarts\Tabs\Tabs;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Tag;
use Laravel\Nova\Fields\Date;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Fields\Number;
use Laravel\Nova\Fields\HasMany;
use Laravel\Nova\Fields\DateTime;
use Eminiarts\Tabs\Traits\HasTabs;
use Laravel\Nova\Fields\BelongsTo;
use Datomatic\NovaMarkdownTui\MarkdownTui;
use Laravel\Nova\Http\Requests\NovaRequest;
use App\Nova\Actions\AddProjectsToFavorites;
use App\Nova\Actions\addStoriesToBacklogAction;
use Datomatic\NovaMarkdownTui\Enums\EditorType;
use Ebess\AdvancedNovaMediaLibrary\Fields\Files;
use App\Nova\Actions\RemoveProjectsFromFavoritesAction;

class Project extends Resource
{
    use HasTabs;
    /**
     * The model the resource corresponds to.
     *
     * @var class-string<\App\Models\Project>
     */
    public static $model = \App\Models\Project::class;

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
        'id', 'name', 'description', 'customer.name'
    ];

    public static function indexQuery(NovaRequest $request, $query)
    {
        if ($request->user()->hasRole(UserRole::Customer)) {
            return $query->where('user_id', $request->user()->id);
        }
        return $query;
    }

    /**
     * Get the fields displayed by the resource.
     *
     * @param  \Laravel\Nova\Http\Requests\NovaRequest  $request
     * @return array
     */
    public function fields(NovaRequest $request)
    {
        return [
            new Panel('MAIN INFO', [
                ID::make()->sortable(),
                Text::make('Name')
                    ->sortable()
                    ->rules('required', 'max:255'),
                BelongsTo::make('Customer')
                    ->filterable()
                    ->searchable(),
                Text::make('SAL', function () {
                    return $this->wip();
                })->hideWhenCreating()->hideWhenUpdating(),
                Text::make('Backlog', function () {
                    return $this->backlogStories()->count();
                })->hideWhenCreating()->hideWhenUpdating(),
                Date::make('Due date')->sortable(),
                Tag::make('Tag epics', 'tagEpics', 'App\Nova\Epic')->onlyOnDetail()->withPreview(),
                //create a tex field that shows the users that have this project as favorite, and link every user to its detail page
                Text::make('Favorited By', function () {
                    $users = [];
                    foreach ($this->favoriters as $user) {
                        $users[] = '<span style="color:green; font-weight:bold; margin: 0 5px"  >' . $user->name . '</span>';
                    }
                    return implode('|', $users);
                })->onlyOnDetail()
                    ->asHtml(),
                Files::make('Documents', 'documents')
                    ->hideFromIndex(),
            ]),

            new panel('DESCRIPTION', [
                MarkdownTui::make('Description')
                    ->hideFromIndex()
                    ->initialEditType(EditorType::MARKDOWN)
            ]),

            new Panel('NOTES', [
                MarkdownTui::make('Notes')
                    ->hideFromIndex()
                    ->initialEditType(EditorType::MARKDOWN)
                    ->nullable()
            ]),

            new Tabs('Epics', [
                new Tab('Epics', [
                    HasMany::make('Epics'),
                ]),
                new Tab('Backlog Stories', [
                    HasMany::make('Backlog Stories', 'backlogStories', Story::class),
                ]),
            ])
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
        // return [
        //     new filters\CustomerFilter,  //this filter is no longer needed because we have a BelongsTo field for the customer with the built in option filterable()
        // ];
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
            (new addStoriesToBacklogAction)
                ->onlyInline()
                ->showOnDetail()
                ->confirmButtonText('Add Stories')
                ->confirmText('Stories will be created starting from the first line of the text area, adding one story per line and associating them to the current project backlog and the specified deadlines, type and user.')
                ->cancelButtonText('Cancel'),

            (new AddProjectsToFavorites)
                ->showOnDetail()
                ->showInline()
                ->confirmButtonText('Add to favorites')
                ->cancelButtonText('Cancel')
                ->canSee(
                    function ($request) {
                        return !$request->user()->hasRole(UserRole::Customer);
                    }
                ),

            (new RemoveProjectsFromFavoritesAction)
                ->showOnDetail()
                ->showInline()
                ->confirmButtonText('Remove from favorites')
                ->cancelButtonText('Cancel')
                ->canSee(
                    function ($request) {
                        return !$request->user()->hasRole(UserRole::Admin) && !$request->user()->hasRole(UserRole::Customer);
                    }
                )

        ];
    }

    public function indexBreadcrumb()
    {
        return null;
    }
}
