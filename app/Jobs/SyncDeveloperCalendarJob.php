<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class SyncDeveloperCalendarJob implements ShouldQueue, ShouldBeUniqueUntilProcessing
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Debounce window in seconds: every dispatch is delayed by this amount and
     * deduplicated by the unique lock, so N saves in a short burst produce a
     * single sync that reads the final state from the database.
     */
    public const DEBOUNCE_SECONDS = 60;

    /**
     * Safety expiry of the unique lock: if the job dies before processing,
     * the lock is released after this window and a new sync can be queued.
     *
     * @var int
     */
    public $uniqueFor = 300;

    /**
     * WithoutOverlapping releases an overlapping job back onto the queue and
     * every release counts as an attempt: keep enough tries so a release does
     * not mark the job as failed (supervisor default is tries=1).
     *
     * @var int
     */
    public $tries = 5;

    public string $developerEmail;

    public function __construct(string $developerEmail)
    {
        $this->developerEmail = $developerEmail;
        $this->delay(self::DEBOUNCE_SECONDS);
    }

    /**
     * One pending sync per developer: dispatches within the debounce window
     * are deduplicated by this key.
     */
    public function uniqueId(): string
    {
        return $this->developerEmail;
    }

    /**
     * Keep the unique lock on Redis: it survives `php artisan cache:clear`
     * (which clears the default file store) and does not depend on web and
     * worker sharing the same filesystem.
     */
    public function uniqueVia(): Repository
    {
        return Cache::driver('redis');
    }

    /**
     * Never run two syncs for the same developer concurrently: the sync is
     * delete-then-recreate, idempotent only when serialized. An overlapping
     * job is released back onto the queue and retried after 60 seconds.
     */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping($this->developerEmail))
                ->releaseAfter(60)
                ->expireAfter(300),
        ];
    }

    public function handle(): void
    {
        Artisan::call('sync:stories-calendar', ['developerEmail' => $this->developerEmail]);
        $output = Artisan::output();

        // The command swallows Google API exceptions internally and reports
        // them only on its output: surface them in the logs, otherwise the
        // job always looks green on Horizon.
        if (str_contains($output, 'Failed to')) {
            Log::warning("Calendar sync for {$this->developerEmail} completed with errors", ['output' => $output]);
        } else {
            Log::info("Calendar sync completed for {$this->developerEmail}");
        }
    }
}
