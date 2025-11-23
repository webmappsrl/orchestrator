<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\ScrumStoryService;

class MoveScrumStoriesInDoneCommand extends Command
{
    protected $signature = 'story:scrum-to-done';
    protected $description = 'Update all scrumm stories status to done';

    public function __construct(
        private ScrumStoryService $scrumStoryService
    ) {
        parent::__construct();
    }

    public function handle()
    {
        $stories = $this->scrumStoryService->getScrumStoriesForToday();
        
        if ($stories->isEmpty()) {
            $this->info('No scrum stories found for today.');
            return;
        }
        
        $successCount = 0;
        $errorCount = 0;
        
        foreach ($stories as $story) {
            try {
                $this->scrumStoryService->moveToDone($story);
                $this->info("scrum Story ID {$story->id} doned.");
                $successCount++;
            } catch (\Exception $e) {
                $this->error("Error processing Story ID {$story->id}: " . $e->getMessage());
                $errorCount++;
            }
        }
        
        $this->info("All applicable stories have been updated. Success: {$successCount}, Errors: {$errorCount}.");
    }
}
