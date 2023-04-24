<?php

namespace App\Nova\Actions;

use App\Models\Epic;
use App\Models\Project;
use App\Models\Milestone;
use Laravel\Nova\Fields\ID;
use Illuminate\Bus\Queueable;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Fields\FormData;
use Illuminate\Support\Collection;
use Laravel\Nova\Fields\ActionFields;
use Illuminate\Queue\InteractsWithQueue;
use Laravel\Nova\Http\Requests\NovaRequest;

class MoveStoriesFromEpic extends Action
{
    use InteractsWithQueue, Queueable;

    /**
     * The displayable name of the action.
     *
     * @var string
     */
    public $name = 'Move to another Epic';

    /**
     * Perform the action on the given models.
     *
     * @param  \Laravel\Nova\Fields\ActionFields  $fields
     * @param  \Illuminate\Support\Collection  $models
     * @return mixed
     */
    public function handle(ActionFields $fields, Collection $models)
    {
        $epic = Epic::find($fields['epic_id']);

        foreach ($models as $story) {
            $story->epic()->associate($epic);
            $story->project_id = $epic->project_id;
            $story->save();
        }
    }

    /**
     * Get the fields available on the action.
     *
     * @param  \Laravel\Nova\Http\Requests\NovaRequest  $request
     * @return array
     */
    public function fields(NovaRequest $request,)
    {
        $resourceId = $request->query('viaResourceId');
        $epic = Epic::find($resourceId);
        if ($epic) {
            $project = Project::where('id', $epic->project_id)->first();
            $milestone = Milestone::where('id', $epic->milestone_id)->first();
        }

        //create 3 select: the first one is for chose the project from every existing project. The second one is for chose the milestone from every existing milestone. The third one is for chose the epic to move the story to. Epic select Options must change based on the project selected, or milestone selected. Default select for project and milestone should be the current project and milestone of the epic the stories belongs to.

        return [

            Select::make('Project', 'project_id')
                ->options(Project::all()->pluck('name', 'id'))
                ->displayUsingLabels()
                ->rules('required')
                ->default($project->id ?? null)
                ->searchable(),


            Select::make('Milestone', 'milestone_id')
                ->options(Milestone::all()->pluck('name', 'id'))
                ->displayUsingLabels()
                ->rules('required')
                ->default($milestone->id ?? null)
                ->searchable(),


            Select::make('Epic', 'epic_id')
                ->dependsOn(['milestone_id', 'project_id'], function (Select $field, NovaRequest $request, FormData $formData) {
                    $field->options(Epic::where('project_id', $formData->project_id)->where('milestone_id', $formData->milestone_id)->pluck('name', 'id'));
                })
                ->displayUsingLabels()
                ->rules('required')
                ->searchable(),

        ];
    }
}
