<?php

namespace App\Services;

use App\Models\Story;
use App\Models\StoryLog;
use App\Enums\StoryStatus;
use Illuminate\Support\Collection;

class StoryDateService
{
    /**
     * Calculate and return the released_at date from story logs
     *
     * @param Story $story
     * @return \Carbon\Carbon|null
     */
    public function calculateReleasedAt(Story $story): ?\Carbon\Carbon
    {
        $logs = $story->storyLogs()
            ->orderBy('viewed_at', 'asc')
            ->get();

        foreach ($logs as $log) {
            $changes = $log->changes ?? [];
            if (is_array($changes) && isset($changes['status']) && $changes['status'] === StoryStatus::Released->value) {
                return \Carbon\Carbon::parse($log->viewed_at);
            }
        }

        return null;
    }

    /**
     * Calculate and return the done_at date from story logs
     * If status is Done but no done_at date is found in logs, use released_at as done_at
     *
     * @param Story $story
     * @return \Carbon\Carbon|null
     */
    public function calculateDoneAt(Story $story): ?\Carbon\Carbon
    {
        $logs = $story->storyLogs()
            ->orderBy('viewed_at', 'asc')
            ->get();

        foreach ($logs as $log) {
            $changes = $log->changes ?? [];
            if (is_array($changes) && isset($changes['status']) && $changes['status'] === StoryStatus::Done->value) {
                return \Carbon\Carbon::parse($log->viewed_at);
            }
        }

        // If status is Done but no done_at date found in logs, use released_at if available
        if ($story->status === StoryStatus::Done->value) {
            // First try to get released_at from the story itself
            if ($story->released_at) {
                return \Carbon\Carbon::parse($story->released_at);
            }
            
            // Otherwise calculate released_at from logs
            $releasedAt = $this->calculateReleasedAt($story);
            if ($releasedAt) {
                return $releasedAt;
            }
        }

        return null;
    }

    /**
     * Update released_at and done_at dates for a story
     * If status is Done but no done_at date found, use released_at as done_at
     *
     * @param Story $story
     * @return Story
     */
    public function updateDates(Story $story): Story
    {
        $releasedAt = $this->calculateReleasedAt($story);
        $doneAt = $this->calculateDoneAt($story);

        $story->released_at = $releasedAt;
        $story->done_at = $doneAt;

        // If status is Done but done_at is still null and released_at exists, use released_at
        if ($story->status === StoryStatus::Done->value && !$story->done_at && $story->released_at) {
            $story->done_at = $story->released_at;
        }

        return $story;
    }

    /**
     * Update dates for multiple stories
     *
     * @param Collection|array $stories
     * @return void
     */
    public function updateDatesForStories($stories): void
    {
        foreach ($stories as $story) {
            $this->updateDates($story);
            $story->saveQuietly();
        }
    }
}

