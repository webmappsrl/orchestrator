<?php

namespace App\Nova\Lenses;

use App\Enums\QuoteStatus;
use Illuminate\Http\Request;
use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\LensRequest;
use Laravel\Nova\Lenses\Lens;

/**
 * Open quotes without any todo task, i.e. quotes nobody is following (oc:8729).
 * The definition lives in Quote::scopeOpenWithoutActiveTasks(), shared with
 * GET /api/quotes?without_active_tasks=1: a Lens does not go through
 * Nova\Quote::indexQuery(), so closed quotes are excluded by the scope.
 */
class QuotesWithoutActiveTasks extends Lens
{
    public static function query(LensRequest $request, $query)
    {
        // Ordinamento predefinito per id, come GET /api/quotes: senza ORDER BY PostgreSQL
        // non garantisce lo stesso ordine fra una pagina e l'altra.
        return $request->withOrdering($request->withFilters(
            $query->openWithoutActiveTasks()->with('customer')
        ), fn ($query) => $query->orderByDesc('id'));
    }

    public function fields(Request $request)
    {
        return [
            ID::make('ID', 'id')->sortable(),
            Text::make(__('Title'), 'title')->sortable(),
            BelongsTo::make(__('Customer'), 'customer', 'App\Nova\Customer'),
            Text::make(__('Status'), 'status')
                ->displayUsing(function () {
                    $status = QuoteStatus::tryFrom($this->status);
                    return $status ? $status->label() : (string) $this->status;
                }),
        ];
    }

    public function name()
    {
        return __('Quotes without active tasks');
    }

    public function uriKey()
    {
        return 'quotes-without-active-tasks';
    }
}
