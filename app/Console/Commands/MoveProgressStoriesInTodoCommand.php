<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Story;
use App\Enums\StoryStatus;

class MoveProgressStoriesInTodoCommand extends Command
{
    protected $signature = 'story:progress-to-todo';
    protected $description = 'Update all progress stories status from progress to todo';

    public function handle()
    {
        Story::where('status', StoryStatus::Progress->value)
            ->each(function ($story) {
                $story->status = StoryStatus::Todo->value;
                $story->save();
                $this->info("TODO Story ID {$story->id} updated.");
            });
        $this->info('All applicable stories have been updated.');
    }
}
