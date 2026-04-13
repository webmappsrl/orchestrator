<?php

namespace App\Nova\Kanban;

use App\Models\Quote;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Webmapp\KanbanCard\Contracts\AggregatesKanbanColumn;

class SalesQuoteColumnAggregator implements AggregatesKanbanColumn
{
    /**
     * Aggregate Sales Kanban columns:
     * - count: number of quotes in the column
     * - sum: total economic value of quotes in the column
     */
    public function aggregate(Collection $items, Request $request, string $statusValue, array $config)
    {
        $sum = (float) $items->sum(function ($item) {
            if (! $item instanceof Quote) {
                return 0.0;
            }

            // Numeric total (not the formatted accessor string).
            return $item->getTotalPrice()
                + $item->getTotalRecurringPrice()
                + $item->getTotalAdditionalServicesPrice();
        });

        return [
            'count' => (int) $items->count(),
            'sum' => $sum,
        ];
    }
}

