<?php

namespace App\Nova\Metrics;

use App\Models\Customer;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Metrics\Value;

class TotalExpiringContracts extends Value
{
    /**
     * The element's icon (empty = no icon box).
     *
     * @var string
     */
    public $icon = '';

    /**
     * Calculate the value of the metric.
     * Total value in euro of expiring contracts and count of expiring contracts.
     *
     * @param  \Laravel\Nova\Http\Requests\NovaRequest  $request
     * @return mixed
     */
    public function calculate(NovaRequest $request)
    {
        $today = now()->startOfDay();
        $thirtyDaysFromNow = now()->addDays(Customer::EXPIRING_SOON_DAYS)->startOfDay();

        $query = Customer::whereNotNull('contract_expiration_date')
            ->where('contract_expiration_date', '>=', $today)
            ->where('contract_expiration_date', '<=', $thirtyDaysFromNow);

        $total = (clone $query)->sum('contract_value');
        $count = (clone $query)->count();

        return $this->result($total ?? 0)
            ->format('0,0.00')
            ->suffix(' euro (' . $count . ' ' . __('total') . ')')
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
        return __('Total Expiring Contracts');
    }
}
