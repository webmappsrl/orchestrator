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
        return __('Ticket assegnati a me');
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


    /**
     * Get the filters available for the resource.
     *
     * @param  \Laravel\Nova\Http\Requests\NovaRequest  $request
     * @return array
     */
    public function filters(NovaRequest $request)
    {
        return [
            new filters\CreatorStoryFilter(),
            new filters\StoryTypeFilter(),
            new filters\CustomerStoryWithDeadlineFilter(),
            new filters\StoryWithoutTagsFilter(),
        ];
    }

    public function cards(NovaRequest $request)
    {
        // Return empty array to remove all cards
        return [];
    }
}
