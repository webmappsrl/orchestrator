<?php

namespace App\Nova;

use App\Enums\QuoteStatus;
use App\Nova\Actions\EditFields;
use App\Nova\Actions\GenerateQuotePublicPdfLink;
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
        ];
        return $query
            ->whereIn('status', $whereIn);
    }

    /**
     * The QuotesWithoutActiveTasks Lens belongs to Nova\Quote only (oc:8729).
     */
    public function lenses(NovaRequest $request)
    {
        return [];
    }

    public function actions(NovaRequest $request)
    {
        return [
            EditFields::make('Edit', ['status'], ArchivedQuotes::class),
            // Le trattative chiuse si aprono da qui: il link si genera anche su quelle (oc:8733).
            new GenerateQuotePublicPdfLink,
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
