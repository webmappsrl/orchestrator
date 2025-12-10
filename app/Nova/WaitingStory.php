<?php

namespace App\Nova;

use App\Enums\StoryStatus;
use Laravel\Nova\Http\Requests\NovaRequest;
use Khalin\Nova4SearchableBelongsToFilter\NovaSearchableBelongsToFilter;

class WaitingStory extends CustomerStory
{
    public static function label()
    {
        return __('In Attesa');
    }

    public static function indexQuery(NovaRequest $request, $query)
    {
        $whereNotIn = [StoryStatus::Done->value, StoryStatus::Backlog->value, StoryStatus::Rejected->value];
        return $query->whereNotNull('creator_id')
            ->whereNotIn('status', $whereNotIn)
            ->where('status', StoryStatus::Waiting->value);
    }
}

