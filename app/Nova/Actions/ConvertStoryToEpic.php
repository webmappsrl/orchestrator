<?php

namespace App\Nova\Actions;

use App\Models\Epic;
use App\Models\Story;
use App\Enums\EpicStatus;
use App\Models\Milestone;
use App\Enums\StoryStatus;
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


        foreach ($models as $story) {
            $epic = new Epic();
            $epic->name = $story->name;
            $epic->description = $story->description;
            $epic->project_id = $story->project_id;
            $epic->status = EpicStatus::New;
            $epic->user_id = $story->user_id;
            $epic->milestone_id = $fields['milestone'];
            $epic->save();
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
            $story->save();
            $epic->stories()->save($story);
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
        return [
            Select::make('Milestone')
                ->options(Milestone::all()->pluck('name', 'id')->toArray())
                ->displayUsingLabels(),
            Textarea::make('lines'),
        ];
    }
}
