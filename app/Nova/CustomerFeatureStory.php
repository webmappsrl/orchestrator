<?php

namespace App\Nova;

use App\Enums\UserRole;
use App\Enums\StoryStatus;
use App\Enums\StoryType;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Query\Search\SearchableRelation;

class CustomerFeatureStory extends Story
{

    public $hideFields = ['updated_at'];

    public static function label()
    {
        return __('Feature stories');
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
            ->doesntHave('deadlines')
            ->where('status', '!=', StoryStatus::Done->value)
            ->where('type', '=', StoryType::Feature->value);
    }

    public function cards(NovaRequest $request)
    {
        $query = $this->indexQuery($request,  Story::query());
        return [
            (new Metrics\StoriesByField('status', 'Status', $query))->width('1/3'),
            (new Metrics\StoriesByUser('creator_id', 'Creator', $query))->width('1/3'),
            (new Metrics\StoriesByUser('user_id', 'Assigned',  $query))->width('1/3'),
        ];
    }
}
