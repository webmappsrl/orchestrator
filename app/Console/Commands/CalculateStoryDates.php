<?php

namespace App\Console\Commands;

use App\Models\Story;
use App\Models\User;
use App\Services\StoryDateService;
use Illuminate\Console\Command;

class CalculateStoryDates extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'story:calculate-dates 
                            {--creator= : Filter by creator ID}
                            {--user= : Filter by assigned user ID}
                            {--story= : Calculate dates for a specific story ID}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Calculate and update released_at and done_at dates for stories from story logs';

    /**
     * Execute the console command.
     */
    public function handle(StoryDateService $dateService)
    {
        $creatorId = $this->option('creator');
        $userId = $this->option('user');
        $storyId = $this->option('story');

        $query = Story::query();

        // Filter by specific story
        if ($storyId) {
            $query->where('id', $storyId);
        }

        // Filter by creator
        if ($creatorId) {
            $query->where('creator_id', $creatorId);
        }

        // Filter by assigned user
        if ($userId) {
            $query->where('user_id', $userId);
        }

        $stories = $query->get();

        if ($stories->isEmpty()) {
            $this->warn('No stories found matching the criteria.');
            return Command::FAILURE;
        }

        $this->info("Found {$stories->count()} story/stories to process.");

        $bar = $this->output->createProgressBar($stories->count());
        $bar->start();

        $updated = 0;
        foreach ($stories as $story) {
            $oldReleasedAt = $story->released_at;
            $oldDoneAt = $story->done_at;

            $dateService->updateDates($story);

            // Check if dates changed
            if ($story->released_at != $oldReleasedAt || $story->done_at != $oldDoneAt) {
                $story->saveQuietly();
                $updated++;
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info("Processed {$stories->count()} story/stories. Updated {$updated} story/stories.");

        return Command::SUCCESS;
    }
}
