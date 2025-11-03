<?php

namespace App\Nova\Filters;

use Laravel\Nova\Filters\BooleanFilter;
use Laravel\Nova\Http\Requests\NovaRequest;

class StoryWithoutTagsFilter extends BooleanFilter
{
    /**
     * The displayable name of the filter.
     *
     * @var string
     */
    public $name = 'Senza Tag';

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
        if (!$value) {
            return $query;
        }

        if (isset($value['without-tags']) && $value['without-tags']) {
            return $query->whereDoesntHave('tags');
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
            'Senza Tag' => 'without-tags',
        ];
    }

    /**
     * Get the displayable name of the filter.
     *
     * @return string
     */
    public function name()
    {
        return __('Senza Tag');
    }
}
