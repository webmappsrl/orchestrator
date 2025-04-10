<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Story;
use App\Enums\StoryStatus;
use App\Enums\StoryType;

class MoveScrumStoriesInDoneCommand extends Command
{
    protected $signature = 'story:scrum-to-done';
    protected $description = 'Update all scrumm stories status to done';

    public function handle()
    {
        $today = now()->startOfDay(); // Inizio della giornata
        Story::where('type', StoryType::Scrum->value)
            ->where(function ($query) use ($today) {
                $query->whereDate('created_at', '=', $today)
                    ->orWhereDate('updated_at', '=', $today);
            })
            ->each(function ($story) {
                $story->status = StoryStatus::Done->value;
                $story->saveQuietly();
                $this->info("scrum Story ID {$story->id} doned.");
            });
        $this->info('All applicable stories have been updated.');
    }
}
