<?php

namespace App\Nova;

use App\Enums\UserRole;
use App\Enums\StoryStatus;
use App\Enums\StoryType;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Query\Search\SearchableRelation;

class CustomerStory extends Story
{

    public $hideFields = ['updated_at', 'deadlines'];

    public static function label()
    {
        return __('Stories');
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
            ->where('status', '!=', StoryStatus::Done->value)
            ->where('type', '!=', StoryType::Feature->value);
    }

    public function cards(NovaRequest $request)
    {
        $query = $this->indexQuery($request,  Story::query());
        $parentCards = parent::cards($request);
        $childCards = [
            (new Metrics\StoriesByField('type', 'Type', $query))->width('1/2'),
            (new Metrics\StoriesByField('status', 'Status', $query))->width('1/2'),
            (new Metrics\StoriesByUser('creator_id', 'Creator', $query))->width('1/2'),
            (new Metrics\StoriesByUser('user_id', 'Assigned',  $query))->width('1/2'),
        ];

        return array_merge($childCards, $parentCards);
    }
}
