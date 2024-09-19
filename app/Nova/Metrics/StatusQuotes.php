<?php

namespace App\Nova\Metrics;

use App\Models\Quote;
use Laravel\Nova\Metrics\Value;
use Laravel\Nova\Http\Requests\NovaRequest;

class StatusQuotes extends Value
{

    protected $validStatuses = [];
    protected $label = 'Total price of quotes';

    /**
     * Create a new metric instance.
     *
     * @param  array  $validStatuses
     */
    public function __construct($label = 'Total price of quotes', array $validStatuses = [])
    {
        $this->validStatuses = $validStatuses;
        $this->label = __($label);
    }

    /**
     * Calculate the value of the metric.
     *
     * @param  \Laravel\Nova\Http\Requests\NovaRequest  $request
     * @return mixed
     */
    public function calculate(NovaRequest $request)
    {
        $wonQuotes = Quote::whereIn('status', $this->validStatuses)->get();
        //sum all of the total price of the quotes
        $totalPrice = $wonQuotes->sum(function ($quote) {
            return $quote->getTotalPrice() + $quote->getTotalRecurringPrice() + $quote->getTotalAdditionalServicesPrice();
        });

        return $this->result($totalPrice)->currency('€')->format([
            'thousandSeparated' => true,
            'mantissa' => 2,
        ]);
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

    public function name()
    {
        return $this->label; // Use the provided label
    }

    /**
     * Determine the amount of time the results of the metric should be cached.
     *
     * @return \DateTimeInterface|\DateInterval|float|int|null
     */
    public function cacheFor() {}
}
