<?php

namespace App\Jobs;

use App\Models\User;
use Illuminate\Bus\Queueable;
use App\Mail\CustomerStoriesDigest;
use Illuminate\Support\Facades\Mail;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Facades\Log;

use function PHPUnit\Framework\isEmpty;

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
        if (empty($customers)) {
            throw new \Exception('No customers found');
            Log::debug('No customers found');
            return;
        }
        foreach ($customers as $customer) {
            //if the customer has at least one story that was updated in the last 24 hours send the digest, otherwise skip
            if ($customer->customerStories()->where('updated_at', '>=', now()->subDay())->exists()) {
                try {
                    Mail::to($customer->email)->send(new CustomerStoriesDigest($customer));
                } catch (\Exception $e) {
                    throw new \Exception('Error sending email to customer ' . $customer->name . ' ' . $e->getMessage());
                    Log::debug('Error sending email to customer ' . $customer->name . ' ' . $e->getMessage());
                }
            } else {
                throw new \Exception('No stories updated in the last 24h for customer ' . $customer->name);
                Log::debug('No stories updated in the last 24h for customer ' . $customer->name);
                return;
            }
        }
    }
}
