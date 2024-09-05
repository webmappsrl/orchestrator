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

            $stories = Story::where('status', StoryStatus::Waiting->value)->get();

            foreach ($stories as $story) {
                try {
                    $lastStatusChangeDate = $this->getLastStatusChangeDate($story);
                    if ($this->shouldSendReminder($lastStatusChangeDate)) {
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

    private function shouldSendReminder($lastStatusChangeDate)
    {
        try {
            $workingDays = 0;
            $currentDate = Carbon::now();

            while ($workingDays < 3) {
                if (!$currentDate->isWeekend()) {
                    $workingDays++;
                }
                $currentDate->subDay();
            }

            return $lastStatusChangeDate->lessThanOrEqualTo($currentDate);
        } catch (\Exception $e) {
            Log::error('Error in shouldSendReminder. Error: ' . $e->getMessage());
            throw $e;
        }
    }

    private function sendReminderEmail($story)
    {
        try {
            $customer = User::find($story->creator_id);
            if ($customer) {
                Mail::to($customer->email)->send(new WaitingStoryReminder($story));
            } else {
                Log::warning('Customer not found for story ID: ' . $story->id);
            }
        } catch (\Exception $e) {
            Log::error('Error sending reminder email for story ID: ' . $story->id . '. Error: ' . $e->getMessage());
            throw $e;
        }
    }
}
