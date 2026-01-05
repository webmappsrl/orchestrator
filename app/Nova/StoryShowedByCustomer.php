<?php

namespace App\Nova;

use App\Enums\UserRole;
use App\Enums\StoryStatus;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Stack;

class StoryShowedByCustomer extends Story
{

    public $hideFields = ['description', 'deadlines', 'updated_at', 'project', 'creator', 'developer', 'relationship', 'tags'];

    public static function label()
    {
        return __('my stories');
    }
    public static function indexQuery(NovaRequest $request, $query)
    {
        $whereNotIn = [StoryStatus::Done->value, StoryStatus::Rejected->value];

        if ($request->user()->hasRole(UserRole::Customer)) {
            return $query
                ->where('creator_id', $request->user()->id)
                ->whereNotIn('status', $whereNotIn);
        }
    }
    public  function fieldsInIndex(NovaRequest $request)
    {
        $fields = [
            Stack::make(__('MAIN INFO'), [
                $this->clickableIdField(),
                $this->statusField($request),
                $this->assignedUserTextField(),
            ]),
            $this->typeField($request),
            $this->infoField($request),
            $this->titleField(),
            $this->relationshipField($request),
            $this->estimatedHoursField($request),
            $this->historyField(),
            $this->deadlineField($request),

        ];
        return array_map(function ($field) {
            return $field->onlyOnIndex();
        }, $fields);
    }
    public function statusField($view, $fieldName = 'status')
    {
        return  parent::statusField($view)->readonly(function ($request) {
            return  $this->resource->status !== StoryStatus::Released->value;
        });
    }

    public function getOptions(): array
    {
        if (!$this->resource || $this->resource->status == null) {
            $statusValue = StoryStatus::New->value;
        } else {
            $statusValue = $this->resource->status;
        }
        
        $statusLabel = $this->getStatusLabel($statusValue);
        $storyStatusOptions = [
            StoryStatus::Done->value => StoryStatus::Done,
            ...(is_array($statusLabel) ? $statusLabel : [])
        ];

        return $storyStatusOptions;
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
            new filters\StoryStatusFilter,
            new filters\StoryTypeFilter,
        ];
    }


    public function cards(NovaRequest $request)
    {
        return parent::cards($request);
    }
}
