<?php

namespace App\Nova\Lenses;

use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Lenses\Lens;
use Laravel\Nova\Fields\Status;
use Laravel\Nova\Fields\BelongsTo;
use App\Nova\Filters\ProjectFilter;
use App\Nova\Filters\MilestoneFilter;
use App\Nova\Filters\EpicStatusFilter;
use Laravel\Nova\Http\Requests\LensRequest;
use Laravel\Nova\Http\Requests\NovaRequest;

class MyEpicLens extends Lens
{
    /**
     * The columns that should be searched.
     *
     * @var array
     */
    public static $search = [];



    /**
     * Get the query builder / paginator for the lens.
     *
     * @param  \Laravel\Nova\Http\Requests\LensRequest  $request
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return mixed
     */
    public static function query(LensRequest $request, $query)
    {
        $user = $request->user();

        return $request->withOrdering($request->withFilters(
            $query->where('user_id', $user->id)
        ));
    }

    /**
     * Get the fields available to the lens.
     *
     * @param  \Laravel\Nova\Http\Requests\NovaRequest  $request
     * @return array
     */
    public function fields(NovaRequest $request)
    {
        return [
            ID::make(__('ID'), 'id')->sortable(),
            BelongsTo::make('Milestone'),
            BelongsTo::make('Project')->searchable(),
            Text::make('Name')
                ->sortable()
                ->rules('required', 'max:255'),
            Text::make('SAL', function () {
                return $this->wip();
            }),
            Status::make('Status')
                ->loadingWhen(['status' => 'project'])
                ->failedWhen(['status' => 'rejected'])
        ];
    }

    /**
     * Get the cards available on the lens.
     *
     * @param  \Laravel\Nova\Http\Requests\NovaRequest  $request
     * @return array
     */
    public function cards(NovaRequest $request)
    {
        return [];
    }

    /**
     * Get the filters available for the lens.
     *
     * @param  \Laravel\Nova\Http\Requests\NovaRequest  $request
     * @return array
     */
    public function filters(NovaRequest $request)
    {
        return [
            new MilestoneFilter,
            new ProjectFilter,
            new EpicStatusFilter,
        ];
    }

    /**
     * Get the actions available on the lens.
     *
     * @param  \Laravel\Nova\Http\Requests\NovaRequest  $request
     * @return array
     */
    public function actions(NovaRequest $request)
    {
        return parent::actions($request);
    }

    /**
     * Get the URI key for the lens.
     *
     * @return string
     */
    public function uriKey()
    {
        return 'my-epic-lens';
    }

    public function name()
    {
        return 'My Epics';
    }
}