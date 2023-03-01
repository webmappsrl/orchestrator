<?php

namespace App\Nova;

use Laravel\Nova\Fields\ID;
use Illuminate\Http\Request;
use Laravel\Nova\Fields\Date;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Fields\Number;
use Laravel\Nova\Fields\Boolean;
use Laravel\Nova\Fields\HasMany;
use Laravel\Nova\Fields\Markdown;
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
                Text::make('Hubspot ID', 'hs_id')
                    ->nullable(),
                Text::make('Domain Name', 'domain_name')
                    ->nullable(),
                Text::make('Full Name', 'full_name')
                    ->nullable(),
            ]),

            new Panel('SUBSCRIPTION INFO', [
                Boolean::make('Has Subscription', 'has_subscription')
                    ->nullable(),
                Number::make('Subscription Amount', 'subscription_amount')
                    ->step(0.01)
                    ->min(0)
                    ->nullable(),
                Date::make('Subscription Last Payment', 'subscription_last_payment')->nullable(),
                Number::make('Subscription Last Covered Year')
                    ->nullable()
                    ->rules('nullable', 'integer'),
                Text::make('Subscription Last Invoice', 'subscription_last_invoice')
                    ->nullable(),
            ]),

            new Panel('NOTES', [
                Markdown::make('Notes', 'notes')
                    ->showOnDetail()
                    ->alwaysShow(),
            ]),

            HasMany::make('Projects'),
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
}