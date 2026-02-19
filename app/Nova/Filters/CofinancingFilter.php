<?php

namespace App\Nova\Filters;

use Laravel\Nova\Filters\Filter;
use Laravel\Nova\Http\Requests\NovaRequest;

class CofinancingFilter extends Filter
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
    public $name = 'Cofin.';

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
        if ($value === 'yes') {
            // "Sì" significa cofinanziamento > 0
            return $query->whereNotNull('cofinancing_quota')->where('cofinancing_quota', '>', 0);
        } elseif ($value === 'no') {
            // "NO" significa cofinanziamento = 0 oppure vuoto (null)
            return $query->where(function ($q) {
                $q->whereNull('cofinancing_quota')->orWhere('cofinancing_quota', 0);
            });
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
            'Sì' => 'yes',
            'No' => 'no',
        ];
    }
}
