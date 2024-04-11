<?php

namespace App\Nova;

use App\Enums\StoryStatus;
use Illuminate\Http\Request;
use Laravel\Nova\Http\Requests\NovaRequest;

class ToBeTestedStory extends Story
{

    public static function label()
    {
        return __('To be tested stories');
    }

    public static function indexQuery(NovaRequest $request, $query)
    {
        return $query
            ->where('tester_id', $request->user()->id)
            ->where('status', StoryStatus::Test);
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
            new filters\UserFilter(),
            new filters\StoryTypeFilter(),
            new filters\CustomerStoryWithDeadlineFilter(),
        ];
    }
}
