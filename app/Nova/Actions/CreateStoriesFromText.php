<?php

namespace App\Nova\Actions;

use App\Models\Story;
use App\Enums\StoryStatus;
use Illuminate\Bus\Queueable;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Fields\Textarea;
use Illuminate\Support\Collection;
use Laravel\Nova\Fields\ActionFields;
use Illuminate\Queue\InteractsWithQueue;
use Laravel\Nova\Http\Requests\NovaRequest;

class CreateStoriesFromText extends Action
{
    use InteractsWithQueue;
    use Queueable;

    /**
     * The displayable name of the action.
     *
     * @var string
     */
    public $name = 'Create Stories From Text';

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
        $epic = $models->first();
        $epicId = $epic->id;
        $epicUserId = $epic->user_id;

        foreach ($lines as $line) {
            // Ignore empty lines don't create
            if (empty(trim($line))) {
                continue;
            }
            // Create a new story
            $story = $this->createStory($line, $epicId, $epicUserId);
            $story->save();
        }
    }

    public function fields(NovaRequest $request)
    {
    return [
        Textarea::make('lines'),
    ];
}


    private function createStory($line, $epicId, $epicUserId)
    {
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
        // Associate the story with the epic and user
        $story->status = StoryStatus::New;
        $story->epic_id = $epicId;
        $story->user_id = $epicUserId;

        return $story;
    }
}