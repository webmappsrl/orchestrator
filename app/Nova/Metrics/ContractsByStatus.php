<?php

namespace App\Nova\Metrics;

use App\Models\Customer;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Metrics\Partition;

class ContractsByStatus extends Partition
{
    /**
     * Calculate the value of the metric.
     *
     * @param  \Laravel\Nova\Http\Requests\NovaRequest  $request
     * @return mixed
     */
    public function calculate(NovaRequest $request)
    {
        $today = now()->startOfDay();
        $thirtyDaysFromNow = now()->addDays(Customer::EXPIRING_SOON_DAYS)->startOfDay();

        // Conta i contratti scaduti
        $expiredCount = Customer::whereNotNull('contract_expiration_date')
            ->where('contract_expiration_date', '<', $today)
            ->count();

        // Conta i contratti in scadenza (tra oggi e EXPIRING_SOON_DAYS giorni)
        $expiringSoonCount = Customer::whereNotNull('contract_expiration_date')
            ->where('contract_expiration_date', '>=', $today)
            ->where('contract_expiration_date', '<=', $thirtyDaysFromNow)
            ->count();

        // Conta i contratti attivi (oltre EXPIRING_SOON_DAYS giorni)
        $activeCount = Customer::whereNotNull('contract_expiration_date')
            ->where('contract_expiration_date', '>', $thirtyDaysFromNow)
            ->count();

        // Conta i contratti senza data ma con valore contratto
        $noDateCount = Customer::whereNull('contract_expiration_date')
            ->whereNotNull('contract_value')
            ->count();

        // Costruisci l'array dei risultati con le chiavi tradotte
        $results = [];

        if ($expiredCount > 0) {
            $results[__('Expired')] = $expiredCount;
        }

        if ($expiringSoonCount > 0) {
            $results[__('Expiring Soon')] = $expiringSoonCount;
        }

        if ($activeCount > 0) {
            $results[__('Active')] = $activeCount;
        }

        if ($noDateCount > 0) {
            $results[__('No Date')] = $noDateCount;
        }

        return $this->result($results)->colors($this->colors());
    }

    /**
     * Get the colors for the metric.
     *
     * @return array
     */
    public function colors()
    {
        return [
            __('Expired') => '#DC3545',      // danger (rosso)
            __('Expiring Soon') => '#FFC107', // warning (arancione)
            __('Active') => '#28A745',        // success (verde)
            __('No Date') => '#6C757D',       // secondary (grigio)
        ];
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
        return __('By Contract Status');
    }
}
