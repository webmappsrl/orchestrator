<?php

namespace App\Nova\Filters;

use Laravel\Nova\Filters\Filter;
use Laravel\Nova\Http\Requests\NovaRequest;
use Carbon\Carbon;

class QuarterTagFilter extends Filter
{
    /**
     * The filter's component.
     *
     * @var string
     */
    public $component = 'select-filter';

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
        return $query->where('name', 'like', "%{$value}%");
    }

    /**
     * Get the filter's available options.
     *
     * @param  \Laravel\Nova\Http\Requests\NovaRequest  $request
     * @return array
     */
    public function options(NovaRequest $request)
    {
        $now = Carbon::now();
        $currentYear = $now->year;
        $suffixYear = substr((string)$currentYear, -2);
        $currentQuarter = ceil($now->month / 3);

        $quarters = [];

        for ($i = 1; $i <= 4; $i++) {

            if ($currentQuarter == 0) {
                $currentQuarter = 4;
                $currentYear--;
                $suffixYear = substr((string)$currentYear, -2);
            }

            $label = "{$suffixYear}Q{$currentQuarter}";
            $quarters[$label] = $label;
            $currentQuarter--;
        }

        return $quarters;
    }

    public function name()
    {
        return __('Quarter Tag Filter');
    }
}
