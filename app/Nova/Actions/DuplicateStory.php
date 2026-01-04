<?php

namespace App\Nova\Actions;

use App\Models\Story;
use App\Enums\StoryStatus;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Collection;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Fields\ActionFields;
use Laravel\Nova\Http\Requests\NovaRequest;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

class DuplicateStory extends Action
{
    use InteractsWithQueue, Queueable;

    public $name = 'Duplicate Story';

    /**
     * Perform the action on the given models.
     *
     * @param  \Laravel\Nova\Fields\ActionFields  $fields
     * @param  \Illuminate\Support\Collection  $models
     * @return mixed
     */
    public function handle(ActionFields $fields, Collection $models)
    {
        $user = Auth::user();

        foreach ($models as $story) {
            $story->user_id = $user->id;
            // Remove status, conditional fields, and timestamps to avoid validation errors during creation
            $storyData = $story->toArray();
            
            // Save problem_reason and waiting_reason if present before removing them
            $problemReason = $storyData['problem_reason'] ?? null;
            $waitingReason = $storyData['waiting_reason'] ?? null;
            
            unset(
                $storyData['id'],
                $storyData['status'],
                $storyData['waiting_reason'],
                $storyData['problem_reason'],
                $storyData['created_at'],
                $storyData['updated_at'],
                $storyData['released_at'],
                $storyData['done_at']
            );
            
            // Add (COPY) to the title
            if (isset($storyData['name'])) {
                $storyData['name'] = $storyData['name'] . ' (COPY)';
            }
            
            $newStory = Story::create($storyData);
            $newStory->status = StoryStatus::New->value;
            
            // Restore problem_reason and waiting_reason if they were present
            if (!empty($problemReason)) {
                $newStory->problem_reason = $problemReason;
            }
            if (!empty($waitingReason)) {
                $newStory->waiting_reason = $waitingReason;
            }
            
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
     * @param  \Laravel\Nova\Http\Requests\NovaRequest  $request
     * @return array
     */
    public function fields(NovaRequest $request)
    {
        return [];
    }
}
