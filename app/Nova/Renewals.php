<?php

namespace App\Nova;

use App\Enums\ContractStatus;
use App\Enums\UserRole;
use App\Models\Customer as CustomerModel;
use App\Nova\Filters\ContractStatusFilter;
use App\Nova\Filters\CustomerOwnerFilter;
use App\Nova\Filters\CustomerStatusFilter;
use App\Nova\Metrics\ContractsByStatus;
use App\Nova\Metrics\TotalActiveContracts;
use App\Nova\Metrics\TotalExpiredContracts;
use App\Nova\Metrics\TotalExpiringContracts;
use App\Nova\Metrics\TotalSubscriptionValue;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Laravel\Nova\Fields\Badge;
use Laravel\Nova\Fields\BelongsTo;
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
     * Shows customers that have a contract_expiration_date or a contract_value.
     *
     * @param  \Laravel\Nova\Http\Requests\NovaRequest  $request
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public static function indexQuery(NovaRequest $request, $query)
    {
        return $query->where(function ($q) {
            $q->whereNotNull('contract_expiration_date')
                ->orWhereNotNull('contract_value');
        });
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
                // Customer field: same structure as Customer index view (name, full_name, email, phone, mobile_phone)
                Text::make('Customer', function () {
                    $string = '';
                    $name = $this->name;
                    $fullName = $this->full_name;
                    $emails = $this->email;
                    $acronym = $this->acronym;
                    $phone = $this->phone;
                    $mobilePhone = $this->mobile_phone;

                    if (isset($name) & isset($acronym)) {
                        $string .= $name . ' (' . $acronym . ')';
                    } elseif (isset($name)) {
                        $string .= $name;
                    }
                    if (isset($fullName)) {
                        $fullName = wordwrap($fullName, 40, "\n", true);
                        $fullName = explode("\n", $fullName);
                        $fullName = implode("</br>", $fullName);
                        $string .= '</br>' . $fullName;
                    }
                    if (isset($emails)) {
                        $mails = preg_split("/[\s,]+/", $this->email);
                        foreach ($mails as $key => $mail) {
                            $mails[$key] = "<a style='color:blue;' href='mailto:$mail'>$mail</a>";
                        }
                        $mails = implode(", </br>", $mails);
                        $string .= '</br> ' . $mails;
                    }
                    if (isset($phone) && trim((string) $phone) !== '') {
                        $string .= '</br>' . e($phone);
                    }
                    if (isset($mobilePhone) && trim((string) $mobilePhone) !== '') {
                        $string .= '</br>' . e($mobilePhone);
                    }
                    return $string ?: '-';
                })->asHtml()
                    ->sortable('name'),

                // Customer Status (reuse parent statusField for DRY)
                $this->statusField($request),

                // Owner
                BelongsTo::make(__('Owner'), 'owner', User::class)
                    ->sortable()
                    ->nullable(),

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
                    return ContractStatus::fromExpirationDate(
                        $this->contract_expiration_date,
                        CustomerModel::EXPIRING_SOON_DAYS
                    )->value;
                })
                    ->map(collect(ContractStatus::cases())->mapWithKeys(fn (ContractStatus $s) => [$s->value => $s->badgeStyle()])->toArray())
                    ->labels(collect(ContractStatus::cases())->mapWithKeys(fn (ContractStatus $s) => [$s->value => $s->label()])->toArray())
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
            (new ContractsByStatus)->width('1/2'),
            (new TotalSubscriptionValue)->width('1/2'),
            new TotalActiveContracts,
            new TotalExpiringContracts,
            new TotalExpiredContracts,
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
            new CustomerStatusFilter,
            new CustomerOwnerFilter,
            new ContractStatusFilter,
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
