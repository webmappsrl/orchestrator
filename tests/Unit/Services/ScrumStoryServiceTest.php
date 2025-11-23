<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Models\Story;
use App\Models\StoryLog;
use App\Models\User;
use App\Services\ScrumStoryService;
use App\Enums\StoryStatus;
use App\Enums\StoryType;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class ScrumStoryServiceTest extends TestCase
{
    use DatabaseTransactions;

    private ScrumStoryService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ScrumStoryService();
    }

    /** @test */
    public function it_can_move_scrum_story_to_done_with_user_id()
    {
        // Arrange
        $user = User::factory()->create();
        $story = Story::factory()->create([
            'type' => StoryType::Scrum->value,
            'user_id' => $user->id,
            'status' => StoryStatus::New->value,
        ]);
        $timestamp = Carbon::now();

        // Act
        $result = $this->service->moveToDone($story, $timestamp);

        // Assert
        $this->assertTrue($result);
        $story->refresh();
        $this->assertEquals(StoryStatus::Done->value, $story->status);
        $this->assertNotNull($story->released_at);
        $this->assertNotNull($story->done_at);
        $this->assertEquals($timestamp->format('Y-m-d H:i:s'), $story->released_at->format('Y-m-d H:i:s'));
        $this->assertEquals($timestamp->format('Y-m-d H:i:s'), $story->done_at->format('Y-m-d H:i:s'));

        // Check StoryLog was created
        $log = StoryLog::where('story_id', $story->id)->latest()->first();
        $this->assertNotNull($log);
        $this->assertEquals($user->id, $log->user_id);
        $this->assertEquals(['status' => StoryStatus::Done->value], $log->changes);
    }

    /** @test */
    public function it_does_not_create_log_when_no_user_id_and_no_story_logs()
    {
        // Arrange - Ensure system user doesn't exist or is not used
        $story = Story::factory()->create([
            'type' => StoryType::Scrum->value,
            'user_id' => null,
            'status' => StoryStatus::New->value,
        ]);
        
        // No story logs exist for this story
        $initialLogCount = StoryLog::where('story_id', $story->id)->count();
        $timestamp = Carbon::now();

        // Act
        $result = $this->service->moveToDone($story, $timestamp);

        // Assert
        $this->assertTrue($result);
        $story->refresh();
        $this->assertEquals(StoryStatus::Done->value, $story->status);
        $this->assertNotNull($story->released_at);
        $this->assertNotNull($story->done_at);

        // Check that no StoryLog was created
        $finalLogCount = StoryLog::where('story_id', $story->id)->count();
        $this->assertEquals($initialLogCount, $finalLogCount, 'No log should be created when no user_id is available');
    }

    /** @test */
    public function it_does_not_create_log_when_no_user_id_available()
    {
        // Arrange
        User::where('email', 'orchestrator_artisan@webmapp.it')->delete();
        $story = Story::factory()->create([
            'type' => StoryType::Scrum->value,
            'user_id' => null,
            'status' => StoryStatus::New->value,
        ]);
        
        // No story logs exist for this story
        $initialLogCount = StoryLog::where('story_id', $story->id)->count();

        // Act
        $result = $this->service->moveToDone($story);

        // Assert
        $this->assertTrue($result);
        $story->refresh();
        $this->assertEquals(StoryStatus::Done->value, $story->status);
        $this->assertNotNull($story->released_at);
        $this->assertNotNull($story->done_at);
        
        // Check that no StoryLog was created
        $finalLogCount = StoryLog::where('story_id', $story->id)->count();
        $this->assertEquals($initialLogCount, $finalLogCount, 'No log should be created when no user_id is available');
    }

    /** @test */
    public function it_uses_user_id_from_existing_story_log_when_story_has_no_user_id()
    {
        // Arrange
        $userFromLog = User::factory()->create();
        $story = Story::factory()->create([
            'type' => StoryType::Scrum->value,
            'user_id' => null,
            'status' => StoryStatus::New->value,
        ]);
        
        // Create a story log with a status change (not watch-only)
        StoryLog::create([
            'story_id' => $story->id,
            'user_id' => $userFromLog->id,
            'viewed_at' => Carbon::now()->subDay(),
            'changes' => [
                'status' => StoryStatus::Progress->value,
            ],
        ]);
        
        // Create a watch-only log (should be skipped)
        StoryLog::create([
            'story_id' => $story->id,
            'user_id' => User::factory()->create()->id,
            'viewed_at' => Carbon::now()->subHours(2),
            'changes' => [
                'watch' => Carbon::now()->format('Y-m-d H:i:s'),
            ],
        ]);
        
        $timestamp = Carbon::now();

        // Act
        $result = $this->service->moveToDone($story, $timestamp);

        // Assert
        $this->assertTrue($result);
        $story->refresh();
        $this->assertEquals(StoryStatus::Done->value, $story->status);
        
        // Check StoryLog was created with user from existing log (not watch-only)
        $log = StoryLog::where('story_id', $story->id)
            ->where('changes->status', StoryStatus::Done->value)
            ->first();
        $this->assertNotNull($log);
        $this->assertEquals($userFromLog->id, $log->user_id, 'Should use user_id from non-watch log');
    }

    /** @test */
    public function it_skips_watch_only_logs_when_looking_for_user_id()
    {
        // Arrange
        $userFromLog = User::factory()->create();
        $story = Story::factory()->create([
            'type' => StoryType::Scrum->value,
            'user_id' => null,
            'status' => StoryStatus::New->value,
        ]);
        
        // Create watch-only logs (should be skipped)
        StoryLog::create([
            'story_id' => $story->id,
            'user_id' => User::factory()->create()->id,
            'viewed_at' => Carbon::now()->subDay(),
            'changes' => [
                'watch' => Carbon::now()->format('Y-m-d H:i:s'),
            ],
        ]);
        
        // Create a log with status change (should be used)
        StoryLog::create([
            'story_id' => $story->id,
            'user_id' => $userFromLog->id,
            'viewed_at' => Carbon::now()->subHours(2),
            'changes' => [
                'status' => StoryStatus::Todo->value,
            ],
        ]);
        
        $timestamp = Carbon::now();

        // Act
        $result = $this->service->moveToDone($story, $timestamp);

        // Assert
        $this->assertTrue($result);
        
        // Check StoryLog was created with user from non-watch log
        $log = StoryLog::where('story_id', $story->id)
            ->where('changes->status', StoryStatus::Done->value)
            ->first();
        $this->assertNotNull($log);
        $this->assertEquals($userFromLog->id, $log->user_id, 'Should skip watch-only logs and use user from status change log');
    }

    /** @test */
    public function it_can_get_scrum_stories_for_today()
    {
        // Arrange
        $today = Carbon::today();
        $yesterday = Carbon::yesterday();
        
        $storyToday1 = Story::factory()->create([
            'type' => StoryType::Scrum->value,
            'created_at' => $today,
        ]);
        $storyToday2 = Story::factory()->create([
            'type' => StoryType::Scrum->value,
            'updated_at' => $today,
        ]);
        $storyYesterday = Story::factory()->create([
            'type' => StoryType::Scrum->value,
            'created_at' => $yesterday,
            'updated_at' => $yesterday,
        ]);
        $nonScrumStory = Story::factory()->create([
            'type' => StoryType::Feature->value,
            'created_at' => $today,
        ]);

        // Act
        $stories = $this->service->getScrumStoriesForToday();

        // Assert
        $storyIds = $stories->pluck('id')->toArray();
        $this->assertContains($storyToday1->id, $storyIds, 'Story created today should be included');
        $this->assertContains($storyToday2->id, $storyIds, 'Story updated today should be included');
        $this->assertNotContains($storyYesterday->id, $storyIds, 'Story from yesterday should not be included');
        $this->assertNotContains($nonScrumStory->id, $storyIds, 'Non-scrum story should not be included');
    }

    /** @test */
    public function it_can_get_scrum_stories_for_specific_date()
    {
        // Arrange
        $specificDate = Carbon::parse('2025-11-20')->startOfDay();
        $story = Story::factory()->create([
            'type' => StoryType::Scrum->value,
            'created_at' => $specificDate,
        ]);

        // Act
        $stories = $this->service->getScrumStoriesForToday($specificDate);

        // Assert
        $storyIds = $stories->pluck('id')->toArray();
        $this->assertContains($story->id, $storyIds, 'Story should be in the collection');
    }
}

