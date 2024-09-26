<?php

namespace App\Nova\Metrics;

use App\Models\Quote;
use App\Enums\QuoteStatus;
use Laravel\Nova\Metrics\Value;
use Laravel\Nova\Http\Requests\NovaRequest;

class SentQuotes extends Value
{
    public function name()
    {
        return __('Sent Quotes');
    }
    /**
     * Calculate the value of the metric.
     *
     * @param  \Laravel\Nova\Http\Requests\NovaRequest  $request
     * @return mixed
     */
    public function calculate(NovaRequest $request)
    {
        $sentQuotes = Quote::where('status', QuoteStatus::Sent->value)->get();
        //sum all of the total price of the quotes
        $totalPrice = $sentQuotes->sum(function ($quote) {
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
}
