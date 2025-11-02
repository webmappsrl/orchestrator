<?php

namespace App\Nova;

use App\Enums\UserRole;
use App\Enums\StoryStatus;
use App\Enums\StoryType;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Query\Search\SearchableRelation;
use Khalin\Nova4SearchableBelongsToFilter\NovaSearchableBelongsToFilter;

class CustomerStory extends Story
{

    public $hideFields = ['updated_at', 'deadlines'];

    public static function label()
    {
        return __('Ticket dei clienti');
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
        $whereNotIn = [StoryStatus::Done->value, StoryStatus::Backlog->value, StoryStatus::Rejected->value];
        return $query->whereNotNull('creator_id')
            ->whereNotIn('status', $whereNotIn);
    }

    public function cards(NovaRequest $request)
    {
        // Return empty array to remove all cards
        return [];
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
            (new NovaSearchableBelongsToFilter(__('Creator')))->fieldAttribute('creator')->filterBy('creator_id'),
            new filters\UserFilter(),
            new filters\TesterFilter(),
            new filters\StoryStatusFilter(),
            new Filters\TaggableTypeFilter(),
            new filters\StoryTypeFilter(),
        ];
    }
}
