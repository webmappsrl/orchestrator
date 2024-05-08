<?php

namespace App\Nova\Metrics;

use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Metrics\Partition;
use App\Models\Story;
use App\Enums\UserRole;
use App\Enums\StoryStatus;
use App\Enums\StoryType;

class StoriesByType extends Partition
{
    /**
     * Calculate the value of the metric.
     *
     * @param  \Laravel\Nova\Http\Requests\NovaRequest  $request
     * @return mixed
     */
    public function calculate(NovaRequest $request)
    {
        $query =  Story::whereNotNull('creator_id')
            ->whereHas('creator', function ($query) {
                $query->whereJsonContains('roles', UserRole::Customer);
            })
            ->where('status', '!=', StoryStatus::Done->value)
            ->where('type', '!=', StoryType::Feature->value);

        return $this->count($request, $query, 'type')
            ->label(function ($value) {
                switch ($value) {
                    case StoryType::Helpdesk->value:
                        return 'Help Desk';
                    case StoryType::Bug->value:
                        return 'Bug';
                    default:
                        return ucfirst($value);
                }
            });
    }

    /**
     * Determine the amount of time the results of the metric should be cached.
     *
     * @return \DateTimeInterface|\DateInterval|float|int|null
     */
    public function cacheFor()
    {
        // Return the cache time in seconds
        return now()->addMinutes(5);
    }

    /**
     * Get the URI key for the metric.
     *
     * @return string
     */
    public function uriKey()
    {
        return 'stories-by-type';
    }
}
