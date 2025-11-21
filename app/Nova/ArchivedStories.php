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
            \Laravel\Nova\Fields\ID::make()->sortable(),
            $this->statusField($request),
            \Laravel\Nova\Fields\Stack::make(__('Title'), [
                $this->typeField($request),
                $this->titleField(),
                $this->relationshipField($request),
            ]),
            \Laravel\Nova\Fields\Stack::make(__('ASSIGNED/HOURS'), [
                $this->assignedToField(),
                $this->estimatedHoursField($request),
                $this->effectiveHoursField($request),
            ]),
            $this->infoField($request),
            $this->createdAtField(),
            \Laravel\Nova\Fields\DateTime::make(__('Updated At'), 'updated_at')
                ->sortable()
                ->displayUsing(function ($updatedAt) {
                    if (!$updatedAt) {
                        return '-';
                    }
                    return \Carbon\Carbon::parse($updatedAt)->format('d/m/Y');
                }),
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
            (new DuplicateStory)->showInline()
        ];
    }
}
