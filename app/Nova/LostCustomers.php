<?php

namespace App\Nova;

use App\Enums\CustomerStatus;
use App\Nova\Filters\ContractStatusFilter;
use App\Nova\Filters\CustomerOwnerFilter;
use Illuminate\Http\Request;
use Laravel\Nova\Http\Requests\NovaRequest;

class LostCustomers extends Customer
{
    /**
     * Resource label shown in the Nova menu.
     */
    public static function label()
    {
        return __('Lost Customers');
    }

    public static function singularLabel()
    {
        return __('Lost Customer');
    }

    public static function authorizedToCreate(Request $request)
    {
        return false;
    }

    /**
     * Show only customers in "Perso" (Lost) status.
     */
    public static function indexQuery(NovaRequest $request, $query)
    {
        return $query->where('status', CustomerStatus::Lost->value);
    }

    /**
     * Same filters as {@see Customer} except Status: the list is already only "Lost".
     */
    public function filters(NovaRequest $request)
    {
        return [
            new CustomerOwnerFilter(),
            new ContractStatusFilter(),
        ];
    }

    /**
     * No metrics/cards for this archived interface.
     */
    public function cards(NovaRequest $request)
    {
        return [];
    }

    /**
     * After saving the edit form, redirect to the standard `customers` edit page.
     */
    public static function redirectAfterUpdate(NovaRequest $request, $resource)
    {
        return '/resources/customers/'.$resource->getKey();
    }
}

