<?php

namespace App\Nova;

use App\Enums\UserRole;
use App\Enums\StoryStatus;
use Illuminate\Http\Request;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Query\Search\SearchableRelation;

class NewStory extends Story
{

    public $hideFields = ['updated_at'];

    public static function label()
    {
        return __('Ticket nuovi');
    }

    public static function singularLabel()
    {
        return __('New Story');
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
            ->where('status', StoryStatus::New->value);
    }

    public static function authorizedToCreate(Request $request)
    {
        return false;
    }

    public function cards(NovaRequest $request)
    {
        // Return empty array to remove all cards
        return [];
    }
}

