<?php

namespace App\Nova;

use App\Enums\QuoteStatus;
use App\Nova\Actions\DuplicateQuote;
use App\Nova\Metrics\NewQuotes;
use App\Nova\Metrics\SentQuotes;
use App\Nova\Metrics\WonQuotes;
use Laravel\Nova\Panel;
use Laravel\Nova\Fields\ID;
use Illuminate\Http\Request;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Fields\Number;
use Laravel\Nova\Fields\Currency;
use Laravel\Nova\Fields\KeyValue;
use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Fields\BelongsToMany;
use Datomatic\NovaMarkdownTui\MarkdownTui;
use Laravel\Nova\Http\Requests\NovaRequest;
use Datomatic\NovaMarkdownTui\Enums\EditorType;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Fields\Status;
use Laravel\Nova\Fields\Textarea;
use Laravel\Nova\Fields\Trix;

class Quote extends Resource
{

    /**
     * The model the resource corresponds to.
     *
     * @var class-string<\App\Models\Quote>
     */
    public static $model = \App\Models\Quote::class;

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
        'id', 'title'
    ];

    /**
     * Get the fields displayed by the resource.
     *
     * @param  \Laravel\Nova\Http\Requests\NovaRequest  $request
     * @return array
     */
    public function fields(NovaRequest $request)
    {
        return [
            ID::make()->sortable(),
            Text::make('Title')
                ->displayUsing(function ($name, $a, $b) {
                    $wrappedName = wordwrap($name, 50, "\n", true);
                    $htmlName = str_replace("\n", '<br>', $wrappedName);
                    return $htmlName;
                })
                ->asHtml(),
            Status::make('Status')->loadingWhen(['new', 'sent'])->failedWhen(['closed lost']),
            Select::make('Status')->options([
                'new' => QuoteStatus::New,
                'sent' => QuoteStatus::Sent,
                'closed lost' => QuoteStatus::Closed_Lost,
                'closed won' => QuoteStatus::Closed_Won,
                'partially paid' =>  QuoteStatus::Partially_Paid,
                'paid' =>  QuoteStatus::Paid,
            ])->onlyOnForms(),
            Text::make('Google Drive Url', 'google_drive_url')->nullable()->hideFromIndex()->displayUsing(function () {
                return '<a class="link-default" target="_blank" href="' . $this->google_drive_url . '">' . $this->google_drive_url . '</a>';
            })->asHtml(),
            new Panel('NOTES', [
                MarkdownTui::make('Notes')
                    ->hideFromIndex()
                    ->initialEditType(EditorType::MARKDOWN)
                    ->nullable()
            ]),
            BelongsTo::make('Customer')
                ->filterable()
                ->searchable(),
            BelongsToMany::make('Products')->fields(function () {
                return [
                    Number::make('Quantity', 'quantity')->rules('required', 'numeric', 'min:1')
                        ->default(1)
                ];
            })
                ->searchable(),
            BelongsToMany::make('Recurring Products')->fields(function () {
                return [
                    Number::make('Quantity', 'quantity')->rules('required', 'numeric', 'min:1')
                        ->default(1)
                ];
            })
                ->searchable(),
            Currency::make('Products')
                ->currency('EUR')
                ->locale('it')
                ->exceptOnForms()
                ->displayUsing(function () {
                    $price = empty($this->products) ? 0 : $this->getTotalPrice();
                    return number_format($price, 2, ',', '.') . ' €';
                })->sortable(),
            Currency::make('Recurring')
                ->currency('EUR')
                ->locale('it')
                ->exceptOnForms()
                ->displayUsing(function () {
                    $price = empty($this->recurringProducts) ? 0 : $this->getTotalRecurringPrice();
                    return number_format($price, 2, ',', '.') . ' €';
                })->sortable(),
            Currency::make('Total')
                ->currency('EUR')
                ->locale('it')
                ->exceptOnForms()
                ->displayUsing(function () {
                    $quotePrice = $this->getTotalPrice() + $this->getTotalRecurringPrice() + $this->getTotalAdditionalServicesPrice();
                    return number_format($quotePrice, 2, ',', '.') . ' €';
                })->sortable(),
            Currency::make('Discount')
                ->currency('EUR')
                ->locale('it')
                ->hideFromIndex()
                ->displayUsing(function () {
                    return number_format($this->discount, 2, ',', '.') . ' €';
                }),
            KeyValue::make('Additional Services', 'additional_services')
                ->hideFromIndex()
                ->rules('json')
                ->keyLabel('Description')
                ->valueLabel('Price (€)')
                ->help('Add additional services to the quote. The price must be with "." as decimal separator. (Example: 100.00)'),
            Currency::make('Additional Services Total Price')
                ->currency('EUR')
                ->locale('it')
                ->onlyonDetail()
                ->displayUsing(function () {
                    return number_format($this->getTotalAdditionalServicesPrice(), 2, ',', '.') . ' €';
                }),
            Currency::make('IVA')
                ->currency('EUR')
                ->locale('it')
                ->onlyonDetail()
                ->displayUsing(function () {
                    $iva = $this->getQuoteNetPrice() * 0.22;
                    return number_format($iva, 2, ',', '.') . ' €';
                }),
            Currency::make('Final Price')
                ->currency('EUR')
                ->locale('it')
                ->onlyonDetail()
                ->displayUsing(function () {
                    $iva = $this->getQuoteNetPrice() * 0.22;
                    return number_format($this->getQuoteNetPrice() + $iva, 2, ',', '.') . ' €';
                }),
            Text::make('PDF')
                ->resolveUsing(function ($value, $resource, $attribute) {
                    return '<a class="link-default" target="_blank" href="' . route('quote', ['id' => $resource->id]) . '">[x]</a>';
                })
                ->asHtml()
                ->exceptOnForms(),

            Trix::make('Additional Info', 'additional_info')
                ->hideFromIndex(),

            Trix::make('Delivery Time', 'delivery_time')
                ->hideFromIndex(),

            Trix::make('Payment Plan', 'payment_plan')
                ->hideFromIndex(),

            Textarea::make('Billing Plan', 'billing_plan')
                ->hideFromIndex()


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
        return [
            new NewQuotes,
            new SentQuotes,
            new WonQuotes,
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
        return [];
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
            (new DuplicateQuote)
                ->showInline()
        ];
    }
}
