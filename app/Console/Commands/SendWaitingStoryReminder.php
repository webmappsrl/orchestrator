<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use App\Models\User;
use App\Models\Story;
use App\Models\StoryLog;
use App\Enums\StoryStatus;
use Illuminate\Console\Command;
use App\Mail\WaitingStoryReminder;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\DB;

class SendWaitingStoryReminder extends Command
{
    protected $signature = 'story:send-waiting-reminder';
    protected $description = 'Sends a reminder email to customers for stories in Waiting status after 3 working days';

    public function handle()
    {
        try {
            $this->info('story:send-waiting-reminder start');
            Log::info('story:send-waiting-reminder start');
            $daysAgo = $this->daysAgo();
            $stories = Story::where('status', StoryStatus::Waiting->value)
                ->whereDate('created_at', '<=', $daysAgo)
                ->get();

            foreach ($stories as $story) {
                try {

                    if ($this->shouldSendReminder($story)) {
                        $this->sendReminderEmail($story);
                        $this->info('Sent reminder for story ID: ' . $story->id);
                        Log::info('Sent reminder for story ID: ' . $story->id);
                    }
                } catch (\Exception $e) {
                    $this->error('Error processing story ID: ' . $story->id . '. Error: ' . $e->getMessage());
                    Log::error('Error processing story ID: ' . $story->id . '. Error: ' . $e->getMessage());
                }
            }

            $this->info('All applicable reminders have been sent.');
            Log::info('All applicable reminders have been sent.');
        } catch (\Exception $e) {
            $this->error('An error occurred while running the command: ' . $e->getMessage());
            Log::error('An error occurred while running the command: ' . $e->getMessage());
        }
    }

    private function getLastStatusChangeDate($story)
    {
        try {
            $logs = StoryLog::where('story_id', $story->id)
                ->where('changes->status', StoryStatus::Waiting->value)
                ->orderBy('viewed_at', 'desc')
                ->get();
            $lastWaitingStatus = null;
            $lastRelevantChange = null;

            foreach ($logs as $log) {
                $changes = $log->changes;

                if (!$lastWaitingStatus && isset($changes['status']) && $changes['status'] === StoryStatus::Waiting->value) {
                    $lastWaitingStatus = $log;
                }

                if ($lastWaitingStatus) {
                    if ($log->viewed_at <= $lastWaitingStatus->viewed_at) {
                        break;
                    }

                    if (!isset($changes['watch']) || count($changes) > 1) {
                        $lastRelevantChange = $log;
                        break;
                    }
                }
            }

            if ($lastRelevantChange) {
                return $lastRelevantChange->viewed_at;
            } elseif ($lastWaitingStatus) {
                return $lastWaitingStatus->viewed_at;
            } else {
                return $story->updated_at;
            }
        } catch (\Exception $e) {
            Log::error('Error in getLastStatusChangeDate for story ID: ' . $story->id . '. Error: ' . $e->getMessage());
            throw $e;
        }
    }

    private function shouldSendReminder($story)
    {
        try {
            $lastStatusChangeDate = $this->getLastStatusChangeDate($story);
            $ThreeWorkingDaysAgo = $this->daysAgo();

            return $lastStatusChangeDate->lessThanOrEqualTo($ThreeWorkingDaysAgo);
        } catch (\Exception $e) {
            Log::error('Error in shouldSendReminder. Error: ' . $e->getMessage());
            throw $e;
        }
    }

    private function sendReminderEmail($story)
    {
        try {
            $mailToUser = User::find($story->creator_id);
            if ($mailToUser) {
                Mail::to($mailToUser->email)->send(new WaitingStoryReminder($story));
                $this->updteWaintingInStoryLog($story);
            } else {
                Log::warning('mailToUser not found for story ID: ' . $story->id);
            }
        } catch (\Exception $e) {
            Log::error('Error sending reminder email for story ID: ' . $story->id . '. Error: ' . $e->getMessage());
            throw $e;
        }
    }

    private function updteWaintingInStoryLog($story)
    {
        try {
            $timestamp = now()->format('Y-m-d H:i');
            $jsonChanges = ['status' => StoryStatus::Waiting->value];
            StoryLog::create([
                'story_id' => $story->id,
                'user_id' => 1,
                'viewed_at' => $timestamp,
                'changes' => $jsonChanges,
            ]);
        } catch (\Exception $e) {
            Log::error('Error updating story status for story ID: ' . $story->id . '. Error: ' . $e->getMessage());
            throw $e;
        }
    }

    private function daysAgo()
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
}
