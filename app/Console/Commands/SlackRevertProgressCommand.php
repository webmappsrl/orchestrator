<?php

namespace App\Console\Commands;

use App\Enums\StoryStatus;
use App\Models\Story;
use App\Models\StoryLog;
use App\Models\User;
use App\Services\SlackService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SlackRevertProgressCommand extends Command
{
    protected $signature = 'story:slack-revert-progress';
    protected $description = 'Revert progress tickets to todo for developers who are offline on Slack';

    public function handle(SlackService $slackService): void
    {
        $systemUser = User::firstOrCreate(
            ['email' => 'orchestrator_artisan@webmapp.it'],
            ['name' => 'Orchestrator Artisan', 'password' => bcrypt('unused')]
        );

        $developers = User::whereHas('stories', function ($q) {
            $q->where('status', StoryStatus::Progress->value);
        })->whereNotNull('slack_user_id')->get();

        foreach ($developers as $developer) {
            try {
                $presence = $slackService->getPresence($developer->slack_user_id);
            } catch (\Exception $e) {
                Log::warning("SlackRevertProgress: skip developer {$developer->id} — API error: {$e->getMessage()}");
                $this->warn("Skipped developer {$developer->id}: {$e->getMessage()}");
                continue;
            }

            if ($presence !== 'away') {
                continue;
            }

            $stories = Story::where('user_id', $developer->id)
                ->where('status', StoryStatus::Progress->value)
                ->get();

            foreach ($stories as $story) {
                $story->status = StoryStatus::Todo->value;
                $story->saveQuietly();

                StoryLog::create([
                    'story_id'  => $story->id,
                    'user_id'   => $systemUser->id,
                    'viewed_at' => now()->format('Y-m-d H:i'),
                    'changes'   => ['status' => StoryStatus::Todo->value],
                ]);

                $this->info("Reverted story {$story->id} (developer {$developer->id})");
                Log::info("SlackRevertProgress: reverted story {$story->id} for developer {$developer->id}");
            }
        }

        $this->info('story:slack-revert-progress complete.');
    }
}
