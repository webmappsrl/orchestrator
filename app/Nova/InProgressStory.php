<?php

namespace App\Nova;

use App\Enums\UserRole;
use App\Enums\StoryStatus;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Query\Search\SearchableRelation;

class InProgressStory extends Story
{

    public $hideFields = ['updated_at', 'deadlines'];

    public static function label()
    {
        return __('Ticket in progress');
    }

    public static function singularLabel()
    {
        return __('In Progress Story');
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
        return $query->where('status', StoryStatus::Progress->value)
            ->whereNotNull('creator_id');
    }

    /**
     * Get the fields displayed on the index listing.
     *
     * @param  \Laravel\Nova\Http\Requests\NovaRequest  $request
     * @return array
     */
    public function fieldsInIndex(NovaRequest $request)
    {
        $fields = parent::fieldsInIndex($request);
        
        // Aggiungo il campo con il link al ticket
        $fields[] = Text::make(__('View Ticket'), 'view_ticket', function () {
            $url = url("/resources/customer-stories/{$this->id}");
            return '<a href="' . $url . '" target="_blank" style="color: #4099de; font-weight: bold;">View Ticket →</a>';
        })->asHtml()->onlyOnIndex();

        return $fields;
    }

    public function cards(NovaRequest $request)
    {
        // Return empty array to remove all cards
        return [];
    }

    /**
     * Get the actions available for the resource.
     *
     * @param  \Laravel\Nova\Http\Requests\NovaRequest  $request
     * @return array
     */
    public function actions(NovaRequest $request)
    {
        return [];
    }

    /**
     * Get the actions available for the index.
     *
     * @param  \Laravel\Nova\Http\Requests\NovaRequest  $request
     * @return array
     */
    public function actionsForIndex(NovaRequest $request)
    {
        return [];
    }
}

