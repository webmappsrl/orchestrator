<?php

namespace App\Nova;

use App\Enums\UserRole;
use App\Enums\StoryStatus;
use Laravel\Nova\Http\Requests\NovaRequest;

class StoryShowedByCustomer extends Story
{

    public $hideFields = ['description', 'deadlines', 'info', 'updated_at', 'project', 'creator', 'type'];
    public static function label()
    {
        return __('my stories');
    }
    public static function indexQuery(NovaRequest $request, $query)
    {
        if ($request->user()->hasRole(UserRole::Customer)) {
            return $query->where('creator_id', $request->user()->id)->where('status', '!=', StoryStatus::Done);
        }
    }

    public function statusField($view, $fieldName = 'status')
    {
        return  parent::statusField($view)->readonly(function ($request) {
            return  $this->resource->status !== StoryStatus::Released->value;
        });
    }

    public function getOptions(): array
    {

        if ($this->resource->status == null) {
            $this->resource->status = StoryStatus::New->value;
        }
        $storyStatusOptions = [
            StoryStatus::Done->value => StoryStatus::Done,
            ...$this->getStatusLabel($this->resource->status)
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
}
