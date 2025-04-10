<?php

namespace App\Jobs;

use App\Models\User;
use App\Models\Story;
use App\Enums\StoryStatus;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class CheckDeveloperProgressJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $developerId;

    /**
     * Create a new job instance.
     *
     * @param mixed $developerId
     */
    public function __construct($developerId)
    {
        $this->developerId = $developerId;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle(): void
    {
        $developer = User::find($this->developerId);
        if (!$developer) {
            return;
        }

        $hasProgress = Story::where('user_id', $developer->id)
            ->where('status', StoryStatus::Progress->value)
            ->exists();

        if (!$hasProgress) {
            $webhookUrl = env('SLACK_ALERT_WEBHOOK');
            if (!$webhookUrl) {
                return;
            }
            $message = ":occhi: {$developer->name} o sei in :wc: o in :spaghetti: oppure ti sei dimenticato di mettere in :freccia_avanti: un ticket";
            $payload = json_encode(['text' => $message]);
            $ch = curl_init($webhookUrl);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/json"]);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_exec($ch);
            curl_close($ch);
        }
    }
}
