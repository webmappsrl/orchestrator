<?php

namespace App\Services;

use App\Models\Story;
use App\Models\StoryLog;
use App\Enums\StoryStatus;
use Carbon\Carbon;

class AutoUpdateStoryStatusService
{
    /**
     * Move a released story to done status after 3 working days
     * Creates a story log entry, updates status and done_at
     *
     * @param Story $story
     * @param Carbon|null $timestamp Optional timestamp, defaults to now()
     * @return bool True if successful, false otherwise
     */
    public function moveToDone(Story $story, ?Carbon $timestamp = null): bool
    {
        $timestamp = $timestamp ?? now();
        
        // Get user_id from story
        $userId = $story->user_id;
        
        // If user_id is null, try to get it from existing story logs
        if (!$userId) {
            $userId = $this->getUserIdFromStoryLogs($story);
        }
        
        // If still no user_id, set as null (don't try system user)
        if (!$userId) {
            $userId = null;
        }
        
        // Only create StoryLog if we have a user_id
        if ($userId) {
            // Create StoryLog entry before saving
            // Use same format as StoryObserver for consistency
            StoryLog::create([
                'story_id' => $story->id,
                'user_id' => $userId,
                'viewed_at' => $timestamp->format('Y-m-d H:i:s'),
                'changes' => [
                    'status' => StoryStatus::Done->value,
                ],
            ]);
        }
        
        // Update status and done_at
        $story->status = StoryStatus::Done->value;
        $story->done_at = $timestamp;
        $story->saveQuietly();
        
        return true;
    }

    /**
     * Get user_id from existing story logs (excluding watch-only logs)
     *
     * @param Story $story
     * @return int|null
     */
    private function getUserIdFromStoryLogs(Story $story): ?int
    {
        $logs = StoryLog::where('story_id', $story->id)
            ->whereNotNull('user_id')
            ->orderBy('viewed_at', 'desc')
            ->get();

        foreach ($logs as $log) {
            $changes = $log->changes ?? [];
            
            // Skip logs that are only "watch" (have only watch key or watch is the only meaningful change)
            if (isset($changes['watch']) && count($changes) === 1) {
                continue;
            }
            
            // Return the first user_id from a non-watch log
            if ($log->user_id) {
                return $log->user_id;
            }
        }

        return null;
    }

    /**
     * Calculate the date 3 working days ago (excluding weekends)
     *
     * @return Carbon
     */
    public function getThreeWorkingDaysAgo(): Carbon
    {
        $daysAgo = Carbon::now();
        for ($i = 0; $i < 3; $i++) {
            $daysAgo->subDay();
            // If the current day is Saturday or Sunday, we need to subtract more days
            if ($daysAgo->isWeekend()) {
                $i--;
            }
        }
        return $daysAgo;
    }

    /**
     * Get released stories that should be moved to done (updated at least 3 working days ago)
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getStoriesToUpdate(): \Illuminate\Database\Eloquent\Collection
    {
        $daysAgo = $this->getThreeWorkingDaysAgo();
        
        return Story::where('status', StoryStatus::Released->value)
            ->whereDate('updated_at', '<=', $daysAgo)
            ->get();
    }
}

