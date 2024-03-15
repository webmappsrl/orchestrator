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
        Story::whereNull('creator_id')
            ->whereNotNull('user_id')
            ->each(function ($story) {
                $story->creator_id = $story->user_id;
                $story->save();
                $this->info("CREATOR for Story ID {$story->id} set to USER ID.");
            });
        $this->info('All applicable stories have been updated.');
    }
}
