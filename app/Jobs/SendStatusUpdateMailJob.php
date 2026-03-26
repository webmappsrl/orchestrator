<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use App\Mail\StoryStatusUpdate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class SendStatusUpdateMailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $story;
    public $user;
    public $context;

    public function __construct($story, $user, array $context = [])
    {
        $this->afterCommit();
        $this->story = $story;
        $this->user = $user;
        $this->context = $context;
    }

    public function handle(): void
    {
        Mail::to($this->user->email)->send(
            new StoryStatusUpdate($this->story, $this->user, $this->context)
        );
    }
}
