<?php

namespace App\Nova;

use Laravel\Nova\Fields\ID;
use Illuminate\Http\Request;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Fields\Trix;
use Laravel\Nova\Fields\Currency;
use Laravel\Nova\Fields\Textarea;
use App\Nova\Actions\ExportProductsToPdf;
use Kongulov\NovaTabTranslatable\NovaTabTranslatable;
use Laravel\Nova\Http\Requests\NovaRequest;

class RecurringProduct extends Resource
{
    /**
     * The model the resource corresponds to.
     *
     * @var class-string<\App\Models\RecurringProduct>
     */
    public static $model = \App\Models\RecurringProduct::class;

    /**
     * The single value that should be used to represent the resource when being displayed.
     *
     * @var string
     */
    public static $title = 'name';

    public function title()
    {
        return __($this->name) . ' - ' . $this->price . '€';
    }

    /**
     * Get the plural label of the resource.
     *
     * @return string
     */
    public static function label()
    {
        return __('Recurring Products');
    }

    /**
     * Get the singular label of the resource.
     *
     * @return string
     */
    public static function singularLabel()
    {
        return __('Recurring Product');
    }

    /**
     * The columns that should be searched.
     *
     * @var array
     */
    public static $search = [
        'id',
        'name',
        'sku'
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
            NovaTabTranslatable::make([
                Text::make(__('Name'), 'name')->sortable(),
                Textarea::make(__('Description'), 'description')->hideFromIndex()->nullable()
            ]),
            Text::make(__('SKU'), 'sku')->sortable(),
            Currency::make(__('Price'), 'price')->currency('EUR')->nullable(),

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
        return [
            (new ExportProductsToPdf())->standalone()->onlyOnIndex(),
        ];
    }
}
