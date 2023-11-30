<?php

namespace App\Nova\Actions;

use App\Models\User;
use App\Models\Story;
use App\Enums\UserRole;
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
            $lineParts = explode(':', $line, 2);
            if (count($lineParts) === 2) {
                $story->name = $lineParts[0];
                $descriptionAndRequest = explode('|', $lineParts[1], 2);
                $story->description = $descriptionAndRequest[0] ?? '';
                $story->customer_request = $descriptionAndRequest[1] ?? '';
            } else {
                $nameAndRequest = explode('|', $line, 2);
                $story->name = $nameAndRequest[0];
                $story->description = '';
                $story->customer_request = $nameAndRequest[1] ?? '';
            }

            $story->type = $type;
            $story->status = StoryStatus::New;
            $story->user_id = $user->id;
            $story->save();
            $stories[] = $story;
        }

        //associate the stories to the deadlines
        foreach ($stories as $story) {
            if ($deadlines != null)
                $story->deadlines()->attach($deadlines, ['deadlineable_type' => Story::class]);
        }

        //associate the stories to the backlog stories of the current project
        $project = $models->first();
        $project->backlogStories()->saveMany($stories);

        return Action::message('Stories added to backlog!');
    }

    public function fields(NovaRequest $request)
    {
        $isCustomer = $request->user()->hasRole(UserRole::Customer);
        return [
            Select::make('User')
                ->options(User::all()->pluck('name', 'id'))
                ->displayUsingLabels(),

            Select::make('Type')
                ->options(collect(StoryType::cases())->pluck('name', 'value'))
                ->default(StoryType::Feature->value)
                ->displayUsingLabels(),

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
                )->displayUsingLabels()
                ->canSee(function () use ($isCustomer) {
                    return !$isCustomer;
                }),
            Textarea::make('Text Stories'),
        ];
    }
}
