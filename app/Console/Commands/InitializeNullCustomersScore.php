<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class InitializeNullCustomersScore extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'orchestrator:initialize-scores';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Initializes null scores for customers';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $customers = \App\Models\Customer::all();
        foreach ($customers as $customer) {
            if ($customer->score === null) {
                $customer->score = 0;
                $customer->save();
            }
        }
        return 0;
    }
}
