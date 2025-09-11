<?php

namespace Tests\Feature;

use App\Enums\StoryStatus;
use App\Models\Project;
use App\Models\Story;
use App\Models\Tag;
use App\Models\User;
use App\Nova\Actions\ConvertStoryToTagAction;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Nova\Fields\ActionFields;
use Tests\TestCase;

class ConvertStoryToTagActionTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $user = User::factory()->create();
        $this->actingAs($user);
    }

    /**
     * Create a random story with random data
     */
    private function createRandomStory(Project $project, array $overrides = []): Story
    {
        return Story::factory()->create(array_merge([
            'project_id' => $project->id,
            'description' => fake()->text(10),
            'estimated_hours' => fake()->randomFloat(1, 1.0, 40.0),
        ], $overrides));
    }

    /** @test */
    public function it_creates_tag_from_story_with_basic_fields()
    {
        $project = Project::factory()->create();
        $story = $this->createRandomStory($project);

        $action = new ConvertStoryToTagAction();
        $actionFields = new ActionFields(collect([
            'tag_name' => null,
            'description' => null,
            'estimate' => null,
        ]), collect([$story]));

        $result = $action->handle($actionFields, collect([$story]));

        $this->assertDatabaseHas('tags', [
            'name' => $story->name,
            'description' => $story->description,
            'estimate' => $story->estimated_hours,
            'taggable_type' => Project::class,
            'taggable_id' => $project->id,
        ]);

        $this->assertDatabaseHas('stories', [
            'id' => $story->id,
            'status' => StoryStatus::Done->value,
        ]);

        $tag = Tag::where('name', $story->name)->first();
        $this->assertTrue($story->tags()->where('tag_id', $tag->id)->exists());
    }

    /** @test */
    public function it_creates_tag_with_custom_name()
    {
        $project = Project::factory()->create();
        $story = $this->createRandomStory($project);

        $action = new ConvertStoryToTagAction();
        $actionFields = new ActionFields(collect([
            'tag_name' => 'Custom Tag Name',
            'description' => null,
            'estimate' => null,
        ]), collect([$story]));

        $action->handle($actionFields, collect([$story]));

        $this->assertDatabaseHas('tags', [
            'name' => 'Custom Tag Name',
            'description' => $story->description,
            'estimate' => $story->estimated_hours,
            'taggable_type' => Project::class,
            'taggable_id' => $project->id,
        ]);

        $this->assertDatabaseHas('stories', [
            'id' => $story->id,
            'status' => StoryStatus::Done->value,
        ]);

        $tag = Tag::where('name', 'Custom Tag Name')->first();
        $this->assertTrue($story->tags()->where('tag_id', $tag->id)->exists());
    }

    /** @test */
    public function it_creates_tag_with_custom_description()
    {
        $project = Project::factory()->create();
        $story = $this->createRandomStory($project);

        $action = new ConvertStoryToTagAction();
        $actionFields = new ActionFields(collect([
            'tag_name' => null,
            'description' => 'Custom Description',
            'estimate' => null,
        ]), collect([$story]));

        $action->handle($actionFields, collect([$story]));

        $this->assertDatabaseHas('tags', [
            'name' => $story->name,
            'description' => 'Custom Description',
            'estimate' => $story->estimated_hours,
            'taggable_type' => Project::class,
            'taggable_id' => $project->id,
        ]);

        $this->assertDatabaseHas('stories', [
            'id' => $story->id,
            'status' => StoryStatus::Done->value,
        ]);

        $tag = Tag::where('name', $story->name)->first();
        $this->assertTrue($story->tags()->where('tag_id', $tag->id)->exists());
    }

    /** @test */
    public function it_creates_tag_with_custom_estimate()
    {
        $project = Project::factory()->create();
        $story = $this->createRandomStory($project);

        $action = new ConvertStoryToTagAction();
        $actionFields = new ActionFields(collect([
            'tag_name' => null,
            'description' => null,
            'estimate' => 8.0,
        ]), collect([$story]));

        $action->handle($actionFields, collect([$story]));

        $this->assertDatabaseHas('tags', [
            'name' => $story->name,
            'description' => $story->description,
            'estimate' => 8.0,
            'taggable_type' => Project::class,
            'taggable_id' => $project->id,
        ]);

        $this->assertDatabaseHas('stories', [
            'id' => $story->id,
            'status' => StoryStatus::Done->value,
        ]);

        $tag = Tag::where('name', $story->name)->where('estimate', 8.0)->first();
        $this->assertTrue($story->tags()->where('tag_id', $tag->id)->exists());
    }

    /** @test */
    public function it_returns_success_message_for_single_tag()
    {
        $project = Project::factory()->create();
        $story = $this->createRandomStory($project);

        $action = new ConvertStoryToTagAction();
        $actionFields = new ActionFields(collect([
            'tag_name' => null,
            'description' => null,
            'estimate' => null,
        ]), collect([$story]));

        $result = $action->handle($actionFields, collect([$story]));

        $tag = Tag::where('name', $story->name)->first();
        $this->assertStringContainsString("'{$tag->name}'", $result->jsonSerialize()['message']);
    }
}
