<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use App\Mail\CustomerStoriesDigest;
use Illuminate\Support\Facades\Mail;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Contracts\Queue\ShouldBeUnique;

class SendDigestEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $customers = User::whereJsonContains('roles', 'customer')->get();

        foreach ($customers as $customer) {
            //if the customer has at least one story that was updated in the last 24 hours send the digest, otherwise skip
            if ($customer->stories()->where('updated_at', '>=', now()->subDay())->exists()) {

                Mail::to($customer->email)->send(new CustomerStoriesDigest($customer));
            }
        }
    }
}
