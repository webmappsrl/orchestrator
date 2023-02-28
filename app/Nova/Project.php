<?php

namespace App\Nova;

use Laravel\Nova\Panel;
use Laravel\Nova\Fields\ID;
use Illuminate\Http\Request;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Fields\Trix;
use Laravel\Nova\Fields\HasMany;
use Laravel\Nova\Fields\Markdown;
use Laravel\Nova\Fields\Textarea;
use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Http\Requests\NovaRequest;



class Project extends Resource
{
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
            new Panel('MAIN INFO', [
                ID::make()->sortable(),
                Text::make('Name')
                    ->sortable()
                    ->rules('required', 'max:255'),
                BelongsTo::make('Customer'),
            ]),

            new panel('DESCRIPTION', [
                Markdown::make('Description')
                    ->hideFromIndex()
                    ->alwaysShow(),
            ]),

            new Panel('NOTE', [
                Markdown::make('Note')
                    ->hideFromIndex()
                    ->nullable()
                    ->alwaysShow(),
            ]),

            HasMany::make('Epics'),
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
        return [];
    }
}