<?php

namespace App\Nova;

use Carbon\Carbon;
use Laravel\Nova\Fields\ID;
use Illuminate\Http\Request;
use Laravel\Nova\Fields\Date;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Fields\HasMany;
use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Fields\MorphToMany;
use Laravel\Nova\Fields\MultiSelect;
use Laravel\Nova\Http\Requests\NovaRequest;

class Deadline extends Resource
{
    /**
     * The model the resource corresponds to.
     *
     * @var class-string<\App\Models\Deadline>
     */
    public static $model = \App\Models\Deadline::class;

    /**
     * Get the displayable title of the resource.
     *
     * @return string
     */
    public function title()
    {
        $dueDate = $this->due_date;

        $formattedDate = Carbon::parse($dueDate)->format('d-m-Y');

        return $formattedDate;
    }


    /**
     * The columns that should be searched.
     *
     * @var array
     */
    public static $search = [
        'due_date', 'status'
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
            Date::make('Due Date', 'due_date')->sortable(),
            Select::make('Status')->options([
                'new' => 'New',
                'in progress' => 'In Progress',
                'done' => 'Done',
                'expired' => 'Expired',
            ])->sortable()->searchable()->hideFromIndex(),
            BelongsTo::make('Customer')->sortable()->searchable(),
            Text::make('Stories Count', function () {
                return $this->stories()->count();
            })->hideWhenCreating()->hideWhenUpdating(),
            Text::make('SAL', function () {
                return $this->wip();
            })->hideWhenCreating()->hideWhenUpdating(),
            MorphToMany::make('Stories')->searchable(),
            MorphToMany::make('Epics')->searchable(),
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

    public function indexBreadcrumb()
    {
        return null;
    }
}
