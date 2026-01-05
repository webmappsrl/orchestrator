<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Story;
use App\Models\StoryLog;
use App\Models\User;
use App\Enums\StoryStatus;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;

class StoryCreationLogTest extends TestCase
{
    use DatabaseTransactions;

    /** @test */
    public function it_creates_story_log_when_story_has_user_id()
    {
        // Arrange
        $user = User::factory()->create();
        $story = Story::factory()->make([
            'user_id' => $user->id,
            'status' => StoryStatus::New->value,
        ]);

        // Act
        $story->save();

        // Assert
        // Note: When a story with user_id is created with status New, 
        // the boot method automatically changes it to Assigned
        $story->refresh();
        $log = StoryLog::where('story_id', $story->id)->first();
        $this->assertNotNull($log, 'StoryLog should be created');
        $this->assertEquals($user->id, $log->user_id, 'Should use user_id from story');
        // The status in the log will be the final status after boot (Assigned if was New with user_id)
        $this->assertArrayHasKey('status', $log->changes);
    }

    /** @test */
    public function it_creates_story_log_with_logged_user_when_story_has_no_user_id()
    {
        // Arrange
        $loggedUser = User::factory()->create();
        Auth::login($loggedUser);
        
        $story = Story::factory()->make([
            'user_id' => null,
            'status' => StoryStatus::New->value,
        ]);

        // Act
        $story->save();

        // Assert
        $log = StoryLog::where('story_id', $story->id)->first();
        $this->assertNotNull($log, 'StoryLog should be created');
        $this->assertEquals($loggedUser->id, $log->user_id, 'Should use logged in user');
        $this->assertArrayHasKey('status', $log->changes);
        $this->assertEquals(StoryStatus::New->value, $log->changes['status']);
        
        Auth::logout();
    }

    /** @test */
    public function it_does_not_create_story_log_when_no_user_available()
    {
        // Arrange - No user logged in and story has no user_id
        $story = Story::factory()->make([
            'user_id' => null,
            'status' => StoryStatus::New->value,
        ]);

        // Act
        $story->save();

        // Assert
        // Since user_id is required in story_logs table, no log should be created when no user is available
        $log = StoryLog::where('story_id', $story->id)->first();
        $this->assertNull($log, 'StoryLog should not be created when no user_id is available');
    }

    /** @test */
    public function it_prioritizes_story_user_id_over_logged_user()
    {
        // Arrange
        $storyUser = User::factory()->create();
        $loggedUser = User::factory()->create();
        Auth::login($loggedUser);
        
        $story = Story::factory()->make([
            'user_id' => $storyUser->id,
            'status' => StoryStatus::New->value,
        ]);

        // Act
        $story->save();

        // Assert
        $log = StoryLog::where('story_id', $story->id)->first();
        $this->assertNotNull($log, 'StoryLog should be created');
        $this->assertEquals($storyUser->id, $log->user_id, 'Should prioritize story user_id over logged user');
        $this->assertNotEquals($loggedUser->id, $log->user_id, 'Should not use logged user when story has user_id');
        
        Auth::logout();
    }
}

