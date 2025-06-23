<?php

namespace Tests\Feature\Nova;

use App\Enums\StoryStatus;
use App\Models\Deadline;
use Tests\TestCase;
use App\Models\Story;
use App\Models\User;
use App\Models\Tag;
use App\Nova\Actions\DuplicateStory;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Nova\Fields\ActionFields;
use Illuminate\Support\Facades\Log;

class DuplicateStoryTest extends TestCase
{
    use DatabaseTransactions;

    /*** ─── Helpers ────**/

    private function createFullStory(): Story
    {
        $story = Story::factory()->create();
        // BelongsTo
        $story->developer()->associate($dev = User::factory()->create());
        $story->creator()->associate($creator = User::factory()->create());
        $story->tester()->associate($tester = User::factory()->create());
        $story->user()->associate($user = User::factory()->create());
        $story->parentStory()->associate($parent = Story::factory()->create());

        // BelongsToMany
        $story->tags()->sync($tags = Tag::factory(3)->create()->pluck('id'));
        $story->participants()->sync($participants = User::factory(2)->create()->pluck('id'));
        //$story->childStories()->sync($children = Story::factory(2)->create()->pluck('id'));
        $story->deadlines()->sync($deadlines = Deadline::factory(2)->create()->pluck('id'));

        $story->save();
        return $story->fresh();
    }

    private function assertStoryCloned(Story $original, Story $duplicate): void
    {
        $this->assertNotEquals($original->id, $duplicate->id);
        $this->assertEquals($original->title, $duplicate->title);
        $this->assertEquals($original->description, $duplicate->description);
        $this->assertEquals(StoryStatus::New->value, $duplicate->status);

        // BelongsTo
        $this->assertEquals($original->developer_id, $duplicate->developer_id);
        //$this->assertEquals($original->creator_id, $duplicate->creator_id);
        $this->assertEquals($original->tester_id, $duplicate->tester_id);
        //$this->assertEquals($original->parent_story_id, $duplicate->parent_story_id);

        // BelongsToMany
        $this->assertEquals($original->tags->pluck('id'), $duplicate->tags->pluck('id'));
        $this->assertEquals($original->participants->pluck('id'), $duplicate->participants->pluck('id'));
        //$this->assertEquals($original->childStories->pluck('id'), $duplicate->childStories->pluck('id'));
        $this->assertEquals($original->deadlines->pluck('id'), $duplicate->deadlines->pluck('id'));
    }

    /** @test */
    public function test_duplicate_story_with_all_relations_and_returns_open_tab_url()
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $originalStory = $this->createFullStory();

        $action = new DuplicateStory();
        $fields = new ActionFields(collect(), collect());
        $result = $action->handle($fields, collect([$originalStory]));

        // Trova la nuova storia clonata
        $newStory = Story::where('id', '!=', $originalStory->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($newStory, 'New duplicate story not found.');

        $newStory->status = StoryStatus::New->value;
        $newStory->user_id = $user->id;
        $this->assertStoryCloned($originalStory, $newStory);

        $this->assertStringContainsString(
            "/resources/developer-stories/{$newStory->id}/edit",
            $result->jsonSerialize()['openInNewTab']
        );
    }

    /** @test */
    public function test_duplicate_multiple_stories_without_returning_url()
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $originalStories = collect();
        $numberOfOriginalStories = 3;
        // creare due storie nuove
        for ($i = 0; $i < $numberOfOriginalStories; $i++) {
            $originalStory = $this->createFullStory();
            $originalStories->push($originalStory);
        }

        $action = new DuplicateStory();
        $fields = new ActionFields(collect(), collect());
        $result = $action->handle($fields, $originalStories);

        //Trova l'ultima storia duplicata
        $latestID =  Story::latest('id')->value('id');
        // Verifica che il numero di storie duplicate sia corretto
        for ($i = 0; $i < $numberOfOriginalStories; $i++) {
            // Calcolo l'ID del duplicato in ordine decrescente
            $duplicatedID = $latestID - $i;
            $latestDuplicatedStory = Story::find($duplicatedID);
            // Recupero la storia originale corrispondente invertendo l'ordine
            $latestOriginalStory = $originalStories[$numberOfOriginalStories - 1 - $i];
            $this->assertStoryCloned($latestOriginalStory, $latestDuplicatedStory);
        }
    }
}
