<?php

namespace App\Nova;

use App\Enums\StoryStatus;
use Illuminate\Http\Request;
use Laravel\Nova\Http\Requests\NovaRequest;
use App\Nova\Actions\DuplicateStory;

class ArchivedStories extends Story
{
    public static function label()
    {
        return __('Ticket archiviati');
    }

    public static function indexQuery(NovaRequest $request, $query)
    {
        $whereIn = [StoryStatus::Done->value,  StoryStatus::Rejected->value];
        return $query
            ->whereIn('status', $whereIn);
    }
    public static function uriKey()
    {
        return 'archived-stories';
    }

    public static function authorizedToCreate(Request $request)
    {
        return false;
    }

    /**
     * Get the fields for the index view.
     * Remove deadline field and add updated_at sortable field
     *
     * @param  \Laravel\Nova\Http\Requests\NovaRequest  $request
     * @return array
     */
    public function fieldsInIndex(NovaRequest $request)
    {
        $fields = [
            Stack::make(__('MAIN INFO'), [
                $this->clickableIdField(),
                $this->statusField($request),
                $this->assignedUserTextField(),
            ]),
            \Laravel\Nova\Fields\Stack::make(__('Ticket Info'), [
                $this->typeField($request),
                $this->titleField(),
                $this->relationshipField($request),
                $this->estimatedHoursField($request),
                $this->infoField($request),
            ]),
            $this->historyField(),
        ];

        return array_map(function ($field) {
            return $field->onlyOnIndex();
        }, $fields);
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
            new filters\StoryStatusFilter(),
            new Filters\TaggableTypeFilter(),
            new filters\StoryTypeFilter(),
            new filters\CustomerStoryWithDeadlineFilter(),
            new filters\StoryWithoutTagsFilter(),
            new filters\StoryWithMultipleTagsFilter(),
        ];
    }

    public function cards(NovaRequest $request)
    {
        // Return empty array to remove all cards
        return [];
    }

    public function actions(NovaRequest $request)
    {
        return [
            (new actions\ChangeStatus())
                ->showInline()
                ->confirmText(__('Seleziona il nuovo stato per il ticket. Clicca su "Conferma" per salvare o "Annulla" per cancellare.'))
                ->confirmButtonText(__('Conferma'))
                ->cancelButtonText(__('Annulla')),
            (new DuplicateStory)->showInline()
        ];
    }
}
