<?php

namespace App\Nova\Metrics;

use App\Models\Customer;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Metrics\Value;

class TotCustomers extends Value
{
    /**
     * Calculate the value of the metric.
     *
     * @param  \Laravel\Nova\Http\Requests\NovaRequest  $request
     * @return mixed
     */
    public function calculate(NovaRequest $request)
    {
        return $this->count($request, Customer::class);
    }

    /**
     * Get the ranges available for the metric.
     *
     * @return array
     */
    public function ranges()
    {
        // return [
        //     30 => __('30 Days'),
        //     60 => __('60 Days'),
        //     365 => __('365 Days'),
        //     'TODAY' => __('Today'),
        //     'MTD' => __('Month To Date'),
        //     'QTD' => __('Quarter To Date'),
        //     'YTD' => __('Year To Date'),
        // ];
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

    public function name()
    {
        return __('Customers');
    }
}
