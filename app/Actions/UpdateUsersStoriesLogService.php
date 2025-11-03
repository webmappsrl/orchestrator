<?php

namespace App\Actions;

use App\Models\Story;
use App\Models\StoryLog;
use App\Models\UsersStoriesLog;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class UpdateUsersStoriesLogService
{
    use AsAction;

    protected $comparableDateFormat = 'Y-m-d';
    protected $comparableDateTimeFormat = 'Y-m-d H:i:s';

    /**
     * Update users_stories_log for a specific story
     *
     * @param Story|null $story
     * @return bool
     */
    public function handle(?Story $story = null): bool
    {
        if ($story) {
            return $this->updateStory($story);
        }
        
        // Update all stories
        $success = true;
        foreach (Story::all() as $s) {
            if (!$this->updateStory($s)) {
                $success = false;
            }
        }
        
        return $success;
    }

    /**
     * Update users_stories_log for a specific story
     *
     * @param Story $story
     * @return bool
     */
    protected function updateStory(Story $story): bool
    {
        try {
            // Get all story logs for this story
            $allStoryLogs = $story->storyLogs()->orderBy('created_at', 'asc')->get();
            
            if ($allStoryLogs->isEmpty()) {
                return true;
            }

            // Group story logs by user
            $storyLogsByUser = $allStoryLogs->groupBy('user_id');

            foreach ($storyLogsByUser as $userId => $userStoryLogs) {
                $this->updateUserStoryLog($story, $userId, $userStoryLogs);
            }

            return true;
        } catch (\Exception $e) {
            \Log::error('Error updating users_stories_log for story ' . $story->id . ': ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Update users_stories_log for a specific user and story
     *
     * @param Story $story
     * @param int $userId
     * @param Collection $userStoryLogs
     * @return void
     */
    protected function updateUserStoryLog(Story $story, int $userId, Collection $userStoryLogs): void
    {
        // Delete existing entries for this user and story
        UsersStoriesLog::where('story_id', $story->id)
            ->where('user_id', $userId)
            ->delete();

        // Calculate elapsed time for each day
        $dailyMinutes = $this->calculateDailyMinutes($userStoryLogs, $story);

        // Insert new entries
        foreach ($dailyMinutes as $date => $minutes) {
            UsersStoriesLog::updateOrCreate(
                [
                    'date' => $date,
                    'user_id' => $userId,
                    'story_id' => $story->id,
                ],
                [
                    'elapsed_minutes' => $minutes,
                ]
            );
        }
    }

    /**
     * Calculate minutes worked per day for a set of story logs
     *
     * @param Collection $userStoryLogs
     * @param Story $story
     * @return array Associative array with date => minutes
     */
    protected function calculateDailyMinutes(Collection $userStoryLogs, Story $story): array
    {
        $dailyMinutes = [];
        
        // Filter story logs that represent actual work (status changes, not just views)
        $workLogs = $userStoryLogs->filter(function ($log) {
            if (!isset($log->changes) || !is_array($log->changes)) {
                return false;
            }
            // Consider logs with significant changes
            $significantChanges = ['status', 'description', 'estimated_hours', 'effective_hours'];
            return !empty(array_intersect(array_keys($log->changes), $significantChanges));
        });

        if ($workLogs->isEmpty()) {
            return $dailyMinutes;
        }

        // Sort by creation time
        $sortedLogs = $workLogs->sortBy('created_at')->values();

        // Iterate through log pairs to calculate time spent
        for ($i = 0; $i < $sortedLogs->count(); $i++) {
            $currentLog = $sortedLogs[$i];
            $nextLog = $i < $sortedLogs->count() - 1 ? $sortedLogs[$i + 1] : null;

            $startTime = Carbon::parse($currentLog->created_at);
            $endTime = $nextLog ? Carbon::parse($nextLog->created_at) : Carbon::now();

            // If this is a status change to "progress", calculate working time
            if (isset($currentLog->changes['status']) && $currentLog->changes['status'] === 'progress') {
                $this->addWorkingMinutes($dailyMinutes, $startTime, $endTime);
            } elseif (!isset($currentLog->changes['status'])) {
                // For other significant changes, add a small amount of time
                // This represents active work on the ticket
                $this->addWorkingMinutes($dailyMinutes, $startTime, $endTime, 30);
            }
        }

        return $dailyMinutes;
    }

    /**
     * Add working minutes to daily tracking
     * Time is counted only during working hours (9-18, Mon-Fri)
     *
     * @param array $dailyMinutes
     * @param Carbon $startTime
     * @param Carbon $endTime
     * @param int $maxMinutes
     * @return void
     */
    protected function addWorkingMinutes(array &$dailyMinutes, Carbon $startTime, Carbon $endTime, int $maxMinutes = PHP_INT_MAX): void
    {
        // If times are on different days, handle each day separately
        if ($startTime->format('Y-m-d') !== $endTime->format('Y-m-d')) {
            $midnight = $startTime->copy()->endOfDay();
            $this->addWorkingMinutesForPeriod($dailyMinutes, $startTime, $midnight, $maxMinutes);
            
            // Add days in between
            $currentDay = $startTime->copy()->addDay()->startOfDay();
            while ($currentDay->lt($endTime)) {
                $dayEnd = $currentDay->copy()->endOfDay();
                $this->addWorkingMinutesForPeriod($dailyMinutes, $currentDay, $dayEnd, $maxMinutes);
                $currentDay->addDay();
            }
            
            // Add remaining time on end day
            $dayStart = $endTime->copy()->startOfDay();
            $this->addWorkingMinutesForPeriod($dailyMinutes, $dayStart, $endTime, $maxMinutes);
        } else {
            $this->addWorkingMinutesForPeriod($dailyMinutes, $startTime, $endTime, $maxMinutes);
        }
    }

    /**
     * Add working minutes for a specific period
     *
     * @param array $dailyMinutes
     * @param Carbon $startTime
     * @param Carbon $endTime
     * @param int $maxMinutes
     * @return void
     */
    protected function addWorkingMinutesForPeriod(array &$dailyMinutes, Carbon $startTime, Carbon $endTime, int $maxMinutes): void
    {
        $dateKey = $startTime->format('Y-m-d');
        
        if (!isset($dailyMinutes[$dateKey])) {
            $dailyMinutes[$dateKey] = 0;
        }

        // Only count working hours (9-18) on weekdays
        if ($this->isSundayOrSaturday($startTime)) {
            return;
        }

        $workingStart = $startTime->copy()->setTime(9, 0);
        $workingEnd = $endTime->copy()->setTime(18, 0);
        
        $actualStart = $startTime->lt($workingStart) ? $workingStart : $startTime;
        $actualEnd = $endTime->gt($workingEnd) ? $workingEnd : $endTime;

        if ($actualStart->gte($actualEnd)) {
            return;
        }

        $minutes = $actualStart->diffInMinutes($actualEnd);
        $minutes = min($minutes, $maxMinutes);
        
        $dailyMinutes[$dateKey] += $minutes;
    }

    /**
     * Check if date is weekend
     *
     * @param CarbonInterface $date
     * @return bool
     */
    protected function isSundayOrSaturday(CarbonInterface $date): bool
    {
        return (int) $date->format('N') > 5; // 6 = Saturday, 7 = Sunday
    }
}

