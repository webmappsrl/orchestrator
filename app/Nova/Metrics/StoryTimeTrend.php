<?php

namespace App\Nova\Metrics;

use App\Models\Tag;
use App\Models\Story;
use Laravel\Nova\Metrics\Value;
use Laravel\Nova\Http\Requests\NovaRequest;

class StoryTimeTrend extends Value
{
    /**
     * Calculate the value of the metric.
     *
     * @param  \Laravel\Nova\Http\Requests\NovaRequest  $request
     * @return mixed
     */
    public function calculate(NovaRequest $request)
    {
        $query = Story::class;
        $requestModel = $request->findModel();
        if ($requestModel instanceof Tag) {
            $query = Story::whereRelation('tags', 'taggables.taggable_type', Story::class)
                ->whereRelation('tags', 'taggables.tag_id', $requestModel->id);
        }
        return $this->sum($request, $query, 'hours')->suffix('Hours');
    }

    /**
     * Get the ranges available for the metric.
     *
     * @return array
     */
    public function ranges()
    {
        return [
            'ALL' => 'All Time'
        ];
    }

    /**
     * Determine the amount of time the results of the metric should be cached.
     *
     * @return \DateTimeInterface|\DateInterval|float|int|null
     */
    public function cacheFor()
    {
        // return now()->addMinutes(5);
    }
}
