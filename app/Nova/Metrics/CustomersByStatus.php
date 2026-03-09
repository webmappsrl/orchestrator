<?php

namespace App\Nova\Metrics;

use App\Enums\CustomerStatus;
use App\Models\Customer;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Metrics\Value;

class CustomersByStatus extends Value
{
    public CustomerStatus $status;

    protected string $label;

    protected string $uriKey;

    public function __construct(CustomerStatus $status, ?string $label = null, ?string $uriKey = null)
    {
        $this->status = $status;
        $this->label = $label ?? __('Customers: ') . ucfirst(__($status->value));
        $this->uriKey = $uriKey ?? 'customers-status-' . $status->value;

        parent::__construct();
    }

    /**
     * Calculate the value of the metric.
     *
     * @param  \Laravel\Nova\Http\Requests\NovaRequest  $request
     * @return mixed
     */
    public function calculate(NovaRequest $request)
    {
        $query = Customer::query()->where('status', $this->status->value);

        return $this->result($query->count());
    }

    /**
     * Get the ranges available for the metric.
     *
     * @return array
     */
    public function ranges()
    {
        return [
            'ALL' => __('All Time'),
        ];
    }

    public function name()
    {
        return $this->label;
    }

    public function uriKey()
    {
        return $this->uriKey;
    }
}

