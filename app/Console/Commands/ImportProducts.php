<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class ImportProducts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'orchestrator:import-products {path* : Path to the Excel file}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import products from an Excel file';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
    }
}