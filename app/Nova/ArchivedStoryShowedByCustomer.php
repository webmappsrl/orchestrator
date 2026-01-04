<?php

namespace App\Nova;

use App\Enums\UserRole;
use App\Enums\StoryStatus;
use Illuminate\Http\Request;
use App\Nova\Actions\MoveStoriesFromEpic;
use Laravel\Nova\Http\Requests\NovaRequest;
use App\Nova\Actions\moveStoriesFromProjectToEpicAction;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Stack;

class ArchivedStoryShowedByCustomer extends Story
{

    public $hideFields = ['description', 'deadlines', 'updated_at', 'project', 'creator', 'developer', 'type', 'relationship', 'tags'];

    public static function label()
    {
        return __('Archived Stories');
    }


    public static function indexQuery(NovaRequest $request, $query)
    {
        $whereIn = [StoryStatus::Done->value,  StoryStatus::Rejected->value];
        return $query
            ->where('creator_id', Auth()->user()->id)
            ->whereIn('status', $whereIn);
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
    /**
     * Get the actions available for the resource.
     *
     * @param  \Laravel\Nova\Http\Requests\NovaRequest  $request
     * @return array
     */
    public function actions(NovaRequest $request)
    {
        if ($request->user()->hasRole(UserRole::Customer)) {
            return [];
        }
        $actions = [
            (new actions\ChangeStatus())
                ->showInline()
                ->confirmText(__('Seleziona il nuovo stato per il ticket. Clicca su "Conferma" per salvare o "Annulla" per cancellare.'))
                ->confirmButtonText(__('Conferma'))
                ->cancelButtonText(__('Annulla')),
            (new actions\EditStories)
                ->confirmText('Edit User and Deadline for the selected stories. Click "Confirm" to save or "Cancel" to delete.')
                ->confirmButtonText('Confirm')
                ->cancelButtonText('Cancel'),
        ];

        if ($request->viaResource == 'projects') {
            array_push($actions, (new moveStoriesFromProjectToEpicAction)
                ->confirmText('Select the epic where you want to move the story. Click on "Confirm" to perform the action or "Cancel" to delete.')
                ->confirmButtonText('Confirm')
                ->cancelButtonText('Cancel'));
            array_push($actions, (new actions\createNewEpicFromStoriesAction)
                ->confirmText('Click on the "Confirm" button to create a new epic with selected stories or "Cancel" to cancel.')
                ->confirmButtonText('Confirm')
                ->cancelButtonText('Cancel'));
        }

        if ($request->viaResource != 'projects') {
            array_push($actions, (new actions\ConvertStoryToEpic)
                ->confirmText('Click on the "Confirm" button to convert the selected stories to epics or "Cancel" to cancel.')
                ->confirmButtonText('Confirm')
                ->cancelButtonText('Cancel')
                ->showInline());
            // moveToBacklogAction rimossa - usare ChangeStatus invece
            array_push($actions, (new MoveStoriesFromEpic)
                ->confirmText('Select the epic where you want to move the story. Click on "Confirm" to perform the action or "Cancel" to delete.')
                ->confirmButtonText('Confirm')
                ->cancelButtonText('Cancel'));
        }

        return $actions;
    }



    public static function authorizedToCreate(Request $request)
    {
        return false;
    }
}
