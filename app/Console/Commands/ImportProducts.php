<?php

namespace App\Console\Commands;

use App\Imports\ProductsImport;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Storage;






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
        $path = $this->argument('path')[0];
        Excel::import(new ProductsImport(), $path, null, \Maatwebsite\Excel\Excel::XLSX);
        $this->info('Products imported successfully');
    }
}