<?php

namespace App\Nova\Metrics;

use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Metrics\Partition;
use App\Models\Story;
use App\Enums\UserRole;
use App\Enums\StoryStatus;
use App\Enums\StoryType;

class StoriesByField extends Partition
{

    public $fieldName;
    public $label;
    public $query;

    public function __construct($fieldName = 'type', $label = 'Type', $query = null)
    {
        $this->fieldName = $fieldName;
        $this->label = $label;
        $this->query = $query;
    }

    /**
     * Calculate the value of the metric.
     *
     * @param  \Laravel\Nova\Http\Requests\NovaRequest  $request
     * @return mixed
     */
    public function calculate(NovaRequest $request)
    {
        if (is_null($this->query)) {
            $this->query =  Story::whereNotNull('creator_id')
                ->whereHas('creator', function ($query) {
                    $query->whereJsonContains('roles', UserRole::Customer);
                })
                ->where('status', '!=', StoryStatus::Done->value)
                ->where('type', '!=', StoryType::Feature->value);
        }

        $results = $this->query
            ->get()
            ->groupBy($this->fieldName)
            ->mapWithKeys(function ($items, $key) {
                if (!empty($key)) {
                    return [$key => count($items)];
                }
                return ['No ' . $this->label => count($items)];
            })
            ->sortByDesc(function ($count, $name) {
                return $count;
            });

        return $this->result($results->toArray());
    }

    /**
     * Determine the amount of time the results of the metric should be cached.
     *
     * @return \DateTimeInterface|\DateInterval|float|int|null
     */
    public function cacheFor()
    {
        // Return the cache time in seconds
        //    return now()->addMinutes(5);
    }

    /**
     * Get the URI key for the metric.
     *
     * @return string
     */
    public function uriKey()
    {
        return 'by-' . $this->label;
    }

    /**
     * Get the displayable name of the metric.
     *
     * @return string
     */
    public function name()
    {
        return __('by') . ' ' . ucfirst(__($this->label));
    }
}
