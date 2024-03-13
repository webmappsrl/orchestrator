<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Story;
use App\Enums\StoryStatus;

class UpdateStoryStatusCommand extends Command
{
    protected $signature = 'story:update-status';
    protected $description = 'Update story status from new to assigned if a developer is assigned.';

    public function handle()
    {
        Story::where('status', StoryStatus::New->value)
            ->whereNotNull('user_id')
            ->each(function ($story) {
                $story->status = StoryStatus::Assigned->value;
                $story->save();
                $this->info("ASSIGNED Story ID {$story->id} updated.");
            });
        Story::where('status', StoryStatus::Done->value)
            ->each(function ($story) {
                $story->status = StoryStatus::Released->value;
                $story->save();
                $this->info("DONE Story ID {$story->id} updated.");
            });

        $this->info('All applicable stories have been updated.');
    }
}
