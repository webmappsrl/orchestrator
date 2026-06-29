<?php

namespace App\Jobs;

use App\Models\StoryGithubCommit;
use App\Services\Metrics\GitHubMetricsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncStoryGithubCommitsJob implements ShouldQueue, ShouldBeUniqueUntilProcessing
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(public readonly int $storyId)
    {
        $this->onQueue('github-sync');
    }

    public function uniqueId(): string
    {
        return "sync-story-github-{$this->storyId}";
    }

    public function handle(GitHubMetricsService $github): void
    {
        $commits = $github->getCommitsForStory($this->storyId);
        foreach ($commits as $commit) {
            StoryGithubCommit::updateOrCreate(
                ['story_id' => $this->storyId, 'sha' => $commit['sha']],
                [
                    'repo'         => $commit['repo'],
                    'author_email' => $commit['author_email'],
                    'committed_at' => $commit['committed_at'],
                ]
            );
        }

        // Cerca PR via Search API (primary)
        $prs = $github->getPrsForStory($this->storyId);

        // Fallback: se Search non ha trovato PR, estrae URL GitHub dal campo description della story
        if (empty($prs)) {
            $story = \App\Models\Story::find($this->storyId, ['description']);
            if ($story?->description) {
                $prs = $github->getPrsFromText($story->description);
            }
        }

        foreach ($prs as $pr) {
            \App\Models\StoryGithubPr::updateOrCreate(
                ['story_id' => $this->storyId, 'repo' => $pr['repo'], 'pr_number' => $pr['pr_number']],
                [
                    'change_requests_count' => $pr['change_requests_count'],
                    'merged_at'             => $pr['merged_at'],
                ]
            );
        }

        Log::info("SyncStoryGithubCommitsJob: {$this->storyId} → " . count($commits) . ' commits, ' . count($prs) . ' PR sincronizzate');
    }
}
