<?php

namespace App\Nova\Metrics;

use App\Models\Customer;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Metrics\Value;

class TotalSubscriptionValue extends Value
{
    /**
     * The element's icon (empty = no icon box).
     *
     * @var string
     */
    public $icon = '';

    /**
     * Calculate the value of the metric.
     * Total value in euro of all subscriptions and count of subscriptions.
     *
     * @param  \Laravel\Nova\Http\Requests\NovaRequest  $request
     * @return mixed
     */
    public function calculate(NovaRequest $request)
    {
        $query = Customer::whereNotNull('contract_expiration_date');
        $total = (clone $query)->sum('contract_value');
        $count = (clone $query)->count();

        return $this->result($total ?? 0)
            ->currency('€')
            ->format('0,0.00')
            ->suffix('(' . $count . ' ' . __('total') . ')')
            ->withoutSuffixInflection();
    }

    /**
     * Get the ranges available for the metric.
     *
     * @return array
     */
    public function ranges()
    {
        return [];
    }

    /**
     * Determine the amount of time the results of the metric should be cached.
     *
     * @return \DateTimeInterface|\DateInterval|float|int|null
     */
    public function cacheFor()
    {
        // return now()->addMinutes(5);
    }

    /**
     * Get the displayable name of the metric.
     *
     * @return string
     */
    public function name()
    {
        return __('Total value of subscriptions');
    }
}
