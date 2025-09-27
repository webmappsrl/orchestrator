<?php

namespace App\Nova\Filters;

use Laravel\Nova\Filters\Filter;
use Laravel\Nova\Http\Requests\NovaRequest;

class TerritorialScopeFilter extends Filter
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
    public $name = 'Scope Territoriale';

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
        return $query->where('territorial_scope', $value);
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
            'Cooperazione' => 'cooperation',
            'Europeo' => 'european',
            'Nazionale' => 'national',
            'Regionale' => 'regional',
            'Territoriale' => 'territorial',
            'Comuni' => 'municipalities',
        ];
    }
}
