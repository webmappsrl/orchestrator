<?php

namespace App\Nova\Metrics;

use App\Models\Story;
use App\Models\User;
use App\Enums\UserRole;
use App\Enums\StoryStatus;
use App\Enums\StoryType;
use Laravel\Nova\Metrics\Partition;
use Laravel\Nova\Http\Requests\NovaRequest;

class StoriesByUser extends Partition
{
    public $fieldName;
    public $label;
    public $query;

    public function __construct($fieldName = 'creator_id', $label = 'Customer', $query = null)
    {
        $this->fieldName = $fieldName;
        $this->label = $label;
        $this->query = $query;
    }

    public function name()
    {
        return 'Stories by ' . ucfirst($this->label);
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
            $this->query = Story::whereNotNull($this->fieldName)
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
                    $user = User::find($key);
                    return [$user ? $user->name : 'User not found' => count($items)];
                }
                return ['User not found' => count($items)];
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
        //   return now()->addMinutes(5);
    }

    /**
     * Get the URI key for the metric.
     *
     * @return string
     */
    public function uriKey()
    {
        return 'stories-by-' . $this->label;
    }
}
