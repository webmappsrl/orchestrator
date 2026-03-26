<?php

namespace App\Nova\Actions;

use App\Enums\StoryStatus;
use App\Models\Story;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Fields\ActionFields;
use Laravel\Nova\Http\Requests\NovaRequest;

class DuplicateStory extends Action
{
    use InteractsWithQueue, Queueable;

    public $name = 'Duplicate Story';

    /**
     * Perform the action on the given models.
     *
     * @return mixed
     */
    public function handle(ActionFields $fields, Collection $models)
    {
        $user = Auth::user();

        foreach ($models as $story) {
            $story->user_id = $user->id;
            $newStory = Story::create($story->toArray());
            $newStory->status = StoryStatus::New->value;
            $newStory->saveQuietly();

            // belongsTo
            $newStory->developer()->associate($story->developer);
            $newStory->tester()->associate($story->tester);
            $newStory->parentStory()->associate($story->parentStory);

            // belongsToMany
            $participantsIds = $story->participants->pluck('id')->toArray();
            $newStory->participants()->sync($participantsIds);

            $childStoryIds = $story->childStories->pluck('id')->toArray();
            $newStory->childStories()->sync($childStoryIds);

            $tagIds = $story->tags->pluck('id')->toArray();
            $newStory->tags()->sync($tagIds);

            $newStory->save();
        }

        if ($models->count() === 1 && isset($newStory)) {
            $resourceName = 'developer-stories';
            $newModelId = $newStory->id;
            $editUrl = url("/resources/{$resourceName}/{$newModelId}/edit");

            return Action::openInNewTab($editUrl);
        }
    }

    /**
     * Get the fields available on the action.
     *
     * @return array
     */
    public function fields(NovaRequest $request)
    {
        return [];
    }
}
