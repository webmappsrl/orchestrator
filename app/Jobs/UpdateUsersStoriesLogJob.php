<?php

namespace App\Jobs;

use App\Models\Story;
use App\Models\User;
use App\Actions\UpdateUsersStoriesLogService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class UpdateUsersStoriesLogJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public ?int $storyId;
    public ?int $userId;

    /**
     * Create a new job instance.
     */
    public function __construct(?int $storyId = null, ?int $userId = null)
    {
        $this->storyId = $storyId;
        $this->userId = $userId;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $service = new UpdateUsersStoriesLogService();

        if ($this->storyId) {
            // Update specific story
            $story = Story::find($this->storyId);
            if ($story) {
                $service->handle($story);
            }
        } elseif ($this->userId) {
            // Update all stories for a specific user
            $user = User::find($this->userId);
            if ($user) {
                $user->stories()->each(function ($story) use ($service) {
                    $service->handle($story);
                });
            }
        } else {
            // Update all stories
            $service->handle();
        }
    }
}
