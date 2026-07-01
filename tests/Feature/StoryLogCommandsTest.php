<?php

namespace Tests\Feature;

use App\Enums\StoryStatus;
use App\Enums\StoryType;
use App\Enums\UserRole;
use App\Models\Story;
use App\Models\StoryLog;
use App\Models\User;
use App\Services\SlackService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class StoryLogCommandsTest extends TestCase
{
    use DatabaseTransactions;

    /** @test */
    public function auto_update_status_creates_story_log(): void
    {
        $story = Story::factory()->create([
            'status'     => StoryStatus::Released->value,
            'type'       => StoryType::Helpdesk->value,
            'user_id'    => null,
            'updated_at' => Carbon::now()->subDays(5),
        ]);

        $logsBefore = StoryLog::where('story_id', $story->id)->count();

        $this->artisan('story:auto-update-status');

        $story->refresh();
        $this->assertEquals(StoryStatus::Done->value, $story->status);
        $this->assertGreaterThan($logsBefore, StoryLog::where('story_id', $story->id)->count());
    }

    /** @test */
    public function move_scrum_stories_to_done_creates_story_log(): void
    {
        $story = Story::factory()->create([
            'status'  => StoryStatus::Progress->value,
            'type'    => StoryType::Scrum->value,
            'user_id' => null,
        ]);

        $logsBefore = StoryLog::where('story_id', $story->id)->count();

        $this->artisan('story:scrum-to-done');

        $story->refresh();
        $this->assertEquals(StoryStatus::Done->value, $story->status);
        $this->assertGreaterThan($logsBefore, StoryLog::where('story_id', $story->id)->count());
    }

    /** @test */
    public function slack_revert_progress_creates_story_log(): void
    {
        $developer = User::factory()->create([
            'roles'         => collect([UserRole::Developer]),
            'slack_user_id' => 'U0123456789',
        ]);
        $story = Story::factory()->create([
            'user_id' => $developer->id,
            'status'  => StoryStatus::Progress->value,
            'type'    => StoryType::Helpdesk->value,
        ]);

        $logsBefore = StoryLog::where('story_id', $story->id)->count();

        $slackService = \Mockery::mock(SlackService::class);
        $slackService->shouldReceive('getPresence')->andReturn('away');
        $this->app->instance(SlackService::class, $slackService);

        $this->artisan('story:slack-revert-progress');

        $story->refresh();
        $this->assertEquals(StoryStatus::Todo->value, $story->status);
        $this->assertGreaterThan($logsBefore, StoryLog::where('story_id', $story->id)->count());
    }
}
