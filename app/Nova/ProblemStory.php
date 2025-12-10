<?php

namespace App\Nova;

use App\Enums\StoryStatus;
use Laravel\Nova\Http\Requests\NovaRequest;
use Khalin\Nova4SearchableBelongsToFilter\NovaSearchableBelongsToFilter;

class ProblemStory extends CustomerStory
{
    public static function label()
    {
        return __('Problemi');
    }

    public static function indexQuery(NovaRequest $request, $query)
    {
        $whereNotIn = [StoryStatus::Done->value, StoryStatus::Backlog->value, StoryStatus::Rejected->value];
        return $query->whereNotNull('creator_id')
            ->whereNotIn('status', $whereNotIn)
            ->where('status', StoryStatus::Problem->value);
    }
}

