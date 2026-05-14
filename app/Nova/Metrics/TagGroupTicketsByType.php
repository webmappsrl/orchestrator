<?php

namespace App\Nova\Metrics;

use App\Models\TagGroup;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Metrics\Partition;

class TagGroupTicketsByType extends Partition
{
    public function calculate(NovaRequest $request)
    {
        $tagGroup = $request->findModel();

        if (! $tagGroup instanceof TagGroup) {
            return $this->result([]);
        }

        $results = $tagGroup->stories()
            ->get()
            ->groupBy('type')
            ->mapWithKeys(fn ($items, $type) => [
                $type ?: 'No Type' => count($items),
            ])
            ->sortByDesc(fn ($count) => $count);

        return $this->result($results->toArray());
    }

    public function name(): string
    {
        return __('Tickets by Type');
    }

    public function uriKey(): string
    {
        return 'tag-group-tickets-by-type';
    }
}
