<?php

namespace App\Console\Commands;

use App\Jobs\ProcessInboundEmails;
use Illuminate\Console\Command;

class ProcessInboundEmailsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'orchestrator:process-inbound-emails';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Dispatch jobs to process inbound emails';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        ProcessInboundEmails::dispatch();
        $this->info('Job dispatched.');
    }
}
