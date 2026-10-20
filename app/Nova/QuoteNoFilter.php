<?php

namespace App\Nova;

use App\Nova\Quote;
use Laravel\Nova\Http\Requests\NovaRequest;

class QuoteNoFilter extends Quote
{
    public static function indexQuery(NovaRequest $request, $query)
    {
        return $query->withNextTodoTask();
    }

    /**
     * The QuotesWithoutActiveTasks Lens belongs to Nova\Quote only (oc:8729).
     */
    public function lenses(NovaRequest $request)
    {
        return [];
    }
}
