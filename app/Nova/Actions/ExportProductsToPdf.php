<?php

namespace App\Nova\Actions;

use App\Models\Product;
use App\Models\RecurringProduct;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Fields\Boolean;
use Illuminate\Support\Collection;
use Laravel\Nova\Fields\ActionFields;
use Outl1ne\MultiselectField\Multiselect;
use Laravel\Nova\Http\Requests\NovaRequest;

class ExportProductsToPdf extends Action
{
    public $name = 'Genera PDF Prodotti';

    public function handle(ActionFields $fields, Collection $models)
    {
        $includeAllProducts = $fields->include_all_products;
        $includeAllRecurringProducts = $fields->include_all_recurring_products;

        if ($includeAllProducts) {
            $productIds = Product::pluck('id')->toArray();
        } else {
            $productIds = json_decode($fields->products, true) ?? [];
        }

        if ($includeAllRecurringProducts) {
            $recurringProductIds = RecurringProduct::pluck('id')->toArray();
        } else {
            $recurringProductIds = json_decode($fields->recurring_products, true) ?? [];
        }

        if (empty($productIds) && empty($recurringProductIds)) {
            return Action::danger('Nessun prodotto selezionato.');
        }

        // Crea l'URL per il download, passando gli ID come parametri della query
        $url = route('products.pdf.download', [
            'products' => implode(',', $productIds),
            'recurring_products' => implode(',', $recurringProductIds),
        ]);

        return Action::redirect($url);
    }

    public function fields(NovaRequest $request)
    {
        return [
            Boolean::make('Includi tutti i Prodotti', 'include_all_products')
                ->trueValue(1)
                ->falseValue(0),

            Multiselect::make('Prodotti', 'products')
                ->options(\App\Models\Product::all()->pluck('name', 'id'))
                ->placeholder('Seleziona i prodotti da includere')
                ->max(0)
                ->dependsOn(['include_all_products'], function (Multiselect $field, NovaRequest $request, $formData) {
                    if ($formData->include_all_products) {
                        $field->hide();
                    }
                }),

            Boolean::make('Includi tutti i Prodotti Ricorrenti', 'include_all_recurring_products')
                ->trueValue(1)
                ->falseValue(0),

            Multiselect::make('Prodotti Ricorrenti', 'recurring_products')
                ->options(\App\Models\RecurringProduct::all()->pluck('name', 'id'))
                ->placeholder('Seleziona i prodotti ricorrenti da includere')
                ->max(0)
                ->dependsOn(['include_all_recurring_products'], function (Multiselect $field, NovaRequest $request, $formData) {
                    if ($formData->include_all_recurring_products) {
                        $field->hide();
                    }
                }),
        ];
    }
}
