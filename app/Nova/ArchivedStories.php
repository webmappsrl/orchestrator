<?php

namespace App\Nova;

use App\Enums\StoryStatus;
use Illuminate\Http\Request;
use Laravel\Nova\Http\Requests\NovaRequest;

class ArchivedStories extends Story
{
    public static function label()
    {
        return __('Archived stories');
    }

    public static function indexQuery(NovaRequest $request, $query)
    {
        return $query
            ->where('user_id', $request->user()->id)
            ->where('status', StoryStatus::Done);
    }

    public static function authorizedToCreate(Request $request)
    {
        return false;
    }
}
