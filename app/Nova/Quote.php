<?php

namespace App\Nova;

use Laravel\Nova\Panel;
use Manogi\Tiptap\Tiptap;
use App\Enums\QuoteStatus;
use Laravel\Nova\Fields\ID;
use Illuminate\Http\Request;
use Laravel\Nova\Fields\Text;
use App\Nova\Metrics\NewQuotes;
use App\Nova\Metrics\WonQuotes;
use Laravel\Nova\Fields\Number;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Fields\Status;
use App\Nova\Metrics\SentQuotes;
use Laravel\Nova\Fields\Currency;
use Laravel\Nova\Fields\KeyValue;
use Laravel\Nova\Fields\BelongsTo;
use App\Nova\Actions\DuplicateQuote;
use Laravel\Nova\Fields\BelongsToMany;
use App\Nova\Filters\QuoteStatusFilter;
use Datomatic\NovaMarkdownTui\MarkdownTui;
use Laravel\Nova\Http\Requests\NovaRequest;
use App\Nova\Metrics\DynamicPartitionMetric;
use Datomatic\NovaMarkdownTui\Enums\EditorType;
use Ebess\AdvancedNovaMediaLibrary\Fields\Files;
use Kongulov\NovaTabTranslatable\NovaTabTranslatable;

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
        'id',
        'title'
    ];

    /**
     * Get the fields displayed by the resource.
     *
     * @param  \Laravel\Nova\Http\Requests\NovaRequest  $request
     * @return array
     */
    public function fields(NovaRequest $request)
    {
        $allButtons = [
            'heading',
            '|',
            'italic',
            'bold',
            '|',
            'link',
            'code',
            'strike',
            'underline',
            'highlight',
            '|',
            'bulletList',
            'orderedList',
            'br',
            'codeBlock',
            'blockquote',
            '|',
            'horizontalRule',
            'hardBreak',
            '|',
            'table',
            '|',
            'image',
            '|',
            'textAlign',
            '|',
            'rtl',
            '|',
            'history',
            '|',
            'editHtml',
        ];
        return [
            ID::make()->sortable(),
            NovaTabTranslatable::make([
                Text::make(__('Title'), 'title')
                    ->displayUsing(function ($name, $a, $b) {
                        $wrappedName = wordwrap($name, 50, "\n", true);
                        $htmlName = str_replace("\n", '<br>', $wrappedName);
                        return $htmlName;
                    })
                    ->asHtml(),
            ])->setTitle(__('Title')),
            Status::make('Status')->loadingWhen(['new', 'sent'])->failedWhen(['closed lost'])->displayUsing(function () {
                return __($this->status);
            })->onlyOnIndex(),
            Select::make('Status')->options(
                collect(QuoteStatus::cases())->mapWithKeys(function ($status) {
                    return [$status->value => $status->label()];
                })->toArray()
            )->onlyOnForms()
                ->default(QuoteStatus::New->value),
            Text::make('Google Drive Url', 'google_drive_url')->nullable()->hideFromIndex()->displayUsing(function () {
                return '<a class="link-default" target="_blank" href="' . $this->google_drive_url . '">' . $this->google_drive_url . '</a>';
            })->asHtml(),
            BelongsTo::make(__('Customer'), 'customer', 'App\nova\Customer')
                ->filterable()
                ->searchable(),
            BelongsToMany::make(__('Products'), 'products', 'App\nova\Product')->fields(function () {
                return [
                    Number::make(__('Quantity'), 'quantity')->rules('required', 'numeric', 'min:1')
                        ->default(1)
                ];
            })
                ->searchable(),
            BelongsToMany::make('Recurring Products')->fields(function () {
                return [
                    Number::make(__('Quantity'), 'quantity')->rules('required', 'numeric', 'min:1')
                        ->default(1)
                ];
            })
                ->searchable(),
            BelongsTo::make(__('Owner'), 'user', 'App\nova\User')
                ->searchable()
                ->filterable()
                ->nullable(),
            Currency::make(__('Products'))
                ->currency('EUR')
                ->locale('it')
                ->exceptOnForms()
                ->displayUsing(function () {
                    $price = empty($this->products) ? 0 : $this->getTotalPrice();
                    return number_format($price, 2, ',', '.') . ' €';
                })->sortable(),
            Currency::make(__('Recurring'), 'recurring')
                ->currency('EUR')
                ->locale('it')
                ->exceptOnForms()
                ->displayUsing(function () {
                    $price = empty($this->recurringProducts) ? 0 : $this->getTotalRecurringPrice();
                    return number_format($price, 2, ',', '.') . ' €';
                })->sortable(),
            Currency::make(__('Total'), 'total')
                ->currency('EUR')
                ->locale('it')
                ->exceptOnForms()
                ->displayUsing(function () {
                    $quotePrice = $this->getTotalPrice() + $this->getTotalRecurringPrice() + $this->getTotalAdditionalServicesPrice();
                    return number_format($quotePrice, 2, ',', '.') . ' €';
                })->sortable(),
            Currency::make(__('Discount'), 'discount')
                ->currency('EUR')
                ->locale('it')
                ->hideFromIndex()
                ->displayUsing(function () {
                    return number_format($this->discount, 2, ',', '.') . ' €';
                }),
            NovaTabTranslatable::make([
                KeyValue::make(__('Additional Services'), 'additional_services')
                    ->hideFromIndex()
                    ->keyLabel(__('Description'))
                    ->valueLabel(__('Price') . '(€)')
                    ->hideFromIndex(),
            ])->hideFromIndex(),
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
                    $itaUrl = route('quote', ['id' => $resource->id]);
                    $enUrl = route('quote', ['id' => $resource->id, 'lang' => 'en']);

                    return $this->pdfButton($itaUrl, 'ITA') . $this->pdfButton($enUrl, 'EN');
                })
                ->asHtml()
                ->exceptOnForms(),
            NovaTabTranslatable::make([
                Tiptap::make(__('Additional Info'), 'additional_info')
                    ->hideFromIndex()
                    ->buttons($allButtons),

                Tiptap::make(__('Delivery Time'), 'delivery_time')
                    ->hideFromIndex()
                    ->buttons($allButtons),

                Tiptap::make(__('Payment Plan'), 'payment_plan')
                    ->hideFromIndex()
                    ->buttons($allButtons),

                Tiptap::make(__('Billing Plan'), 'billing_plan')
                    ->hideFromIndex()
                    ->buttons($allButtons),
                MarkdownTui::make(__('Notes'), 'notes')
                    ->hideFromIndex()
                    ->initialEditType(EditorType::MARKDOWN)
                    ->nullable()
            ])->hideFromIndex(),

            Files::make(__('Documents'), 'documents')
                ->hideFromIndex(),


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
        $query = $this->indexQuery($request,  Quote::query());
        return [

            (new DynamicPartitionMetric(
                'Quotes by Status',
                $query,
                'status',
            ))->width('full'),
            (new NewQuotes)->width('1/2'),
            (new SentQuotes)->width('1/2'),
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
            (new QuoteStatusFilter),
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
            (new DuplicateQuote)
                ->showInline()
        ];
    }

    public static function indexQuery(NovaRequest $request, $query)
    {
        $whereNotIn =  [QuoteStatus::Closed_Won->value,  QuoteStatus::Closed_Lost->value];
        return $query
            ->whereNotIn('status', $whereNotIn);
    }

    public function authorizedToReplicate(Request $request)
    {
        return false;
    }

    protected function pdfButton(string $url, string $label): string
    {
        return <<<HTML
                        <a style="display: inline-flex; align-items: center; padding: 0.25rem 0.5rem; background-color: rgb(20 184 166); color: rgb(254 243 199); font-weight: 500; border-radius: 0.375rem; margin-right: 0.5rem; font-size: 0.875rem;" target="_blank" href="{$url}">
                            {$label}
                        </a>
                    HTML;
    }
}
