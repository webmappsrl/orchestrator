<?php

namespace Tests\Unit;

use App\Models\Tag;
use App\Models\TagGroup;
use App\Models\TagGroupCondition;
use App\Models\Story;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TagGroupTest extends TestCase
{
    use RefreshDatabase;

    public function test_stories_returns_empty_when_no_conditions(): void
    {
        $group = TagGroup::factory()->create();

        $this->assertCount(0, $group->stories);
    }

    public function test_stories_single_or_group(): void
    {
        $tagA = Tag::factory()->create(['name' => 'osm2cai']);
        $tagB = Tag::factory()->create(['name' => 'wm-package']);
        $group = TagGroup::factory()->create();

        TagGroupCondition::create(['tag_group_id' => $group->id, 'tag_id' => $tagA->id, 'group_index' => 0]);
        TagGroupCondition::create(['tag_group_id' => $group->id, 'tag_id' => $tagB->id, 'group_index' => 0]);

        $storyA = Story::factory()->create();
        $storyA->tags()->syncWithoutDetaching([$tagA->id]);
        $group->syncForStory($storyA);

        $storyB = Story::factory()->create();
        $storyB->tags()->syncWithoutDetaching([$tagB->id]);
        $group->syncForStory($storyB);

        $storyC = Story::factory()->create();
        $group->syncForStory($storyC);

        $results = $group->stories()->pluck('stories.id');

        $this->assertContains($storyA->id, $results);
        $this->assertContains($storyB->id, $results);
        $this->assertNotContains($storyC->id, $results);
    }

    public function test_stories_two_and_groups(): void
    {
        $tagQuarter = Tag::factory()->create(['name' => '25Q4']);
        $tagCustomer = Tag::factory()->create(['name' => 'ClienteX']);
        $group = TagGroup::factory()->create();

        TagGroupCondition::create(['tag_group_id' => $group->id, 'tag_id' => $tagQuarter->id, 'group_index' => 0]);
        TagGroupCondition::create(['tag_group_id' => $group->id, 'tag_id' => $tagCustomer->id, 'group_index' => 1]);

        $storyMatch = Story::factory()->create();
        $storyMatch->tags()->syncWithoutDetaching([$tagQuarter->id, $tagCustomer->id]);
        $group->syncForStory($storyMatch);

        $storyOnlyQuarter = Story::factory()->create();
        $storyOnlyQuarter->tags()->syncWithoutDetaching([$tagQuarter->id]);
        $group->syncForStory($storyOnlyQuarter);

        $storyOnlyCustomer = Story::factory()->create();
        $storyOnlyCustomer->tags()->syncWithoutDetaching([$tagCustomer->id]);
        $group->syncForStory($storyOnlyCustomer);

        $results = $group->stories()->pluck('stories.id');

        $this->assertContains($storyMatch->id, $results);
        $this->assertNotContains($storyOnlyQuarter->id, $results);
        $this->assertNotContains($storyOnlyCustomer->id, $results);
    }

    public function test_stories_three_and_groups_with_or_in_first(): void
    {
        $tagQ1 = Tag::factory()->create(['name' => '26Q1']);
        $tagQ2 = Tag::factory()->create(['name' => '26Q2']);
        $tagCustomer = Tag::factory()->create(['name' => 'ClienteX']);
        $tagRepo = Tag::factory()->create(['name' => 'wm-package']);
        $group = TagGroup::factory()->create();

        TagGroupCondition::create(['tag_group_id' => $group->id, 'tag_id' => $tagQ1->id, 'group_index' => 0]);
        TagGroupCondition::create(['tag_group_id' => $group->id, 'tag_id' => $tagQ2->id, 'group_index' => 0]);
        TagGroupCondition::create(['tag_group_id' => $group->id, 'tag_id' => $tagCustomer->id, 'group_index' => 1]);
        TagGroupCondition::create(['tag_group_id' => $group->id, 'tag_id' => $tagRepo->id, 'group_index' => 2]);

        $storyMatch = Story::factory()->create();
        $storyMatch->tags()->syncWithoutDetaching([$tagQ2->id, $tagCustomer->id, $tagRepo->id]);
        $group->syncForStory($storyMatch);

        $storyNoRepo = Story::factory()->create();
        $storyNoRepo->tags()->syncWithoutDetaching([$tagQ1->id, $tagCustomer->id]);
        $group->syncForStory($storyNoRepo);

        $results = $group->stories()->pluck('stories.id');

        $this->assertContains($storyMatch->id, $results);
        $this->assertNotContains($storyNoRepo->id, $results);
    }

    public function test_sync_stories_populates_pivot(): void
    {
        $tag = Tag::factory()->create(['name' => 'osm2cai']);
        $group = TagGroup::factory()->create();
        TagGroupCondition::create(['tag_group_id' => $group->id, 'tag_id' => $tag->id, 'group_index' => 0]);

        $story = Story::factory()->create();
        $story->tags()->syncWithoutDetaching([$tag->id]);

        $group->syncStories();

        $this->assertContains($story->id, $group->stories()->pluck('stories.id'));
    }

    public function test_tag_group_extends_tag(): void
    {
        $group = TagGroup::factory()->create();
        $this->assertInstanceOf(Tag::class, $group);
    }

    public function test_tagged_returns_same_stories_as_stories_relation(): void
    {
        $tag = Tag::factory()->create(['name' => 'some-tag']);
        $group = TagGroup::factory()->create();
        TagGroupCondition::create(['tag_group_id' => $group->id, 'tag_id' => $tag->id, 'group_index' => 0]);

        $story = Story::factory()->create(['hours' => 5]);
        $story->tags()->syncWithoutDetaching([$tag->id]);
        $group->syncStories();

        $this->assertContains($story->id, $group->tagged()->pluck('stories.id'));
    }

    public function test_get_total_hours_sums_story_hours(): void
    {
        $tag = Tag::factory()->create(['name' => 'some-tag']);
        $group = TagGroup::factory()->create();
        TagGroupCondition::create(['tag_group_id' => $group->id, 'tag_id' => $tag->id, 'group_index' => 0]);

        $story1 = Story::factory()->create(['hours' => 3]);
        $story2 = Story::factory()->create(['hours' => 7]);
        $story1->tags()->syncWithoutDetaching([$tag->id]);
        $story2->tags()->syncWithoutDetaching([$tag->id]);
        $group->syncStories();

        $this->assertEquals(10, $group->getTotalHoursAttribute());
    }

    public function test_sal_ticket_counts_returns_closed_and_total(): void
    {
        $tag = Tag::factory()->create(['name' => 'some-tag']);
        $group = TagGroup::factory()->create();
        TagGroupCondition::create(['tag_group_id' => $group->id, 'tag_id' => $tag->id, 'group_index' => 0]);

        $done = Story::factory()->create(['status' => \App\Enums\StoryStatus::Done->value]);
        $open = Story::factory()->create(['status' => \App\Enums\StoryStatus::New->value]);
        $done->tags()->syncWithoutDetaching([$tag->id]);
        $open->tags()->syncWithoutDetaching([$tag->id]);
        $group->syncStories();

        [$closed, $total] = $group->salTicketCounts();
        $this->assertEquals(1, $closed);
        $this->assertEquals(2, $total);
    }

    public function test_estimate_is_sum_of_matched_story_estimates(): void
    {
        $tag = Tag::factory()->create(['name' => 'some-tag']);
        $group = TagGroup::factory()->create();
        TagGroupCondition::create(['tag_group_id' => $group->id, 'tag_id' => $tag->id, 'group_index' => 0]);

        $story1 = Story::factory()->create(['estimated_hours' => 10]);
        $story2 = Story::factory()->create(['estimated_hours' => 20]);
        $story1->tags()->syncWithoutDetaching([$tag->id]);
        $story2->tags()->syncWithoutDetaching([$tag->id]);
        $group->syncStories();

        $this->assertEquals(30, $group->estimate);
    }

    public function test_sync_stories_called_explicitly_after_condition_change(): void
    {
        $tag = Tag::factory()->create(['name' => 'beta-tag']);
        $group = TagGroup::factory()->create();

        $story = Story::factory()->create();
        $story->tags()->syncWithoutDetaching([$tag->id]);

        $this->assertCount(0, $group->stories);

        // Il sync ora è lazy: va chiamato esplicitamente (come fa la Nova resource al render)
        TagGroupCondition::create(['tag_group_id' => $group->id, 'tag_id' => $tag->id, 'group_index' => 0]);
        $group->syncStories();

        $this->assertContains($story->id, $group->fresh()->stories()->pluck('stories.id'));
    }
}
