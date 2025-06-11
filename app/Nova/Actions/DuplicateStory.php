<?php

namespace App\Nova\Actions;

use App\Models\Story;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Collection;
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
     * @param  \Laravel\Nova\Fields\ActionFields  $fields
     * @param  \Illuminate\Support\Collection  $models
     * @return mixed
     */
    public function handle(ActionFields $fields, Collection $models)
    {
        foreach ($models as $story) {
            $newStory = Story::create($story->toArray());
            //createQuietly
    
            $newStory->status = StoryStatus::New->value;
            // belongsTo
            $newStory->developer()->associate($story->developer);
            $newStory->creator()->associate($story->creator);
            $newStory->tester()->associate($story->tester);
            $newStory->project()->associate($story->project);
            $newStory->epic()->associate($story->epic);
            $newStory->parentStory()->associate($story->parentStory);
            $newStory->user()->associate($story->user);
    
            // belongsToMany
            $participantsIds = $story->participants->pluck('id')->toArray();
            $newStory->participants()->sync($participantsIds);

            $childStoryIds = $story->childStories->pluck('id')->toArray();
            $newStory->childStories()->sync($childStoryIds);

            $tagIds = $story->tags->pluck('id')->toArray();
            $newStory->tags()->sync($tagIds);

            $deadlinesIds = $story->deadlines->pluck('id')->toArray();
            $newStory->deadlines()->sync($deadlinesIds);

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
