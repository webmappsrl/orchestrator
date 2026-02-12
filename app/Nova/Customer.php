<?php

namespace App\Nova;

use Eminiarts\Tabs\Tab;
use Laravel\Nova\Panel;
use Eminiarts\Tabs\Tabs;
use Illuminate\Database\Eloquent\Builder;
use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Date;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Fields\Number;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Fields\Boolean;
use Laravel\Nova\Fields\HasMany;
use Laravel\Nova\Fields\Currency;
use Eminiarts\Tabs\Traits\HasTabs;
use Datomatic\NovaMarkdownTui\MarkdownTui;
use Laravel\Nova\Http\Requests\NovaRequest;
use App\Nova\Filters\CustomerOwnerFilter;
use App\Nova\Filters\CustomerStatusFilter;
use App\Nova\Actions\EditCustomerStatus;
use App\Enums\CustomerStatus;
use App\Enums\UserRole;
use Datomatic\NovaMarkdownTui\Enums\EditorType;
use Illuminate\Database\Eloquent\Model;
use Laravel\Nova\Fields\Textarea;
use App\Nova\QuoteNoFilter;
use Ebess\AdvancedNovaMediaLibrary\Fields\Files;

class Customer extends Resource
{
    use HasTabs;
    /**
     * The model the resource corresponds to.
     *
     * @var class-string<\App\Models\Customer>
     */
    public static $model = \App\Models\Customer::class;

    /**
     * The single value that should be used to represent the resource when being displayed.
     *
     * @var string
     */
    public static $title = 'name';

    /**
     * The columns that should be searched.
     *
     * @var array
     */
    public static $search = [
        'name',
        'domain_name',
        'full_name',
        'acronym',
        'email',
        'owner.name',
    ];
    public static function label()
    {
        return __('Customers');
    }

