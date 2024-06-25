<?php

namespace App\Nova\Filters;

use Laravel\Nova\Filters\BooleanFilter;
use Laravel\Nova\Http\Requests\NovaRequest;

class CustomerStoryWithDeadlineFilter extends BooleanFilter
{

    public $name = 'Deadline';
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
        //deadline and stories have a morphtomany relationship
        //we can use whereHas to filter stories that have a deadline
        //or don't have a deadline
        if (!$value) return $query;

        if ($value['with-deadline']) {
            return $query->whereHas('deadlines');
        } else if ($value['without-deadline']) {
            return $query->whereDoesntHave('deadlines');
        }
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
            'With Deadline' => 'with-deadline',
            'Without Deadline' => 'without-deadline',
        ];
    }
    /**
     * Set the default value of the filter.
     *
     * @return array
     */
    public function default()
    {
        return [
            'with-deadline' => false,
            'without-deadline' => true
        ];
    }
}
