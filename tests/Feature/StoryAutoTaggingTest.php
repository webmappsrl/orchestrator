<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Story;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class StoryAutoTaggingTest extends TestCase
{
    use DatabaseTransactions;

    private User $developer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->developer = User::factory()->create([
            'roles' => [UserRole::Developer->value],
        ]);
    }

    /** @test */
    public function quarter_tag_is_attached_on_story_created(): void
    {
        $this->actingAs($this->developer);

        $story = Story::factory()->create([
            'name' => 'Test story created',
        ]);

        $expectedQuarter = \App\Services\TagService::quarterNameForDate($story->created_at);

        $this->assertTrue(
            $story->tags()->where('name', $expectedQuarter)->exists(),
            "Quarter tag '{$expectedQuarter}' not attached on create"
        );
    }

    /** @test */
    public function quarter_tag_is_attached_on_story_updated(): void
    {
        $this->actingAs($this->developer);

        $story = Story::factory()->create(['name' => 'Test story update']);
        $story->tags()->detach();

        $tagService = app(\App\Services\TagService::class);
        $tagService->attachQuarterTagToStory($story);

        $expectedQuarter = \App\Services\TagService::quarterNameForDate($story->created_at);

        $this->assertTrue(
            $story->tags()->where('name', $expectedQuarter)->exists(),
            "Quarter tag '{$expectedQuarter}' not attached on update"
        );
    }

    /** @test */
    public function text_tags_are_attached_on_story_created(): void
    {
        $this->actingAs($this->developer);

        $story = Story::factory()->create([
            'name' => 'Test story with github url',
            'description' => '<p>Fix needed in https://github.com/webmappsrl/wm-app repo</p>',
        ]);

        $this->assertTrue(
            $story->tags()->where('name', 'wm-app')->exists(),
            "Tag 'wm-app' not attached from description URL on create"
        );
    }

    /** @test */
    public function text_tags_are_attached_on_story_updated(): void
    {
        $this->actingAs($this->developer);

        $story = Story::factory()->create(['name' => 'Test story update text tags']);
        $story->tags()->detach();

        $story->description = '<p>See https://github.com/webmappsrl/orchestrator for details</p>';
        $story->saveQuietly();

        $tagService = app(\App\Services\TagService::class);
        $tagService->attachTagsFromTextToStory($story);

        $this->assertTrue(
            $story->tags()->where('name', 'orchestrator')->exists(),
            "Tag 'orchestrator' not attached from description URL on update"
        );
    }

    /** @test */
    public function nova_after_update_attaches_quarter_tag(): void
    {
        $this->actingAs($this->developer);

        $story = Story::factory()->create(['name' => 'Nova update test']);
        $story->tags()->detach();

        \App\Nova\Story::afterUpdate(
            app(\Laravel\Nova\Http\Requests\NovaRequest::class),
            $story
        );

        $expectedQuarter = \App\Services\TagService::quarterNameForDate($story->created_at);

        $this->assertTrue(
            $story->tags()->where('name', $expectedQuarter)->exists(),
            "Quarter tag '{$expectedQuarter}' not attached via Nova afterUpdate"
        );
    }
}
