<?php

namespace App\Nova;

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
use Laravel\Nova\Fields\BelongsToMany;
use Datomatic\NovaMarkdownTui\MarkdownTui;
use Laravel\Nova\Http\Requests\NovaRequest;
use Datomatic\NovaMarkdownTui\Enums\EditorType;

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
                BelongsTo::make('Customer'),
                //add a column to display the SAL of all epics in this milestone
                Text::make('SAL', function () {
                    return $this->wip();
                })->hideWhenCreating()->hideWhenUpdating(),
                Text::make('Backlog', function () {
                    return $this->backlogStories()->count();
                })->hideWhenCreating()->hideWhenUpdating(),
                Date::make('Due date')->sortable(),
                Tag::make('Tag epics', 'tagEpics', 'App\Nova\Epic')->onlyOnDetail()->withPreview()
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
                BelongsToMany::make('Users', 'users'),
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
        return [
            new filters\CustomerFilter
        ];
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
        return [];
    }

    public function indexBreadcrumb()
    {
        return null;
    }
}
