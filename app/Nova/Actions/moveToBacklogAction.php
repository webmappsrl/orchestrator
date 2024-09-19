<?php

namespace App\Nova\Actions;

use App\Enums\StoryStatus;
use Illuminate\Bus\Queueable;
use Laravel\Nova\Actions\Action;
use Illuminate\Support\Collection;
use Laravel\Nova\Fields\ActionFields;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Laravel\Nova\Http\Requests\NovaRequest;

class moveToBacklogAction extends Action
{
    use InteractsWithQueue, Queueable;

    /**
     * The displayable name of the action.
     *
     * @var string
     */
    public $name = 'Move to Backlog';


    /**
     * Perform the action on the given models.
     *
     * @param  \Laravel\Nova\Fields\ActionFields  $fields
     * @param  \Illuminate\Support\Collection  $models
     * @return mixed
     */
    public function handle(ActionFields $fields, Collection $models)
    {
        //remove the relation between the story and the epic and assign the story to the project the epic is related with 
        foreach ($models as $story) {
            if (!$story->project_id) {
                return Action::danger('Story is not related to a project');
            }
            $deadlines = $story->deadlines;
            if ($deadlines->count() > 0) {
                foreach ($deadlines as $deadline) {
                    $deadline->stories()->detach($story->id);
                }
            }
            if ($story->epic_id) {
                $story->epic()->dissociate();
            }
            $story->status = StoryStatus::New;
            $story->save();
        }
    }

    /**
     * Get the fields available on the action.
     *
     * @param  \Laravel\Nova\Http\Requests\NovaRequest  $request
     * @return array
     */
    public function fields(NovaRequest $request)
    {
        return [];
    }

    /**
     * Get the displayable name of the action.
     *
     * @return string
     */
    public function name()
    {
        return __('Move to Backlog');
    }
}
