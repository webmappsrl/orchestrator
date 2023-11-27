<?php

namespace App\Nova\Filters;

use App\Models\Customer;
use App\Models\User;
use Laravel\Nova\Filters\Filter;
use Laravel\Nova\Http\Requests\NovaRequest;

class CustomerStoryFilter extends Filter
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
        $customer = Customer::where('id', $value)->first();
        return $query->where('creator_id', $value);
    }

    /**
     * Get the filter's available options.
     *
     * @param  \Laravel\Nova\Http\Requests\NovaRequest  $request
     * @return array
     */
    public function options(NovaRequest $request)
    {
        $debug =
            User::whereJsonContains('roles', ['customer'])->orderBy('name')
            ->pluck('id', 'name')->toArray();
        return
            User::whereJsonContains('roles', ['customer'])->orderBy('name')
            ->pluck('id', 'name')->toArray();
    }

    public function name()
    {
        return 'Customer';
    }
}
