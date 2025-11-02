<?php

namespace App\Nova;

use App\Enums\UserRole;
use App\Enums\StoryStatus;
use App\Enums\StoryType;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Query\Search\SearchableRelation;

class BacklogStory extends Story
{

    public $hideFields = ['updated_at'];

    public static function label()
    {
        return __('Ticket in Backlog');
    }


    public static function searchableColumns()
    {
        return [
            'id',
            'name',
            new SearchableRelation('creator', 'name'),
        ];
    }

    public static function indexQuery(NovaRequest $request, $query)
    {
        return $query->whereNotNull('creator_id')
            ->where('status', StoryStatus::Backlog->value);
    }

    public function cards(NovaRequest $request)
    {
        // Return empty array to remove all cards
        return [];
    }
}
