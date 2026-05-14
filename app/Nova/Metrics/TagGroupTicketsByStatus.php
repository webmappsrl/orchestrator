<?php

namespace App\Nova\Metrics;

use App\Enums\StoryStatus;
use App\Models\TagGroup;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Metrics\Partition;

class TagGroupTicketsByStatus extends Partition
{
    public function calculate(NovaRequest $request)
    {
        $tagGroup = $request->findModel();

        if (! $tagGroup instanceof TagGroup) {
            return $this->result([]);
        }

        $results = $tagGroup->stories()
            ->get()
            ->groupBy('status')
            ->mapWithKeys(fn ($items, $status) => [
                StoryStatus::from($status)->label() => count($items),
            ])
            ->sortByDesc(fn ($count) => $count);

        return $this->result($results->toArray())
            ->colors($this->statusColors($results->keys()->all()));
    }

    private function statusColors(array $labels): array
    {
        $map = [];
        foreach (StoryStatus::cases() as $case) {
            $map[$case->label()] = $case->color();
        }
        return array_intersect_key($map, array_flip($labels));
    }

    public function name(): string
    {
        return __('Tickets by Status');
    }

    public function uriKey(): string
    {
        return 'tag-group-stories-by-status';
    }
}
