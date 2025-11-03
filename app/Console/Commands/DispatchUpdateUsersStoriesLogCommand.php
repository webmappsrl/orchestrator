<?php

namespace App\Console\Commands;

use App\Jobs\UpdateUsersStoriesLogJob;
use Illuminate\Console\Command;

class DispatchUpdateUsersStoriesLogCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'users-stories-log-dispatch {--story_id= : Specific story ID to update} {--user_id= : Specific user ID to update}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Dispatch job to update users_stories_log table';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $storyId = $this->option('story_id');
        $userId = $this->option('user_id');

        if ($storyId) {
            $this->info("Dispatching job for story ID: {$storyId}");
            UpdateUsersStoriesLogJob::dispatch($storyId, null);
            $this->info('Job dispatched successfully!');
        } elseif ($userId) {
            $this->info("Dispatching job for user ID: {$userId}");
            UpdateUsersStoriesLogJob::dispatch(null, $userId);
            $this->info('Job dispatched successfully!');
        } else {
            $this->info('Dispatching job for all stories');
            UpdateUsersStoriesLogJob::dispatch();
            $this->info('Job dispatched successfully!');
        }
    }
}
