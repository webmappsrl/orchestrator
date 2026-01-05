<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Models\Story;
use App\Models\StoryLog;
use App\Models\User;
use App\Services\AutoUpdateWaitingStoriesService;
use App\Enums\StoryStatus;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Collection;

class AutoUpdateWaitingStoriesServiceTest extends TestCase
{
    use DatabaseTransactions;

    private AutoUpdateWaitingStoriesService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new AutoUpdateWaitingStoriesService();
        
        // Set default threshold to 7 days for testing
        config(['orchestrator.autorestore_waiting_days' => 7]);
    }

    /** @test */
    public function it_updates_stories_that_exceed_threshold()
    {
        // Arrange
        $user = User::factory()->create();
        $story = Story::factory()->create([
            'status' => StoryStatus::Waiting->value,
            'waiting_reason' => 'Test reason',
            'creator_id' => $user->id,
        ]);

        // Delete the auto-created log from StoryObserver (it has updated_at in changes)
        StoryLog::where('story_id', $story->id)->delete();

        // Create a log entry showing story entered Waiting 10 days ago
        StoryLog::create([
            'story_id' => $story->id,
            'user_id' => $user->id,
            'viewed_at' => Carbon::now()->subDays(10),
            'changes' => ['status' => StoryStatus::Waiting->value],
        ]);

        // Create a log entry showing previous status was Todo
        StoryLog::create([
            'story_id' => $story->id,
            'user_id' => $user->id,
            'viewed_at' => Carbon::now()->subDays(11),
            'changes' => ['status' => StoryStatus::Todo->value],
        ]);

        // Act
        $result = $this->service->updateWaitingStories(collect([$story]));

        // Assert
        $this->assertEquals(1, $result['success']);
        $this->assertEquals(0, $result['skipped']);
        $this->assertEquals(0, $result['errors']);
        
        $story->refresh();
        $this->assertEquals(StoryStatus::Todo->value, $story->status);
        $this->assertStringContainsString('Rimesso da IN attesa', $story->description);
    }

    /** @test */
    public function it_skips_stories_below_threshold()
    {
        // Arrange
        $user = User::factory()->create();
        $story = Story::factory()->create([
            'status' => StoryStatus::Waiting->value,
            'waiting_reason' => 'Test reason',
            'creator_id' => $user->id,
        ]);

        // Create a log entry showing story entered Waiting 3 days ago (below threshold)
        StoryLog::create([
            'story_id' => $story->id,
            'user_id' => $user->id,
            'viewed_at' => Carbon::now()->subDays(3),
            'changes' => ['status' => StoryStatus::Waiting->value],
        ]);

        // Act
        $result = $this->service->updateWaitingStories(collect([$story]));

        // Assert
        $this->assertEquals(0, $result['success']);
        $this->assertEquals(0, $result['skipped']);
        $this->assertEquals(0, $result['errors']);
        
        $story->refresh();
        $this->assertEquals(StoryStatus::Waiting->value, $story->status);
    }

    /** @test */
    public function it_handles_stories_without_waiting_log()
    {
        // Arrange
        $user = User::factory()->create();
        $story = Story::factory()->create([
            'status' => StoryStatus::Waiting->value,
            'waiting_reason' => 'Test reason',
            'creator_id' => $user->id,
            'updated_at' => Carbon::now()->subDays(10), // Use updated_at as fallback
        ]);

        // Delete the auto-created log from StoryObserver
        StoryLog::where('story_id', $story->id)->delete();

        // Act
        $result = $this->service->updateWaitingStories(collect([$story]));

        // Assert
        // Service uses updated_at as fallback, so if updated_at is 10 days ago (> threshold),
        // it will process the story. Since there's no previous status log, it will default to New.
        // However, if updated_at is recent, it will skip.
        // For this test, we expect it to use updated_at fallback and process it
        $this->assertEquals(1, $result['success']);
        $this->assertEquals(0, $result['skipped']);
        $this->assertEquals(0, $result['errors']);
        
        $story->refresh();
        // Should default to New status since no previous status found
        $this->assertEquals(StoryStatus::New->value, $story->status);
    }

    /** @test */
    public function it_restores_to_previous_status_correctly()
    {
        // Arrange
        $user = User::factory()->create();
        $story = Story::factory()->create([
            'status' => StoryStatus::Waiting->value,
            'waiting_reason' => 'Test reason',
            'creator_id' => $user->id,
        ]);

        // Delete the auto-created log from StoryObserver
        StoryLog::where('story_id', $story->id)->delete();

        // Create a log entry showing story entered Waiting 10 days ago
        StoryLog::create([
            'story_id' => $story->id,
            'user_id' => $user->id,
            'viewed_at' => Carbon::now()->subDays(10),
            'changes' => ['status' => StoryStatus::Waiting->value],
        ]);

        // Create a log entry showing previous status was Assigned
        StoryLog::create([
            'story_id' => $story->id,
            'user_id' => $user->id,
            'viewed_at' => Carbon::now()->subDays(11),
            'changes' => ['status' => StoryStatus::Assigned->value],
        ]);

        // Act
        $result = $this->service->updateWaitingStories(collect([$story]));

        // Assert
        $this->assertEquals(1, $result['success']);
        $story->refresh();
        $this->assertEquals(StoryStatus::Assigned->value, $story->status);
    }

    /** @test */
    public function it_changes_progress_released_done_to_todo()
    {
        // Arrange
        $user = User::factory()->create();
        $previousStatuses = [
            StoryStatus::Progress->value,
            StoryStatus::Released->value,
            StoryStatus::Done->value,
        ];

        foreach ($previousStatuses as $previousStatus) {
            $story = Story::factory()->create([
                'status' => StoryStatus::Waiting->value,
                'waiting_reason' => 'Test reason',
                'creator_id' => $user->id,
            ]);

            // Delete the auto-created log from StoryObserver
            StoryLog::where('story_id', $story->id)->delete();

            // Create a log entry showing story entered Waiting 10 days ago
            StoryLog::create([
                'story_id' => $story->id,
                'user_id' => $user->id,
                'viewed_at' => Carbon::now()->subDays(10),
                'changes' => ['status' => StoryStatus::Waiting->value],
            ]);

            // Create a log entry showing previous status
            StoryLog::create([
                'story_id' => $story->id,
                'user_id' => $user->id,
                'viewed_at' => Carbon::now()->subDays(11),
                'changes' => ['status' => $previousStatus],
            ]);

            // Act
            $result = $this->service->updateWaitingStories(collect([$story]));

            // Assert
            $this->assertEquals(1, $result['success']);
            $story->refresh();
            $this->assertEquals(StoryStatus::Todo->value, $story->status, "Previous status {$previousStatus} should be changed to Todo");
        }
    }

    /** @test */
    public function it_handles_stories_without_previous_status()
    {
        // Arrange
        $user = User::factory()->create();
        $story = Story::factory()->create([
            'status' => StoryStatus::Waiting->value,
            'waiting_reason' => 'Test reason',
            'creator_id' => $user->id,
        ]);

        // Delete the auto-created log from StoryObserver
        StoryLog::where('story_id', $story->id)->delete();

        // Create a log entry showing story entered Waiting 10 days ago
        StoryLog::create([
            'story_id' => $story->id,
            'user_id' => $user->id,
            'viewed_at' => Carbon::now()->subDays(10),
            'changes' => ['status' => StoryStatus::Waiting->value],
        ]);

        // No previous status log exists

        // Act
        $result = $this->service->updateWaitingStories(collect([$story]));

        // Assert
        $this->assertEquals(1, $result['success']);
        $story->refresh();
        // Should default to New status
        $this->assertEquals(StoryStatus::New->value, $story->status);
    }

    /** @test */
    public function it_creates_correct_development_note()
    {
        // Arrange
        $user = User::factory()->create();
        $story = Story::factory()->create([
            'status' => StoryStatus::Waiting->value,
            'waiting_reason' => 'Aspettando risposta cliente',
            'description' => 'Descrizione originale',
            'creator_id' => $user->id,
        ]);

        // Delete the auto-created log from StoryObserver
        StoryLog::where('story_id', $story->id)->delete();

        // Create a log entry showing story entered Waiting 10 days ago
        StoryLog::create([
            'story_id' => $story->id,
            'user_id' => $user->id,
            'viewed_at' => Carbon::now()->subDays(10),
            'changes' => ['status' => StoryStatus::Waiting->value],
        ]);

        // Create a log entry showing previous status was Todo
        StoryLog::create([
            'story_id' => $story->id,
            'user_id' => $user->id,
            'viewed_at' => Carbon::now()->subDays(11),
            'changes' => ['status' => StoryStatus::Todo->value],
        ]);

        // Act
        $result = $this->service->updateWaitingStories(collect([$story]));

        // Assert
        $this->assertEquals(1, $result['success']);
        $story->refresh();
        $this->assertStringContainsString('Rimesso da IN attesa', $story->description);
        $this->assertStringContainsString('Aspettando risposta cliente', $story->description);
        $this->assertStringContainsString('Descrizione originale', $story->description);
        $this->assertStringContainsString('10 giorni', $story->description);
    }

    /** @test */
    public function it_handles_errors_gracefully()
    {
        // Arrange
        $user = User::factory()->create();
        $story = Story::factory()->create([
            'status' => StoryStatus::Waiting->value,
            'waiting_reason' => 'Test reason',
            'creator_id' => $user->id,
        ]);

        // Delete the auto-created log from StoryObserver
        StoryLog::where('story_id', $story->id)->delete();

        // Create a log entry showing story entered Waiting 10 days ago
        StoryLog::create([
            'story_id' => $story->id,
            'user_id' => $user->id,
            'viewed_at' => Carbon::now()->subDays(10),
            'changes' => ['status' => StoryStatus::Waiting->value],
        ]);

        // Create a log entry showing previous status was Todo
        StoryLog::create([
            'story_id' => $story->id,
            'user_id' => $user->id,
            'viewed_at' => Carbon::now()->subDays(11),
            'changes' => ['status' => StoryStatus::Todo->value],
        ]);

        // Mock the story to throw an exception when save() is called
        // We'll use a partial mock to simulate a save failure
        $storyMock = \Mockery::mock($story)->makePartial();
        $storyMock->shouldReceive('save')
            ->once()
            ->andThrow(new \Exception('Database error: Story not found'));

        // Act
        $result = $this->service->updateWaitingStories(collect([$storyMock]));

        // Assert
        // The service should catch the exception and count it as an error
        $this->assertEquals(0, $result['success']);
        $this->assertEquals(1, $result['errors']);
        $this->assertEquals(0, $result['skipped']);
        
        \Mockery::close();
    }

    /** @test */
    public function it_processes_collection_when_provided()
    {
        // Arrange
        $user = User::factory()->create();
        $story1 = Story::factory()->create([
            'status' => StoryStatus::Waiting->value,
            'waiting_reason' => 'Test reason 1',
            'creator_id' => $user->id,
        ]);

        $story2 = Story::factory()->create([
            'status' => StoryStatus::Waiting->value,
            'waiting_reason' => 'Test reason 2',
            'creator_id' => $user->id,
        ]);

        // Delete auto-created logs from StoryObserver and create logs for both stories
        foreach ([$story1, $story2] as $story) {
            StoryLog::where('story_id', $story->id)->delete();

            StoryLog::create([
                'story_id' => $story->id,
                'user_id' => $user->id,
                'viewed_at' => Carbon::now()->subDays(10),
                'changes' => ['status' => StoryStatus::Waiting->value],
            ]);

            StoryLog::create([
                'story_id' => $story->id,
                'user_id' => $user->id,
                'viewed_at' => Carbon::now()->subDays(11),
                'changes' => ['status' => StoryStatus::Todo->value],
            ]);
        }

        // Act
        $result = $this->service->updateWaitingStories(collect([$story1, $story2]));

        // Assert
        $this->assertEquals(2, $result['success']);
        $story1->refresh();
        $story2->refresh();
        $this->assertEquals(StoryStatus::Todo->value, $story1->status);
        $this->assertEquals(StoryStatus::Todo->value, $story2->status);
    }

    /** @test */
    public function it_processes_all_waiting_stories_when_collection_is_null()
    {
        // Arrange
        $user = User::factory()->create();
        $story1 = Story::factory()->create([
            'status' => StoryStatus::Waiting->value,
            'waiting_reason' => 'Test reason 1',
            'creator_id' => $user->id,
        ]);

        $story2 = Story::factory()->create([
            'status' => StoryStatus::Waiting->value,
            'waiting_reason' => 'Test reason 2',
            'creator_id' => $user->id,
        ]);

        // Create a story that is NOT waiting (should not be processed)
        $story3 = Story::factory()->create([
            'status' => StoryStatus::Todo->value,
            'creator_id' => $user->id,
        ]);

        // Delete auto-created logs from StoryObserver and create logs for waiting stories
        foreach ([$story1, $story2] as $story) {
            StoryLog::where('story_id', $story->id)->delete();

            StoryLog::create([
                'story_id' => $story->id,
                'user_id' => $user->id,
                'viewed_at' => Carbon::now()->subDays(10),
                'changes' => ['status' => StoryStatus::Waiting->value],
            ]);

            StoryLog::create([
                'story_id' => $story->id,
                'user_id' => $user->id,
                'viewed_at' => Carbon::now()->subDays(11),
                'changes' => ['status' => StoryStatus::Todo->value],
            ]);
        }

        // Act
        $result = $this->service->updateWaitingStories(null);

        // Assert
        $this->assertEquals(2, $result['success']);
        $story1->refresh();
        $story2->refresh();
        $story3->refresh();
        $this->assertEquals(StoryStatus::Todo->value, $story1->status);
        $this->assertEquals(StoryStatus::Todo->value, $story2->status);
        $this->assertEquals(StoryStatus::Todo->value, $story3->status); // Should remain unchanged
    }

    /** @test */
    public function it_skips_stories_without_valid_id()
    {
        // Arrange
        $story = new Story(); // Story without ID

        // Act
        $result = $this->service->updateWaitingStories(collect([$story]));

        // Assert
        $this->assertEquals(0, $result['success']);
        $this->assertEquals(1, $result['skipped']);
        $this->assertEquals(0, $result['errors']);
    }

    /** @test */
    public function it_handles_empty_collection()
    {
        // Act
        $result = $this->service->updateWaitingStories(collect([]));

        // Assert
        $this->assertEquals(0, $result['success']);
        $this->assertEquals(0, $result['skipped']);
        $this->assertEquals(0, $result['errors']);
    }

    /** @test */
    public function it_handles_no_waiting_stories_when_collection_is_null()
    {
        // Arrange - create stories that are NOT waiting
        $user = User::factory()->create();
        Story::factory()->create([
            'status' => StoryStatus::Todo->value,
            'creator_id' => $user->id,
        ]);

        // Act
        $result = $this->service->updateWaitingStories(null);

        // Assert
        $this->assertEquals(0, $result['success']);
        $this->assertEquals(0, $result['skipped']);
        $this->assertEquals(0, $result['errors']);
    }
}

