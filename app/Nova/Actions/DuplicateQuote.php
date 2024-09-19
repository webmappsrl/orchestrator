<?php

namespace App\Nova\Actions;

use App\Models\Quote;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Collection;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Fields\ActionFields;
use Laravel\Nova\Http\Requests\NovaRequest;

class DuplicateQuote extends Action
{
    use InteractsWithQueue, Queueable;

    public $name = 'Duplicate Quote';

    /**
     * Perform the action on the given models.
     *
     * @param  \Laravel\Nova\Fields\ActionFields  $fields
     * @param  \Illuminate\Support\Collection  $models
     * @return mixed
     */
    public function handle(ActionFields $fields, Collection $models)
    {
        foreach ($models as $quote) {
            //create a new quote that takes all the fields from the old quote
            $newQuote = Quote::create($quote->toArray());
            //add the new quote to the same client
            $newQuote->customer()->associate($quote->customer);
            // Aggiungi i prodotti alla nuova quote, includendo i dati del pivot (quantità)
            $products = $quote->products->mapWithKeys(function ($product) {
                return [$product->id => ['quantity' => $product->pivot->quantity]];
            });
            $newQuote->products()->sync($products);

            // Aggiungi i prodotti ricorrenti alla nuova quote, includendo i dati del pivot (quantità)
            $recurringProducts = $quote->recurringProducts->mapWithKeys(function ($recurringProduct) {
                return [$recurringProduct->id => ['quantity' => $recurringProduct->pivot->quantity]];
            });
            $newQuote->recurringProducts()->sync($recurringProducts);
            $newQuote->user()->associate($quote->user);
            //save the new quote
            $newQuote->save();
        }
        // Controlla se è stato duplicato solo un modello e fai il redirect
        if ($models->count() === 1 && $newQuote) {
            // Reindirizza alla pagina di modifica della nuova quote
            $resourceName = 'quotes'; // Sostituisci con il nome corretto della risorsa
            $newModelId = $newQuote->id;
            $editUrl = url("/resources/{$resourceName}/{$newModelId}/edit");


            // Usa il metodo Nova per aprire in una nuova scheda
            return Action::openInNewTab($editUrl);
        }
    }

    /**
     * Get the fields available on the action.
     *
     * @param  \Laravel\Nova\Http\Requests\NovaRequest  $request
     * @return array
     */
    public function fields(NovaRequest $request)
    {
        return [];
    }
}
