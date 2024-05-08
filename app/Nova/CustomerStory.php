<?php

namespace App\Nova;

use App\Enums\UserRole;
use App\Enums\StoryStatus;
use App\Enums\StoryType;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Query\Search\SearchableRelation;

class CustomerStory extends Story
{

    public $hideFields = ['updated_at'];

    public static function label()
    {
        return __('Customer stories');
    }


    public static function searchableColumns()
    {
        return [
            'id', 'name', new SearchableRelation('creator', 'name'),
        ];
    }

    public static function indexQuery(NovaRequest $request, $query)
    {
        return $query->whereNotNull('creator_id')
            ->whereHas('creator', function ($query) {
                $query->whereJsonContains('roles', UserRole::Customer);
            })
            ->where('status', '!=', StoryStatus::Done->value)
            ->where('type', '!=', StoryType::Feature->value);
    }

    public function cards(NovaRequest $request)
    {
        return [
            new Metrics\StoriesByType(),
            new Metrics\StoriesByAssigned(),
        ];
    }
}
