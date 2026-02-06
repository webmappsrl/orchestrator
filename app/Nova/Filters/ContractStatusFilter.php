<?php

namespace App\Nova\Filters;

use App\Models\Customer;
use Laravel\Nova\Filters\Filter;
use Laravel\Nova\Http\Requests\NovaRequest;

class ContractStatusFilter extends Filter
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
        $today = now()->startOfDay();
        $thirtyDaysFromNow = now()->addDays(Customer::EXPIRING_SOON_DAYS)->startOfDay();

        switch ($value) {
            case 'expired':
                return $query->whereNotNull('contract_expiration_date')
                    ->where('contract_expiration_date', '<', $today);

            case 'expiring_soon':
                return $query->whereNotNull('contract_expiration_date')
                    ->where('contract_expiration_date', '>=', $today)
                    ->where('contract_expiration_date', '<=', $thirtyDaysFromNow);

            case 'active':
                return $query->whereNotNull('contract_expiration_date')
                    ->where('contract_expiration_date', '>', $thirtyDaysFromNow);

            case 'no_date':
                return $query->whereNull('contract_expiration_date')
                    ->whereNotNull('contract_value');

            default:
                return $query;
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
            __('Expired') => 'expired',
            __('Expiring Soon') => 'expiring_soon',
            __('Active') => 'active',
            __('No Date') => 'no_date',
        ];
    }

    /**
     * Get the displayable name of the filter.
     *
     * @return string
     */
    public function name()
    {
        return __('Contract Status');
    }
}
