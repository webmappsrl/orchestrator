<?php

namespace App\Nova;

use App\Enums\UserRole;
use App\Enums\StoryStatus;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Query\Search\SearchableRelation;

class DeveloperStory extends Story
{

    public $hideFields = ['updated_at'];

    public static function label()
    {
        return __('Developer stories');
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
            ->whereDoesntHave('creator', function ($query) {
                $query->whereJsonContains('roles', UserRole::Customer);
            })
            ->where('status', '!=', StoryStatus::Done->value);
    }

    public function cards(NovaRequest $request)
    {
        $query = $this->indexQuery($request,  Story::query());
        return [
            ...parent::cards($request),
            (new Metrics\StoriesByField('type', 'Type', $query))->width('1/2'),
            (new Metrics\StoriesByField('status', 'Status', $query))->width('1/2'),
            (new Metrics\StoriesByUser('creator_id', 'Customer', $query))->width('1/2'),
            (new Metrics\StoriesByUser('user_id', 'Assigned',  $query))->width('1/2'),
        ];
    }
}
