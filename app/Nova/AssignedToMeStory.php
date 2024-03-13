<?php

namespace App\Nova;

use App\Enums\StoryStatus;
use Laravel\Nova\Http\Requests\NovaRequest;
use Illuminate\Http\Request;

class AssignedToMeStory extends Story
{
    public static function indexQuery(NovaRequest $request, $query)
    {
        $user = auth()->user();
        return $query->where('user_id', $user->id)->whereNotIn('status', [StoryStatus::New, StoryStatus::Done, StoryStatus::Released]);
    }

    public static function authorizedToCreate(Request $request)
    {
        return false;
    }
}
