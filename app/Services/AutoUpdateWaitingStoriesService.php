<?php

namespace App\Services;

use Carbon\Carbon;
use App\Models\Story;
use App\Models\StoryLog;
use App\Enums\StoryStatus;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class AutoUpdateWaitingStoriesService
{
    /**
     * Update waiting stories - restore them to their previous status after threshold days
     *
     * @param Collection|null $stories Optional collection of stories to process. If null, processes all waiting stories.
     * @return array Array with 'success', 'skipped', 'errors' counts
     */
    public function updateWaitingStories(?Collection $stories = null): array
    {
        $daysThreshold = config('orchestrator.autorestore_waiting_days', 7);
        
        // If no stories provided, get all waiting stories
        if ($stories === null) {
            $stories = Story::where('status', StoryStatus::Waiting->value)->get();
        }
        
        if ($stories->isEmpty()) {
            return [
                'success' => 0,
                'skipped' => 0,
                'errors' => 0,
            ];
        }

        $successCount = 0;
        $errorCount = 0;
        $skippedCount = 0;

        foreach ($stories as $story) {
            try {
                // Validate story has an ID
                if (!$story || !$story->id) {
                    Log::warning("Skipping story without valid ID.");
                    $skippedCount++;
                    continue;
                }

                // Find when the story entered Waiting status
                $waitingEntryDate = $this->getWaitingEntryDate($story);
                
                if (!$waitingEntryDate) {
                    Log::warning("Story ID {$story->id}: Could not determine when it entered Waiting status. Skipping.");
                    $skippedCount++;
                    continue;
                }

                // Calculate days since entering Waiting
                $daysInWaiting = $waitingEntryDate->diffInDays(now());
                
                if ($daysInWaiting < $daysThreshold) {
                    // Not enough days have passed, skip
                    continue;
                }

                // Get previous status
                $previousStatus = $this->getPreviousStatusFromLogs($story);
                
                // If no previous status found, use New as fallback
                if (!$previousStatus) {
                    $previousStatus = StoryStatus::New->value;
                    Log::info("Story ID {$story->id}: No previous status found in logs, using New as fallback.");
                }

                // Determine target status based on previous status
                $targetStatus = $previousStatus;
                
                // If previous status was progress, released, or done, change it to todo
                if (in_array($previousStatus, [
                    StoryStatus::Progress->value,
                    StoryStatus::Released->value,
                    StoryStatus::Done->value
                ])) {
                    $targetStatus = StoryStatus::Todo->value;
                    Log::info("Story ID {$story->id}: Previous status was {$previousStatus}, changing to Todo.");
                }
                // If previous status was todo, it remains in todo (no change needed)

                // Restore the story to target status
                $this->restoreStory($story, $targetStatus, $daysInWaiting, $previousStatus);
                
                Log::info("Story ID {$story->id}: Restored from Waiting to {$targetStatus} (was in Waiting for {$daysInWaiting} days).");
                $successCount++;
                
            } catch (\Exception $e) {
                Log::error("Error processing Story ID {$story->id}: " . $e->getMessage() . " - Trace: " . $e->getTraceAsString());
                $errorCount++;
            }
        }

        return [
            'success' => $successCount,
            'skipped' => $skippedCount,
            'errors' => $errorCount,
        ];
    }

    /**
     * Get the date when the story entered Waiting status
     *
     * @param Story $story
     * @return Carbon|null
     */
    private function getWaitingEntryDate(Story $story): ?Carbon
    {
        // Validate story has an ID
        if (!$story || !$story->id) {
            return null;
        }

        // Find the most recent log entry where status changed to Waiting
        // Use raw query to avoid any potential issues with JSON casting
        $waitingLog = StoryLog::where('story_id', $story->id)
            ->whereRaw("changes->>'status' = ?", [StoryStatus::Waiting->value])
            ->orderBy('viewed_at', 'desc')
            ->orderBy('id', 'desc')
            ->first();

        if ($waitingLog && $waitingLog->viewed_at) {
            // viewed_at is already a Carbon instance due to the cast, but parse handles both
            return $waitingLog->viewed_at instanceof Carbon 
                ? $waitingLog->viewed_at 
                : Carbon::parse($waitingLog->viewed_at);
        }

        // Fallback: if no log found, use updated_at (less reliable)
        if ($story->updated_at) {
            return Carbon::parse($story->updated_at);
        }

        return null;
    }

    /**
     * Get the previous status from story_logs before the ticket was set to WAITING
     * Reuses logic from ChangeStatus action
     *
     * @param Story $story
     * @return string|null
     */
    private function getPreviousStatusFromLogs(Story $story): ?string
    {
        // Validate story has an ID
        if (!$story || !$story->id) {
            return null;
        }

        // Find the most recent log where status changed to WAITING
        // Use raw query to avoid any potential issues with JSON casting
        $statusChangeLog = StoryLog::where('story_id', $story->id)
            ->whereRaw("changes->>'status' = ?", [StoryStatus::Waiting->value])
            ->orderBy('viewed_at', 'desc')
            ->orderBy('id', 'desc')
            ->first();

        if (!$statusChangeLog) {
            return null;
        }

        // Validate that the log has required properties
        if (!$statusChangeLog->viewed_at || !$statusChangeLog->id) {
            return null;
        }

        // Store values in variables to avoid issues with closures
        $waitingLogViewedAt = $statusChangeLog->viewed_at;
        $waitingLogId = $statusChangeLog->id;

        // Find all logs before the one that changed status to WAITING
        // ordered by date descending
        $allLogs = StoryLog::where('story_id', $story->id)
            ->where(function ($query) use ($waitingLogViewedAt, $waitingLogId) {
                $query->where('viewed_at', '<', $waitingLogViewedAt)
                    ->orWhere(function ($q) use ($waitingLogViewedAt, $waitingLogId) {
                        $q->where('viewed_at', '=', $waitingLogViewedAt)
                            ->where('id', '<', $waitingLogId);
                    });
            })
            ->orderBy('viewed_at', 'desc')
            ->orderBy('id', 'desc')
            ->get();

        // Find the first log that has a status different from WAITING/PROBLEM
        foreach ($allLogs as $log) {
            if (isset($log->changes['status'])) {
                $logStatus = $log->changes['status'];
                if (!in_array($logStatus, [StoryStatus::Problem->value, StoryStatus::Waiting->value])) {
                    return $logStatus;
                }
            }
        }

        return null;
    }

    /**
     * Restore story to target status and add development note
     *
     * @param Story $story
     * @param string $targetStatus The status to restore to (may differ from original previous status)
     * @param int $daysInWaiting
     * @param string $originalPreviousStatus The original previous status (for display in note)
     * @return void
     */
    private function restoreStory(Story $story, string $targetStatus, int $daysInWaiting, string $originalPreviousStatus = null): void
    {
        // Validate story has an ID
        if (!$story || !$story->id) {
            throw new \Exception("Story does not have a valid ID");
        }

        // Map status values to Italian display names
        $statusNames = [
            'new' => 'New',
            'backlog' => 'Backlog',
            'assigned' => 'Assigned',
            'todo' => 'Todo',
            'progress' => 'Progress',
            'testing' => 'Testing',
            'tested' => 'Tested',
            'released' => 'Released',
            'done' => 'Done',
            'problem' => 'Problem',
            'waiting' => 'Waiting',
            'rejected' => 'Rejected',
        ];
        
        // Use original previous status for display in note, or target status if not provided
        $statusForNote = $originalPreviousStatus ?? $targetStatus;
        $statusDisplayName = $statusNames[$statusForNote] ?? ucfirst($statusForNote);
        
        // Get waiting reason
        $waitingReason = $story->waiting_reason ?? 'Non specificato';
        
        // Create development note
        // If we changed status (from progress/released/done to todo), mention both in the note
        if ($originalPreviousStatus && $originalPreviousStatus !== $targetStatus) {
            $originalStatusDisplayName = $statusNames[$originalPreviousStatus] ?? ucfirst($originalPreviousStatus);
            $targetStatusDisplayName = $statusNames[$targetStatus] ?? ucfirst($targetStatus);
            $note = "Rimesso da IN attesa in {$targetStatusDisplayName} (precedentemente era in {$originalStatusDisplayName}) perché sono passati {$daysInWaiting} giorni.\nMotivo dell'attesa: {$waitingReason}";
        } else {
            $note = "Rimesso da IN attesa in {$statusDisplayName} perché sono passati {$daysInWaiting} giorni.\nMotivo dell'attesa: {$waitingReason}";
        }
        
        // Prepend note to existing description
        $existingDescription = $story->description ?? '';
        $newDescription = $existingDescription 
            ? $note . "\n\n" . $existingDescription
            : $note;
        
        // Update story status and description
        // Use save() instead of saveQuietly() to trigger StoryObserver and create story_log entry
        $story->status = $targetStatus;
        $story->description = $newDescription;
        // Note: waiting_reason is kept (not cleared) as per requirements
        
        try {
            $story->save();
        } catch (\Exception $e) {
            // If save fails, try to get more context
            Log::error("Failed to save story ID {$story->id}: " . $e->getMessage() . " - Trace: " . $e->getTraceAsString());
            throw $e;
        }
    }
}

