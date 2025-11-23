<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Models\Story;
use App\Models\StoryLog;
use App\Models\User;
use App\Services\AutoUpdateStoryStatusService;
use App\Enums\StoryStatus;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class AutoUpdateStoryStatusServiceTest extends TestCase
{
    use DatabaseTransactions;

    private AutoUpdateStoryStatusService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new AutoUpdateStoryStatusService();
    }

    /** @test */
    public function it_can_move_released_story_to_done_with_user_id()
    {
        // Arrange
        $user = User::factory()->create();
        $story = Story::factory()->create([
            'status' => StoryStatus::Released->value,
            'user_id' => $user->id,
            'done_at' => null,
        ]);
        $timestamp = Carbon::now();

        // Act
        $result = $this->service->moveToDone($story, $timestamp);

        // Assert
        $this->assertTrue($result);
        $story->refresh();
        $this->assertEquals(StoryStatus::Done->value, $story->status);
        $this->assertNotNull($story->done_at);
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
        // Arrange
        $story = Story::factory()->create([
            'status' => StoryStatus::Released->value,
            'user_id' => null,
            'done_at' => null,
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
            'status' => StoryStatus::Released->value,
            'user_id' => null,
            'done_at' => null,
        ]);
        
        // Create a story log with a status change (not watch-only)
        StoryLog::create([
            'story_id' => $story->id,
            'user_id' => $userFromLog->id,
            'viewed_at' => Carbon::now()->subDay(),
            'changes' => [
                'status' => StoryStatus::Released->value,
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
            'status' => StoryStatus::Released->value,
            'user_id' => null,
            'done_at' => null,
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
                'status' => StoryStatus::Released->value,
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
    public function it_calculates_three_working_days_ago_correctly()
    {
        // Arrange - Test on a Monday (assuming we're not on a weekend)
        $monday = Carbon::parse('2025-11-24'); // Monday
        Carbon::setTestNow($monday);
        
        // Act
        $threeWorkingDaysAgo = $this->service->getThreeWorkingDaysAgo();
        
        // Assert - Should be Wednesday of previous week (3 working days before Monday)
        $this->assertEquals('2025-11-19', $threeWorkingDaysAgo->format('Y-m-d')); // Wednesday
        
        Carbon::setTestNow(); // Reset
    }

    /** @test */
    public function it_skips_weekends_when_calculating_working_days()
    {
        // Arrange - Test on a Monday
        $monday = Carbon::parse('2025-11-24'); // Monday
        Carbon::setTestNow($monday);
        
        // Act
        $threeWorkingDaysAgo = $this->service->getThreeWorkingDaysAgo();
        
        // Assert - Should skip Saturday and Sunday
        $this->assertFalse($threeWorkingDaysAgo->isWeekend(), 'Should not be a weekend');
        $this->assertEquals('2025-11-19', $threeWorkingDaysAgo->format('Y-m-d')); // Wednesday (skipping Sat/Sun)
        
        Carbon::setTestNow(); // Reset
    }

    /** @test */
    public function it_gets_stories_to_update_correctly()
    {
        // Arrange
        $threeWorkingDaysAgo = $this->service->getThreeWorkingDaysAgo();
        $beforeThreeDays = $threeWorkingDaysAgo->copy()->subDay();
        $afterThreeDays = $threeWorkingDaysAgo->copy()->addDay();
        
        // Story that should be updated (updated before 3 working days ago)
        $storyToUpdate = Story::factory()->create([
            'status' => StoryStatus::Released->value,
            'updated_at' => $beforeThreeDays,
        ]);
        
        // Story that should NOT be updated (updated after 3 working days ago)
        $storyNotToUpdate1 = Story::factory()->create([
            'status' => StoryStatus::Released->value,
            'updated_at' => $afterThreeDays,
        ]);
        
        // Story that should NOT be updated (not Released status)
        $storyNotToUpdate2 = Story::factory()->create([
            'status' => StoryStatus::Progress->value,
            'updated_at' => $beforeThreeDays,
        ]);

        // Act
        $stories = $this->service->getStoriesToUpdate();

        // Assert
        $storyIds = $stories->pluck('id')->toArray();
        $this->assertContains($storyToUpdate->id, $storyIds, 'Story updated before 3 working days ago should be included');
        $this->assertNotContains($storyNotToUpdate1->id, $storyIds, 'Story updated after 3 working days ago should not be included');
        $this->assertNotContains($storyNotToUpdate2->id, $storyIds, 'Story with non-Released status should not be included');
    }
}

