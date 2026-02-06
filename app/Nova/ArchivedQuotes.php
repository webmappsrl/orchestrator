<?php

namespace App\Nova;

use App\Enums\QuoteStatus;
use App\Nova\Actions\EditFields;
use App\Nova\Metrics\StatusQuotes;
use Google\Service\AIPlatformNotebooks\Status;
use Illuminate\Http\Request;
use Laravel\Nova\Http\Requests\NovaRequest;

class ArchivedQuotes extends Quote
{
    public static function label()
    {
        return __('Archived quotes');
    }

    public static function uriKey()
    {
        return 'archived-quotes';
    }

    public static function authorizedToCreate(Request $request)
    {
        return false;
    }

    public static function indexQuery(NovaRequest $request, $query)
    {
        $whereIn = [
            QuoteStatus::Closed_Won->value,
            QuoteStatus::Closed_Lost->value,
            QuoteStatus::Closed_Won_Offer->value,
            QuoteStatus::Closed_Lost_Offer->value
        ];
        return $query
            ->whereIn('status', $whereIn);
    }

    public function actions(NovaRequest $request)
    {
        return [
            EditFields::make('Edit', ['status'], ArchivedQuotes::class),
        ];
    }

    public function cards(NovaRequest $request)
    {
        return [
            new StatusQuotes('Won', [QuoteStatus::Closed_Won->value]),
            new StatusQuotes('Lost', [QuoteStatus::Closed_Lost->value]),
        ];
    }
}
