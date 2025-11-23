<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\AutoUpdateStoryStatusService;
use Illuminate\Support\Facades\Log;

class AutoUpdateStoryStatus extends Command
{
    protected $signature = 'story:auto-update-status';
    protected $description = 'Automatically updates story statuses from Released to Done after 3 working days';

    public function __construct(
        private AutoUpdateStoryStatusService $autoUpdateService
    ) {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $stories = $this->autoUpdateService->getStoriesToUpdate();
        
        $this->info('story:auto-update-status start');
        Log::info('story:auto-update-status start');
        
        if ($stories->isEmpty()) {
            $this->info('No stories found to update.');
            Log::info('No stories found to update.');
            return;
        }
        
        $successCount = 0;
        $errorCount = 0;
        
        foreach ($stories as $story) {
            try {
                $this->autoUpdateService->moveToDone($story);
                $this->info('Updated story ID: ' . $story->id);
                Log::info('Updated story ID: ' . $story->id);
                $successCount++;
            } catch (\Exception $e) {
                $this->error("Error processing Story ID {$story->id}: " . $e->getMessage());
                Log::error("Error processing Story ID {$story->id}: " . $e->getMessage());
                $errorCount++;
            }
        }

        $this->info("All applicable stories have been updated. Success: {$successCount}, Errors: {$errorCount}.");
        Log::info("All applicable stories have been updated. Success: {$successCount}, Errors: {$errorCount}.");
    }
}
