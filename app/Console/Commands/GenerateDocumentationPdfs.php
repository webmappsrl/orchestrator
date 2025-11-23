<?php

namespace App\Console\Commands;

use App\Jobs\GenerateDocumentationPdfJob;
use App\Models\Documentation;
use Illuminate\Console\Command;

class GenerateDocumentationPdfs extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'orchestrator:documentation-pdf-generate 
                            {--id= : The ID of a specific documentation to generate PDF for (optional)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate PDF files for documentations. Use --id to generate for a specific documentation, or omit to generate for all.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $documentationId = $this->option('id');

        if ($documentationId) {
            // Generate PDF for specific documentation
            $documentation = Documentation::find($documentationId);
            
            if (!$documentation) {
                $this->error("Documentation with ID {$documentationId} not found.");
                return 1;
            }

            $this->info("Generating PDF for documentation: {$documentation->name} (ID: {$documentation->id})...");
            
            GenerateDocumentationPdfJob::dispatch($documentation->id);
            
            $this->info("PDF generation job dispatched for documentation ID: {$documentation->id}");
        } else {
            // Generate PDF for all documentations
            $documentations = Documentation::all();
            
            if ($documentations->isEmpty()) {
                $this->warn('No documentations found.');
                return 0;
            }

            $this->info("Generating PDFs for {$documentations->count()} documentations...");
            
            foreach ($documentations as $documentation) {
                $this->line("  - Dispatching job for: {$documentation->name} (ID: {$documentation->id})");
                GenerateDocumentationPdfJob::dispatch($documentation->id);
            }
            
            $this->info("Dispatched {$documentations->count()} PDF generation jobs.");
        }

        return 0;
    }
}
