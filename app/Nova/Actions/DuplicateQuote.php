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
            //add products to the new quote
            $newQuote->products()->attach($quote->products);
            //add recurring products to the new quote
            $newQuote->recurringProducts()->attach($quote->recurringProducts);
            $newQuote->user()->associate($quote->user);
            //save the new quote
            $newQuote->save();
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
