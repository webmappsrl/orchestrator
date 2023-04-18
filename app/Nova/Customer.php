<?php

namespace App\Nova;

use Datomatic\NovaMarkdownTui\Enums\EditorType;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Date;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Fields\Number;
use Laravel\Nova\Fields\Boolean;
use Laravel\Nova\Fields\Currency;
use Laravel\Nova\Fields\HasMany;
use Datomatic\NovaMarkdownTui\MarkdownTui;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Panel;

class Customer extends Resource
{
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
        'id', 'name', 'hs_id', 'domain_name', 'full_name', 'subscription_amount', 'subscription_last_payment', 'subscription_last_invoice', 'notes'
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
            new Panel('MAIN INFO', [
                ID::make()->sortable(),
                Text::make('Name')
                    ->sortable()
                    ->rules('required', 'max:255'),
                Text::make('Full Name', 'full_name')
                    ->sortable()
                    ->nullable()
                    ->hideFromIndex(),
                Text::make('HS', 'hs_id')
                    ->sortable()
                    ->nullable()
                    ->hideFromIndex(),
                Text::make('Domain Name', 'domain_name')
                    ->sortable()
                    ->nullable()
                    ->hideFromIndex(),

            ]),

            new Panel('SUBSCRIPTION INFO', [
                Boolean::make('Subs.', 'has_subscription')
                    ->sortable()
                    ->nullable(),
                Currency::make('S/Amount', 'subscription_amount')
                    ->sortable()
                    ->currency('EUR')
                    ->nullable(),
                Date::make('S/Payment', 'subscription_last_payment')
                    ->sortable()
                    ->nullable(),
                Number::make('S/year', 'subscription_last_covered_year')
                    ->sortable()
                    ->nullable()
                    ->rules('nullable', 'integer'),
                Text::make('S/invoice', 'subscription_last_invoice')
                    ->sortable()
                    ->nullable(),
            ]),

            new Panel('NOTES', [
                MarkdownTui::make('Notes', 'notes')
                    ->showOnDetail()
                    ->initialEditType(EditorType::MARKDOWN)
            ]),

            HasMany::make('Projects'),
            HasMany::make('Quotes'),
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
        return [];
    }

    public function indexBreadcrumb()
    {
        return null;
    }
}
