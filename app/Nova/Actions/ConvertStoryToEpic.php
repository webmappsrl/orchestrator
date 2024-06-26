<?php

namespace App\Nova\Actions;

use App\Models\Epic;
use App\Models\Story;
use App\Enums\EpicStatus;
use App\Models\Milestone;
use App\Enums\StoryStatus;
use App\Models\Project;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Fields\Textarea;
use Illuminate\Support\Collection;
use Laravel\Nova\Fields\ActionFields;
use Illuminate\Queue\InteractsWithQueue;
use Laravel\Nova\Http\Requests\NovaRequest;

class ConvertStoryToEpic extends Action
{
    use InteractsWithQueue, Queueable;

    /**
     * The displayable name of the action.
     *
     * @var string
     */
    public $name = 'Convert Story to Epic';

    /**
     * Perform the action on the given models.
     *
     * @param  \Laravel\Nova\Fields\ActionFields  $fields
     * @param  \Illuminate\Support\Collection  $models
     * @return mixed
     */
    public function handle(ActionFields $fields, Collection $models)
    {
        $textArea = $fields['lines'];
        $lines = explode(PHP_EOL, $textArea);

        $deadlines = [];
        $story_type = '';
        foreach ($models as $story) {
            $epic = new Epic();
            $epic->name = $story->name;
            $epic->description = $story->description;
            $epic->project_id = $story->project_id ?? $fields['project'];
            $epic->status = EpicStatus::New;
            $epic->user_id = $story->user_id ?? $fields['user'];
            $epic->milestone_id = $fields['milestone'];
            $epic->save();
            $deadlines = $story->deadlines;
            $story_type = $story->type;
            $story->delete();
        }
        foreach ($lines as $line) {
            if (empty(trim($line))) {
                continue;
            }
            $story = new Story();
            $story->name = $line;
            $story->status = StoryStatus::New;
            $story->epic_id = $epic->id;
            $story->user_id = $epic->user_id;
            $story->type = $story_type;
            $story->save();
            if (count($deadlines) > 0) {
                $story->deadlines()->sync($deadlines);
            }
            //$epic->stories()->save($story);
        }
        return Action::visit('/resources/epics/' . $epic->id);
    }

    /**
     * Get the fields available on the action.
     *
     * @param  \Laravel\Nova\Http\Requests\NovaRequest  $request
     * @return array
     */
    public function fields(NovaRequest $request)
    {
        return [
            Select::make('Milestone')
                ->options(Milestone::all()->pluck('name', 'id')->toArray())
                ->displayUsingLabels(),
            Textarea::make('lines'),
            Select::make('User')
                ->options(User::all()->pluck('name', 'id')->toArray())
                ->displayUsingLabels()
                ->help('If empty, it will use the user of the story'),

            Select::make('Project')
                ->options(Project::all()->pluck('name', 'id')->toArray())
                ->displayUsingLabels()
                ->help('If empty, it will use the project of the story')
        ];
    }
}
