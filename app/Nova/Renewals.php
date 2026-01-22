<?php

namespace App\Nova;

use App\Enums\UserRole;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Laravel\Nova\Fields\Badge;
use Laravel\Nova\Fields\Currency;
use Laravel\Nova\Fields\Date;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\NovaRequest;

class Renewals extends Customer
{
    /**
     * The single value that should be used to represent the resource when being displayed.
     *
     * @var string
     */
    public static $title = 'name';

    /**
     * Get the displayable label of the resource.
     *
     * @return string
     */
    public static function label()
    {
        return __('Renewals');
    }

    /**
     * Get the displayable singular label of the resource.
     *
     * @return string
     */
    public static function singularLabel()
    {
        return __('Renewal');
    }

    /**
     * Get the URI key for the resource.
     *
     * @return string
     */
    public static function uriKey()
    {
        return 'renewals';
    }

    /**
     * Determine if this resource is available for navigation.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return bool
     */
    public static function availableForNavigation(Request $request)
    {
        if ($request->user() == null) {
            return false;
        }

        return $request->user()->hasRole(UserRole::Admin) || $request->user()->hasRole(UserRole::Manager);
    }

    /**
     * Build an "index" query for the given resource.
     * Shows only customers that have a contract_expiration_date.
     *
     * @param  \Laravel\Nova\Http\Requests\NovaRequest  $request
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public static function indexQuery(NovaRequest $request, $query)
    {
        return $query->whereNotNull('contract_expiration_date');
    }

    /**
     * Get the fields displayed by the resource.
     * Shows only the required columns in the index: Customer (full name), Email,
     * Contract Expiration Date, Days Until Expiration, Contract Value, Contract Status
     *
     * @param  \Laravel\Nova\Http\Requests\NovaRequest  $request
     * @return array
     */
    public function fields(NovaRequest $request)
    {
        // If we are in the index view, show only the required fields
        if ($request->isResourceIndexRequest()) {
            return [
                // Customer field (full name only, clickable)
                Text::make('Customer', function () {
                    $fullName = $this->full_name;
                    $id = $this->id;
                    if (isset($fullName)) {
                        $fullName = wordwrap($fullName, 40, "\n", true);
                        $fullName = explode("\n", $fullName);
                        $fullName = implode("</br>", $fullName);
                        $url = '/resources/customers/' . $id;
                        return '<a href="' . $url . '" class="link-default">' . $fullName . '</a>';
                    }
                    return '-';
                })->asHtml()
                    ->sortable('full_name'),

                // Email field
                Text::make('Email', function () {
                    $emails = $this->email;
                    if (isset($emails)) {
                        //get the mails by exploding the string by comma or space
                        $mails = preg_split("/[\s,]+/", $this->email);
                        //add a mailto link to each mail
                        foreach ($mails as $key => $mail) {
                            $mails[$key] = "<a style='color:blue;' href='mailto:$mail'>$mail</a>";
                        }
                        $mails = implode(", </br>", $mails);
                        return $mails;
                    }
                    return '-';
                })->asHtml()
                    ->sortable('email'),

                // Contract Expiration Date
                Date::make(__('Contract Expiration Date'), 'contract_expiration_date')
                    ->sortable()
                    ->nullable(),

                // Days Until Expiration
                Text::make(__('Days Until Expiration'), function () {
                    if (!$this->contract_expiration_date) {
                        return '-';
                    }

                    $expirationDate = Carbon::parse($this->contract_expiration_date);
                    $today = Carbon::today();
                    $days = $today->diffInDays($expirationDate, false);

                    if ($days < 0) {
                        return abs($days) . ' ' . __('days ago');
                    } elseif ($days == 0) {
                        return __('Today');
                    } else {
                        return $days . ' ' . __('days');
                    }
                })
                    ->sortable('contract_expiration_date'),

                // Contract Value
                Currency::make(__('Contract Value'), 'contract_value')
                    ->sortable()
                    ->currency('EUR')
                    ->nullable(),

                // Contract Status
                Badge::make(__('Contract Status'), function () {
                    $expirationDate = Carbon::parse($this->contract_expiration_date);
                    $today = Carbon::today();
                    $daysUntilExpiration = $today->diffInDays($expirationDate, false);

                    if ($daysUntilExpiration < 0) {
                        return 'expired';
                    } elseif ($daysUntilExpiration <= \App\Models\Customer::EXPIRING_SOON_DAYS) {
                        return 'expiring_soon';
                    } else {
                        return 'active';
                    }
                })
                    ->map([
                        'expired' => 'danger',
                        'expiring_soon' => 'warning',
                        'active' => 'success',
                    ])
                    ->labels([
                        'expired' => __('Expired'),
                        'expiring_soon' => __('Expiring Soon'),
                        'active' => __('Active'),
                    ])
                    ->sortable('contract_expiration_date'),
            ];
        }

        // For other views (detail, form), use parent fields
        return parent::fields($request);
    }

    /**
     * Get the cards available for the request.
     *
     * @param  \Laravel\Nova\Http\Requests\NovaRequest  $request
     * @return array
     */
    public function cards(NovaRequest $request)
    {
        return [
            (new Metrics\ContractsByStatus)->width('full'),
            new Metrics\TotalActiveContracts,
            new Metrics\TotalExpiringContracts,
            new Metrics\TotalExpiredContracts,
        ];
    }

    /**
     * Get the filters available for the resource.
     *
     * @param  \Laravel\Nova\Http\Requests\NovaRequest  $request
     * @return array
     */
    public function filters(NovaRequest $request)
    {
        return [
            new Filters\ContractStatusFilter,
        ];
    }

    /**
     * Determine if this resource is searchable.
     *
     * @return bool
     */
    public static function searchable()
    {
        return true;
    }

    /**
     * Determine if the user can create resources.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return bool
     */
    public static function authorizedToCreate(Request $request)
    {
        return false;
    }

    /**
     * Determine if the user can update the resource.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return bool
     */
    public function authorizedToUpdate(Request $request)
    {
        return false;
    }

    /**
     * Determine if the user can delete the resource.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return bool
     */
    public function authorizedToDelete(Request $request)
    {
        return false;
    }

    /**
     * Determine if the user can view any resources.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return bool
     */
    public static function authorizedToViewAny(Request $request)
    {
        if ($request->user() == null) {
            return false;
        }

        return $request->user()->hasRole(UserRole::Admin) || $request->user()->hasRole(UserRole::Manager);
    }
}
