<?php

namespace Tests\Feature\Jobs;

use App\Jobs\SyncStoryGithubCommitsJob;
use App\Models\Story;
use App\Models\StoryGithubCommit;
use App\Services\Metrics\GitHubMetricsService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\TestCase;

class SyncStoryGithubCommitsJobTest extends TestCase
{
    use DatabaseTransactions;

    public function test_job_persists_commits_from_github(): void
    {
        $story = Story::factory()->create(['status' => 'done']);

        $mockService = Mockery::mock(GitHubMetricsService::class);
        $mockService->shouldReceive('getCommitsForStory')
            ->with($story->id)
            ->andReturn([
                [
                    'sha'          => 'abc123',
                    'repo'         => 'webmappsrl/orchestrator',
                    'author_email' => 'dev@webmapp.it',
                    'committed_at' => '2026-01-15T10:00:00Z',
                ],
            ]);
        $mockService->shouldReceive('getPrsForStory')
            ->with($story->id)
            ->andReturn([]);
        $mockService->shouldReceive('getPrsFromText')->andReturn([]);
        $this->app->instance(GitHubMetricsService::class, $mockService);

        (new SyncStoryGithubCommitsJob($story->id))->handle(
            app(GitHubMetricsService::class)
        );

        $this->assertDatabaseHas('story_github_commits', [
            'story_id' => $story->id,
            'sha'      => 'abc123',
            'repo'     => 'webmappsrl/orchestrator',
        ]);
    }

    public function test_job_is_idempotent_on_duplicate_sha(): void
    {
        $story = Story::factory()->create(['status' => 'done']);
        StoryGithubCommit::create([
            'story_id'     => $story->id,
            'sha'          => 'abc123',
            'repo'         => 'webmappsrl/orchestrator',
            'author_email' => 'dev@webmapp.it',
            'committed_at' => now(),
        ]);

        $mockService = Mockery::mock(GitHubMetricsService::class);
        $mockService->shouldReceive('getCommitsForStory')
            ->andReturn([
                ['sha' => 'abc123', 'repo' => 'webmappsrl/orchestrator', 'author_email' => 'dev@webmapp.it', 'committed_at' => '2026-01-15T10:00:00Z'],
            ]);
        $mockService->shouldReceive('getPrsForStory')
            ->andReturn([]);
        $mockService->shouldReceive('getPrsFromText')->andReturn([]);
        $this->app->instance(GitHubMetricsService::class, $mockService);

        (new SyncStoryGithubCommitsJob($story->id))->handle(
            app(GitHubMetricsService::class)
        );

        $this->assertDatabaseCount('story_github_commits', 1);
    }

    public function test_observer_dispatches_job_on_done(): void
    {
        Queue::fake();
        $story = Story::factory()->create(['status' => 'progress']);

        $story->status = 'done';
        $story->save();

        Queue::assertPushed(SyncStoryGithubCommitsJob::class, function ($job) use ($story) {
            return $job->storyId === $story->id;
        });
    }

    public function test_observer_does_not_dispatch_on_non_closing_status(): void
    {
        Queue::fake();
        $story = Story::factory()->create(['status' => 'progress']);

        $story->status = 'testing';
        $story->save();

        Queue::assertNotPushed(SyncStoryGithubCommitsJob::class);
    }
}
