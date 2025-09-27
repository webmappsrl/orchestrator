<?php

namespace App\Nova\Filters;

use Laravel\Nova\Filters\Filter;
use Laravel\Nova\Http\Requests\NovaRequest;

class ExpiredFilter extends Filter
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
    public $name = 'Stato Scadenza';

    /**
     * Apply the filter to the given query.
     *
     * @param  \Laravel\Nova\Http\Requests\NovaRequest  $request
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  mixed  $value
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function apply(NovaRequest $request, $query, $value)
    {
        if ($value === 'active') {
            return $query->where('deadline', '>=', now());
        } elseif ($value === 'expired') {
            return $query->where('deadline', '<', now());
        }

        return $query;
    }

    /**
     * Get the filter's available options.
     *
     * @param  \Laravel\Nova\Http\Requests\NovaRequest  $request
     * @return array
     */
    public function options(NovaRequest $request)
    {
        return [
            'Attive' => 'active',
            'Scadute' => 'expired',
        ];
    }
}
