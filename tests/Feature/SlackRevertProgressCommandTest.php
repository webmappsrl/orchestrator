<?php

namespace Tests\Feature;

use App\Enums\StoryStatus;
use App\Enums\StoryType;
use App\Enums\UserRole;
use App\Models\Story;
use App\Models\StoryLog;
use App\Models\User;
use App\Services\SlackService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class SlackRevertProgressCommandTest extends TestCase
{
    use DatabaseTransactions;

    private function makeDeveloper(array $attrs = []): User
    {
        return User::factory()->create(array_merge([
            'roles' => collect([UserRole::Developer]),
            'slack_user_id' => 'U0123456789',
        ], $attrs));
    }

    private function makeProgressStory(User $developer): Story
    {
        return Story::factory()->create([
            'user_id' => $developer->id,
            'status'  => StoryStatus::Progress->value,
            'type'    => StoryType::Helpdesk->value,
        ]);
    }

    /** @test */
    public function it_reverts_progress_story_to_todo_when_developer_is_away(): void
    {
        $developer = $this->makeDeveloper();
        $story = $this->makeProgressStory($developer);

        $slack = $this->mock(SlackService::class);
        $slack->shouldReceive('getPresence')
            ->with($developer->slack_user_id)
            ->once()
            ->andReturn('away');

        $this->artisan('story:slack-revert-progress')->assertExitCode(0);

        $this->assertEquals(StoryStatus::Todo->value, $story->fresh()->status);
    }

    /** @test */
    public function it_creates_story_log_on_revert(): void
    {
        $developer = $this->makeDeveloper();
        $story = $this->makeProgressStory($developer);

        $slack = $this->mock(SlackService::class);
        $slack->shouldReceive('getPresence')->andReturn('away');

        $this->artisan('story:slack-revert-progress');

        $log = StoryLog::where('story_id', $story->id)->latest()->first();
        $this->assertNotNull($log);
        $this->assertEquals(['status' => StoryStatus::Todo->value], $log->changes);
    }

    /** @test */
    public function it_does_not_revert_when_developer_is_active(): void
    {
        $developer = $this->makeDeveloper();
        $story = $this->makeProgressStory($developer);

        $slack = $this->mock(SlackService::class);
        $slack->shouldReceive('getPresence')
            ->with($developer->slack_user_id)
            ->once()
            ->andReturn('active');

        $this->artisan('story:slack-revert-progress');

        $this->assertEquals(StoryStatus::Progress->value, $story->fresh()->status);
    }

    /** @test */
    public function it_skips_developer_on_slack_api_exception(): void
    {
        $developer = $this->makeDeveloper();
        $story = $this->makeProgressStory($developer);

        $slack = $this->mock(SlackService::class);
        $slack->shouldReceive('getPresence')
            ->andThrow(new \Exception('Slack API error'));

        $this->artisan('story:slack-revert-progress')->assertExitCode(0);

        $this->assertEquals(StoryStatus::Progress->value, $story->fresh()->status);
    }

    /** @test */
    public function it_skips_developer_without_slack_user_id(): void
    {
        $developer = $this->makeDeveloper(['slack_user_id' => null]);
        $story = $this->makeProgressStory($developer);

        $slack = $this->mock(SlackService::class);
        $slack->shouldReceive('getPresence')->never();

        $this->artisan('story:slack-revert-progress');

        $this->assertEquals(StoryStatus::Progress->value, $story->fresh()->status);
    }
}
