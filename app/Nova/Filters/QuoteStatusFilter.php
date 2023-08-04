<?php

namespace App\Nova\Filters;

use App\Enums\QuoteStatus;
use Laravel\Nova\Filters\Filter;
use Laravel\Nova\Http\Requests\NovaRequest;

class QuoteStatusFilter extends Filter
{
    /**
     * The filter's component.
     *
     * @var string
     */
    public $component = 'select-filter';

    /**
     * Apply the filter to the given query.
     *
     * @param  \Laravel\Nova\Http\Requests\NovaRequest  $request
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  mixed  $value
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function apply(NovaRequest $request, $query, $value)
    {
        return $query->where('status', $value);
    }

    /**
     * Get the filter's available options.
     *
     * @param  \Laravel\Nova\Http\Requests\NovaRequest  $request
     * @return array
     */
    public function options(NovaRequest $request)
    {

        return [
            'new' => QuoteStatus::New,
            'sent' => QuoteStatus::Sent,
            'closed lost' => QuoteStatus::Closed_Lost,
            'closed won' => QuoteStatus::Closed_Won,
            'partially paid' =>  QuoteStatus::Partially_Paid,
            'paid' =>  QuoteStatus::Paid,
        ];
    }
}