    /**
     * Get the plural label of the resource.
     *
     * @return string
     */
    public static function singularLabel()
    {
        return __('Customer');
    }
    /**
     * Get the fields displayed by the resource.
     *
     * @param  \Laravel\Nova\Http\Requests\NovaRequest  $request
     * @return array
     */
    public function fields(NovaRequest $request)
    {
        $title = 'Customer Details:' . $this->name;
        return [
            ID::make()->sortable(),
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
                    //get the mails by exploding the string by comma or space
                    $mails = preg_split("/[\s,]+/", $this->email);
                    //add a mailto link to each mail
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
                return $string;
            })->asHtml()
                ->hideWhenCreating()
                ->hideWhenUpdating()
                ->showOnPreview(function (NovaRequest $request, $resource) {
                    return $resource->exists;
                }),
            Text::make('Scores', function () {
                $string = '';
                $scoreCash = $this->score_cash;
                $scorePain = $this->score_pain;
                $scoreBusiness = $this->score_business;
                if (isset($scoreCash)) {
                    $string .= 'Cash: ' . $scoreCash;
                }
                if (isset($scorePain)) {
                    $string .= '</br> Pain: ' . $scorePain;
                }
                if (isset($scoreBusiness)) {
                    $string .= '</br> Business: ' . $scoreBusiness;
                }
                return $string;
            })->asHtml()
                ->hideWhenCreating()
                ->hideWhenUpdating()
                ->hideFromIndex()
                ->hideFromDetail(),
            Text::make('Name')
                ->sortable()
                ->rules('required', 'max:255')
                ->creationRules('unique:customers,name')
                ->onlyOnForms(),
            Text::make('Full Name', 'full_name')
                ->sortable()
                ->nullable()
                ->onlyOnForms(),
            Textarea::make('Heading', 'heading')
                ->nullable()
                ->hideFromIndex(),
            Text::make('Acronym', 'acronym')
                ->sortable()
                ->nullable()
                ->onlyOnForms(),
            Select::make(__('Status'), 'status')->options(
                collect(CustomerStatus::cases())->mapWithKeys(function ($status) {
                    return [$status->value => __(ucfirst($status->value))];
                })->toArray()
            )->sortable()
                ->default(CustomerStatus::Unknown->value)
                ->displayUsing(function ($value) {
                    return __(ucfirst($value));
                }),
            BelongsTo::make(__('Owner'), 'owner', User::class)
                ->sortable()
                ->searchable()
                ->nullable()
                ->relatableQueryUsing(function (NovaRequest $request, Builder $query) {
                    $query->whereJsonContains('roles', UserRole::Admin->value)
                        ->orWhereJsonContains('roles', UserRole::Manager->value);
                })
                ->help(__('User who manages this customer (Admin or Manager).')),
            Text::make('HS', 'hs_id')
                ->sortable()
                ->nullable()
                ->hideFromIndex(),
            Text::make('Domain Name', 'domain_name')
                ->sortable()
                ->nullable()
                ->hideFromIndex(),
            MarkdownTui::make('Migration Note', 'migration_note')
                ->hideFromIndex()
                ->initialEditType(EditorType::MARKDOWN),
            Text::make('Contact emails', 'email')
                ->onlyOnForms(),
            Text::make(__('Phone'), 'phone')
                ->nullable()
                ->rules('nullable', 'regex:/^[0-9]+$/', 'max:30')
                ->help(__('Digits only (no spaces or symbols).'))
                ->onlyOnForms(),
            Text::make(__('Mobile phone'), 'mobile_phone')
                ->nullable()
                ->rules('nullable', 'regex:/^\+?[0-9]+$/', 'max:30')
                ->help(__('Digits only; optional leading + for country code.'))
                ->onlyOnForms(),
            Boolean::make('Subs.', 'has_subscription')
                ->sortable()
                ->nullable()->hideFromIndex(),
            Currency::make('S/Amount', 'subscription_amount')
                ->sortable()
                ->currency('EUR')
                ->nullable()->hideFromIndex(),
            Date::make('S/Payment', 'subscription_last_payment')
                ->sortable()
                ->nullable()->hideFromIndex(),
            Number::make('S/year', 'subscription_last_covered_year')
                ->sortable()
                ->nullable()
                ->rules('nullable', 'integer')->hideFromIndex(),
            Text::make('S/invoice', 'subscription_last_invoice')
                ->sortable()
                ->nullable()->hideFromIndex(),
            Date::make(__('Contract Expiration Date'), 'contract_expiration_date')
                ->sortable()
                ->nullable()
                ->hideFromIndex(),
            Currency::make(__('Contract Value'), 'contract_value')
                ->sortable()
                ->currency('EUR')
                ->nullable()
                ->hideFromIndex(),
            MarkdownTui::make('Notes', 'notes')
                ->initialEditType(EditorType::MARKDOWN)
                ->hideFromIndex(),
            Files::make(__('Invoices'), 'documents')
                ->singleMediaRules('mimetypes:application/pdf')
                ->hideFromIndex(),
            new Tabs('Relationships', [
                Tab::make(__('Projects'), [
                    HasMany::make(__('Projects'), 'projects', Project::class),
                ]),
                Tab::make('Quotes', [
                    HasMany::make('Quotes', 'quotes', QuoteNoFilter::class),
                ]),
                Tab::make('Deadlines', [
                    HasMany::make('Deadlines', 'deadlines', Deadline::class),
                ]),

            ]),
            Number::make('Score Cash', 'score_cash')
                ->sortable()
                ->nullable()
                ->onlyOnForms()
                ->hideFromIndex()
                ->hideFromDetail()
                ->hideWhenCreating()
                ->hideWhenUpdating(),
            Number::make('Score Pain', 'score_pain')
                ->sortable()
                ->nullable()
                ->onlyOnForms()
                ->hideFromIndex()
                ->hideFromDetail()
                ->hideWhenCreating()
                ->hideWhenUpdating(),
            Number::make('Score Business', 'score_business')
                ->sortable()
                ->nullable()
                ->onlyOnForms()
                ->hideFromIndex()
                ->hideFromDetail()
                ->hideWhenCreating()
                ->hideWhenUpdating(),

        ];
    }

    /**
     * Get the cards available for the request.
     *
     * @param  \Laravel\Nova\Http\Requests\NovaRequest  $request
     * @return array
     */
    public function cards(NovaRequest $request)
    {
        return [];
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
            new CustomerStatusFilter(),
            new CustomerOwnerFilter(),
        ];
    }

    /**
     * Get the lenses available for the resource.
     *
     * @param  \Laravel\Nova\Http\Requests\NovaRequest  $request
     * @return array
     */
    public function lenses(NovaRequest $request)
    {
        return [];
    }

    /**
     * Get the actions available for the resource.
     *
     * @param  \Laravel\Nova\Http\Requests\NovaRequest  $request
     * @return array
     */
    public function actions(NovaRequest $request)
    {
        return [
            new EditCustomerStatus(),
        ];
    }

    public function indexBreadcrumb()
    {
        return null;
    }
}
