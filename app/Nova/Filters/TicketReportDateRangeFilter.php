<?php

namespace App\Nova\Filters;

use Laravel\Nova\Filters\Filter;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Filters\DateFilter;
use Carbon\Carbon;

class TicketReportDateRangeFilter extends Filter
{
    /**
     * The filter's component.
     *
     * @var string
     */
    public $component = 'select-filter';

    /**
     * The displayable name of the filter.
     *
     * @var string
     */
    public $name = 'Periodo Data';

    /**
     * Apply the filter to the given query.
     * Filters by date range on created_at, released_at, or done_at
     *
     * @param  \Laravel\Nova\Http\Requests\NovaRequest  $request
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  mixed  $value
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function apply(NovaRequest $request, $query, $value)
    {
        // Per ora, il filtro non fa nulla perché useremo query parameters direttamente
        // Questo filtro può essere esteso in futuro per aggiungere filtri custom
        return $query;
    }

    /**
     * Get the filter's available options.
     * For date range, we'll use query parameters instead
     *
     * @param  \Laravel\Nova\Http\Requests\NovaRequest  $request
     * @return array
     */
    public function options(NovaRequest $request)
    {
        return [];
    }
}
