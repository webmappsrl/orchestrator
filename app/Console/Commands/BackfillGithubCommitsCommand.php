<?php

namespace App\Console\Commands;

use App\Enums\StoryStatus;
use App\Jobs\SyncStoryGithubCommitsJob;
use App\Models\Story;
use App\Models\StoryGithubCommit;
use Illuminate\Console\Command;

class BackfillGithubCommitsCommand extends Command
{
    protected $signature = 'github:backfill-commits
                            {--dry-run : Mostra quante storie verrebbero processate senza dispatch}';

    protected $description = 'Dispatcha SyncStoryGithubCommitsJob per tutte le storie done/released
                              non ancora sincronizzate. Idempotente: skippa le storie già presenti
                              in story_github_commits. Ordina per updated_at DESC (più recenti prima).';

    public function handle(): int
    {
        $syncedIds = StoryGithubCommit::distinct()->pluck('story_id');

        $stories = Story::whereIn('status', [StoryStatus::Done->value, StoryStatus::Released->value])
            ->whereNotIn('id', $syncedIds)
            ->orderByDesc('updated_at')
            ->get(['id', 'name', 'updated_at']);

        $this->info("Storie da processare: {$stories->count()}");

        if ($this->option('dry-run')) {
            $this->table(['ID', 'Name', 'Updated At'], $stories->map(fn ($s) => [$s->id, substr($s->name, 0, 60), $s->updated_at]));
            return self::SUCCESS;
        }

        $bar = $this->output->createProgressBar($stories->count());
        $bar->start();

        foreach ($stories as $story) {
            SyncStoryGithubCommitsJob::dispatch($story->id);
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info("Dispatched {$stories->count()} job(s) sulla coda github-sync (maxProcesses=1, rate limit gestito dal supervisor).");

        return self::SUCCESS;
    }
}
