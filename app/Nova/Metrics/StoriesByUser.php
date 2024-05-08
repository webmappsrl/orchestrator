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

    public function __construct($fieldName = 'creator_id', $label = 'Customer')
    {
        $this->fieldName = $fieldName;
        $this->label = $label;
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
        $query =  Story::whereNotNull('creator_id')
            ->whereHas('creator', function ($query) {
                $query->whereJsonContains('roles', UserRole::Customer);
            })
            ->where('status', '!=', StoryStatus::Done->value)
            ->where('type', '!=', StoryType::Feature->value);

        return $this->count($request, $query, $this->fieldName)
            ->label(function ($value) {
                $user = User::find($value);
                return $user ? $user->name : 'User not found';
            });
    }

    /**
     * Determine the amount of time the results of the metric should be cached.
     *
     * @return \DateTimeInterface|\DateInterval|float|int|null
     */
    public function cacheFor()
    {
        return now()->addMinutes(5);
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
