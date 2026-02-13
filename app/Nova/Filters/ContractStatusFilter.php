<?php

namespace App\Nova\Filters;

use App\Enums\ContractStatus;
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
        $status = ContractStatus::tryFrom($value);
        if ($status === null) {
            return $query;
        }

        $today = now()->startOfDay();
        $thirtyDaysFromNow = now()->addDays(Customer::EXPIRING_SOON_DAYS)->startOfDay();

        return match ($status) {
            ContractStatus::Expired => $query->whereNotNull('contract_expiration_date')
                ->where('contract_expiration_date', '<', $today),

            ContractStatus::ExpiringSoon => $query->whereNotNull('contract_expiration_date')
                ->where('contract_expiration_date', '>=', $today)
                ->where('contract_expiration_date', '<=', $thirtyDaysFromNow),

            ContractStatus::Active => $query->whereNotNull('contract_expiration_date')
                ->where('contract_expiration_date', '>', $thirtyDaysFromNow),

            ContractStatus::NoDate => $query->whereNull('contract_expiration_date')
                ->whereNotNull('contract_value'),
        };
    }

    /**
     * Get the filter's available options.
     *
     * @param  \Laravel\Nova\Http\Requests\NovaRequest  $request
     * @return array
     */
    public function options(NovaRequest $request)
    {
        return collect(ContractStatus::cases())->mapWithKeys(fn (ContractStatus $s) => [$s->label() => $s->value])->toArray();
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
