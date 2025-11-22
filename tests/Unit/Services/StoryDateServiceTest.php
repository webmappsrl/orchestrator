<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Models\Story;
use App\Models\StoryLog;
use App\Models\User;
use App\Services\StoryDateService;
use App\Enums\StoryStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Carbon\Carbon;

class StoryDateServiceTest extends TestCase
{
    use RefreshDatabase;

    protected StoryDateService $dateService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dateService = new StoryDateService();
    }

    /** @test */
    public function it_returns_the_most_recent_release_date_when_multiple_release_dates_exist()
    {
        // Create a user for the story logs
        $user = User::factory()->create();

        // Create a story
        $story = Story::factory()->create([
            'status' => StoryStatus::Released->value,
        ]);

        // Create multiple story logs with different release dates
        // First release: 30/10/2025
        StoryLog::create([
            'story_id' => $story->id,
            'user_id' => $user->id,
            'viewed_at' => Carbon::create(2025, 10, 30, 10, 0, 0),
            'changes' => [
                'status' => StoryStatus::Released->value,
            ],
        ]);

        // Second release (more recent): 19/11/2025
        StoryLog::create([
            'story_id' => $story->id,
            'user_id' => $user->id,
            'viewed_at' => Carbon::create(2025, 11, 19, 14, 28, 54),
            'changes' => [
                'status' => StoryStatus::Released->value,
            ],
        ]);

        // Calculate the release date
        $releasedAt = $this->dateService->calculateReleasedAt($story);

        // Assert that it returns the most recent date (19/11/2025)
        $this->assertNotNull($releasedAt);
        $this->assertEquals(
            Carbon::create(2025, 11, 19, 14, 28, 54)->format('Y-m-d H:i:s'),
            $releasedAt->format('Y-m-d H:i:s')
        );
        $this->assertEquals('19/11/2025', $releasedAt->format('d/m/Y'));
    }

    /** @test */
    public function it_returns_null_when_no_release_date_exists()
    {
        // Create a user
        $user = User::factory()->create();

        // Create a story that was never released
        $story = Story::factory()->create([
            'status' => StoryStatus::Progress->value,
        ]);

        // Create a story log without release status
        StoryLog::create([
            'story_id' => $story->id,
            'user_id' => $user->id,
            'viewed_at' => Carbon::now(),
            'changes' => [
                'status' => StoryStatus::Progress->value,
            ],
        ]);

        // Calculate the release date
        $releasedAt = $this->dateService->calculateReleasedAt($story);

        // Assert that it returns null
        $this->assertNull($releasedAt);
    }

    /** @test */
    public function it_returns_the_most_recent_done_date_when_multiple_done_dates_exist()
    {
        // Create a user
        $user = User::factory()->create();

        // Create a story
        $story = Story::factory()->create([
            'status' => StoryStatus::Done->value,
        ]);

        // Create multiple story logs with different done dates
        // First done: 15/11/2025
        StoryLog::create([
            'story_id' => $story->id,
            'user_id' => $user->id,
            'viewed_at' => Carbon::create(2025, 11, 15, 10, 0, 0),
            'changes' => [
                'status' => StoryStatus::Done->value,
            ],
        ]);

        // Second done (more recent): 20/11/2025
        StoryLog::create([
            'story_id' => $story->id,
            'user_id' => $user->id,
            'viewed_at' => Carbon::create(2025, 11, 20, 16, 0, 0),
            'changes' => [
                'status' => StoryStatus::Done->value,
            ],
        ]);

        // Calculate the done date
        $doneAt = $this->dateService->calculateDoneAt($story);

        // Assert that it returns the most recent date (20/11/2025)
        $this->assertNotNull($doneAt);
        $this->assertEquals(
            Carbon::create(2025, 11, 20, 16, 0, 0)->format('Y-m-d H:i:s'),
            $doneAt->format('Y-m-d H:i:s')
        );
    }

    /** @test */
    public function it_updates_story_dates_correctly_with_multiple_release_dates()
    {
        // Create a user
        $user = User::factory()->create();

        // Create a story
        $story = Story::factory()->create([
            'status' => StoryStatus::Released->value,
            'released_at' => null,
        ]);

        // Create multiple story logs with different release dates
        // First release: 30/10/2025
        StoryLog::create([
            'story_id' => $story->id,
            'user_id' => $user->id,
            'viewed_at' => Carbon::create(2025, 10, 30, 10, 0, 0),
            'changes' => [
                'status' => StoryStatus::Released->value,
            ],
        ]);

        // Second release (more recent): 19/11/2025
        StoryLog::create([
            'story_id' => $story->id,
            'user_id' => $user->id,
            'viewed_at' => Carbon::create(2025, 11, 19, 14, 28, 54),
            'changes' => [
                'status' => StoryStatus::Released->value,
            ],
        ]);

        // Update the story dates
        $updatedStory = $this->dateService->updateDates($story);

        // Assert that released_at is set to the most recent date (19/11/2025)
        $this->assertNotNull($updatedStory->released_at);
        $this->assertEquals('19/11/2025', $updatedStory->released_at->format('d/m/Y'));
    }
}

