<?php

namespace App\Nova;

use App\Enums\EpicStatus;
use App\Nova\Actions\SetMilestoneEpicsToDone;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Fields\HasMany;
use Laravel\Nova\Fields\DateTime;
use Laravel\Nova\Fields\Number;
use Datomatic\NovaMarkdownTui\MarkdownTui;
use Laravel\Nova\Http\Requests\NovaRequest;
use Datomatic\NovaMarkdownTui\Enums\EditorType;
use Eminiarts\Tabs\Tab;
use Eminiarts\Tabs\Tabs;

class Milestone extends Resource
{
    /**
     * The model the resource corresponds to.
     *
     * @var class-string<\App\Models\Milestone>
     */
    public static $model = \App\Models\Milestone::class;

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
        'id', 'name', 'description'
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
            ID::make(__('ID'), 'id')->sortable(),
            Text::make(__('Name'), 'name')->sortable(),
            MarkdownTui::make(__('Description'), 'description')
                ->hideFromIndex()
                ->initialEditType(EditorType::MARKDOWN),
            DateTime::make(__('Due Date'), 'due_date')->sortable(),
            new Tabs('Epics', [
                //create a tab for every status of epics
                Tab::make('New Epics', [
                    HasMany::make('New Epics', 'newEpics', Epic::class),
                ]),
                Tab::make('Project Epics', [
                    HasMany::make('Project Epics', 'projectEpics', Epic::class),
                ]),
                Tab::make('Progress Epics', [
                    HasMany::make('Progress Epics', 'progressEpics', Epic::class),
                ]),
                Tab::make('Test Epics', [
                    HasMany::make('Test Epics', 'testEpics', Epic::class),
                ]),
                Tab::make('Done Epics', [
                    HasMany::make('Done Epics', 'doneEpics', Epic::class),
                ]),
            ]),
            //add a column to display the SAL of all epics in this milestone
            Text::make('SAL', function () {
                return $this->wip();
            })->hideWhenCreating()->hideWhenUpdating(),



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
            (new SetMilestoneEpicsToDone)
                //inlining the action
                ->onlyOnTableRow()
                ->showOnDetail(),
        ];
    }
}