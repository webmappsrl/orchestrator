<?php

namespace App\Nova\Actions;

use App\Models\Epic;
use App\Models\User;
use App\Models\Project;
use App\Enums\EpicStatus;
use App\Models\Milestone;
use Illuminate\Bus\Queueable;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Fields\Status;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Fields\Textarea;
use Illuminate\Support\Collection;
use Laravel\Nova\Fields\ActionFields;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Http\Requests\NovaRequest;

class createNewEpicFromStoriesAction extends Action
{
    use InteractsWithQueue, Queueable;

    /**
     * The displayable name of the action.
     *
     * @var string
     */
    public $name = 'Create a new epic from selected stories';


    /**
     * Perform the action on the given models.
     *
     * @param  \Laravel\Nova\Fields\ActionFields  $fields
     * @param  \Illuminate\Support\Collection  $models
     * @return mixed
     */
    public function handle(ActionFields $fields, Collection $models)
    {
        //create a new epic that belongs to the project where the action is performed and assign the selected story to it
        $epic = Epic::create([
            'name' => $fields['name'],
            'description' => $fields['description'],
            'user_id' => $fields['user_id'],
            'milestone_id' => $fields['milestone_id'],
            // project_id must be the same project we perform the action on (the project where the stories are)
            'project_id' => $fields['project_id'],
            'status' => EpicStatus::New
        ]);

        foreach ($models as $story) {
            //attach each selected story to the selected epic
            $story->epic_id = $epic->id;
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
        $project = Project::where('id', $request->query('viaResourceId'))->first() ?? null;
        $options = $project ? [$project->id => $project->name] : [];
        return [
            ID::make()->sortable(),
            Text::make('Name', 'name'),
            Textarea::make('Description', 'description'),
            Select::make('User', 'user_id')
                ->options(User::all()->pluck('name', 'id'))
                ->searchable(),
            Select::make('Milestone', 'milestone_id')
                ->options(Milestone::all()->pluck('name', 'id'))
                ->searchable(),
            Select::make('Project', 'project_id')
                ->options($options)
                ->default($project ? $project->id : null),
        ];
    }
}
