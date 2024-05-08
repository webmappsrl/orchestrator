<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Enums\StoryStatus;
use App\Models\Story;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class AutoUpdateStoryStatus extends Command
{
    protected $signature = 'story:auto-update-status';
    protected $description = 'Automatically updates story statuses from Released to Done after 7 days';

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $sevenDaysAgo = Carbon::now()->subDays(7);
        $stories = Story::where('status', StoryStatus::Released->value)
            ->whereDate('updated_at', '<=', $sevenDaysAgo)
            ->get();
        $this->info('story:auto-update-status start');
        Log::info('story:auto-update-status start');
        foreach ($stories as $story) {
            $story->status = StoryStatus::Done->value;
            $story->saveQuietly();
            $this->info('Updated story ID: ' . $story->id);
            Log::info('Updated story ID: ' . $story->id);
        }

        $this->info('All applicable stories have been updated.');
        Log::info('All applicable stories have been updated.');
    }
}
