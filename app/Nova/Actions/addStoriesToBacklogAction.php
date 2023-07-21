<?php

namespace App\Nova\Actions;

use App\Models\User;
use App\Models\Story;
use App\Enums\StoryType;
use App\Models\Deadline;
use App\Enums\StoryStatus;
use App\Enums\DeadlineStatus;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Carbon;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Fields\Textarea;
use Illuminate\Support\Collection;
use Laravel\Nova\Fields\MultiSelect;
use Laravel\Nova\Fields\ActionFields;
use Illuminate\Queue\InteractsWithQueue;
use Laravel\Nova\Http\Requests\NovaRequest;

class addStoriesToBacklogAction extends Action
{
    use InteractsWithQueue, Queueable;

    public $name = 'Add Stories to backlog';

    /**
     * Perform the action on the given models.
     *
     * @param  \Laravel\Nova\Fields\ActionFields  $fields
     * @param  \Illuminate\Support\Collection  $models
     * @return mixed
     */
    public function handle(ActionFields $fields, Collection $models)
    {
        $textArea = $fields['text_stories'];
        $lines = explode(PHP_EOL, $textArea);
        $user = User::find($fields['user']) ?? auth()->user();
        $type = $fields['type'];
        $deadlines = $fields['deadlines'];
        $stories = [];

        foreach ($lines as $line) {
            // Ignore empty lines don't create
            if (empty(trim($line))) {
                continue;
            }
            // Create a new story 
            $story = new Story();
            // divide the line by only the first ":" found to avoid splitting the description
            $lineParts = explode(':', $line, 2);
            $story->name = $lineParts[0];
            $story->description = $lineParts[1] ?? '';
            $story->type = $type;
            $story->status = StoryStatus::New;
            $story->user_id = $user->id;
            $story->save();
            $stories[] = $story;
        }
        //associate the stories to the deadlines
        foreach ($stories as $story) {
            $story->deadlines()->attach($deadlines, ['deadlineable_type' => Story::class]);
        }
        //associate the stories to the backlog stories of the current project
        $project = $models->first();
        $project->backlogStories()->saveMany($stories);
        return Action::message('Stories added to backlog');
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
            Select::make('User')
                ->options(User::all()->pluck('name', 'id'))
                ->displayUsingLabels(),

            Select::make('Type')
                ->options(collect(StoryType::cases())->pluck('name', 'value'))
                ->default(StoryType::Feature->value)
                ->displayUsingLabels(),

            Textarea::make('Text Stories'),

            MultiSelect::make('Deadlines')
                ->options(
                    function () {
                        $deadlines = Deadline::whereNotIn('status', [DeadlineStatus::Expired, DeadlineStatus::Done])->get();
                        $options = [];
                        //order the not expired deadlines by descending due date
                        $deadlines = $deadlines->sortByDesc('due_date');
                        foreach ($deadlines as $deadline) {
                            if (isset($deadline->customer) && $deadline->customer != null) {
                                $customer = $deadline->customer;
                                //format the due_date
                                $formattedDate = Carbon::parse($deadline->due_date)->format('Y-m-d');
                                //add the customer name to the option label
                                $optionLabel = $formattedDate . '    ' . $customer->name . ' ' . $deadline->title;
                            } else {
                                $formattedDate = Carbon::parse($deadline->due_date)->format('Y-m-d');
                                $optionLabel = $formattedDate . '    ' . $deadline->title;
                            }
                            $options[$deadline->id] = $optionLabel;
                        }
                        return $options;
                    }
                )->displayUsingLabels(),
        ];
    }
}
