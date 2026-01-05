<?php

namespace App\Console\Commands;

use App\Services\AutoUpdateWaitingStoriesService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class AutoUpdateWaitingStories extends Command
{
    protected $signature = 'orchestrator:autoupdate-waiting';
    protected $description = 'Automatically restores tickets from Waiting status to their previous status after a configured number of days';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('orchestrator:autoupdate-waiting start');
        Log::info('orchestrator:autoupdate-waiting start');

        $service = new AutoUpdateWaitingStoriesService();
        $result = $service->updateWaitingStories();

        if ($result['success'] === 0 && $result['skipped'] === 0 && $result['errors'] === 0) {
            $this->info('No stories found in Waiting status.');
            Log::info('No stories found in Waiting status.');
            return;
        }

        $this->info("Command completed. Success: {$result['success']}, Skipped: {$result['skipped']}, Errors: {$result['errors']}.");
        Log::info("orchestrator:autoupdate-waiting completed. Success: {$result['success']}, Skipped: {$result['skipped']}, Errors: {$result['errors']}.");
    }
}

