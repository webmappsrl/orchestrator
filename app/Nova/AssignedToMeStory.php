<?php

namespace App\Nova;

use App\Enums\StoryStatus;
use Laravel\Nova\Http\Requests\NovaRequest;
use Illuminate\Http\Request;

class AssignedToMeStory extends Story
{
    public $hideFields = ['answer_to_ticket', 'updated_at'];
    public static function label()
    {
        return __('Assigned to me stories');
    }

    public static function indexQuery(NovaRequest $request, $query)
    {
        return $query
            ->where('user_id', auth()->user()->id)
            ->whereNotIn('status', [StoryStatus::New, StoryStatus::Done]);
    }

    public static function authorizedToCreate(Request $request)
    {
        return false;
    }
}
