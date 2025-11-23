<?php

namespace App\Jobs;

use App\Models\Documentation;
use App\Services\DocumentationPdfService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GenerateDocumentationPdfJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public ?int $documentationId;

    /**
     * Create a new job instance.
     */
    public function __construct(?int $documentationId = null)
    {
        $this->documentationId = $documentationId;
    }

    /**
     * Execute the job.
     */
    public function handle(DocumentationPdfService $pdfService): void
    {
        try {
            if ($this->documentationId) {
                // Generate PDF for specific documentation
                $documentation = Documentation::find($this->documentationId);
                
                if (!$documentation) {
                    Log::warning('Documentation not found for PDF generation', [
                        'documentation_id' => $this->documentationId,
                    ]);
                    return;
                }

                $pdfUrl = $pdfService->generatePdf($documentation);
                
                if ($pdfUrl) {
                    $documentation->pdf_url = $pdfUrl;
                    $documentation->saveQuietly(); // Use saveQuietly to avoid triggering observer
                    
                    Log::info('Documentation PDF generated successfully via job', [
                        'documentation_id' => $documentation->id,
                        'pdf_url' => $pdfUrl,
                    ]);
                } else {
                    Log::error('Failed to generate PDF for documentation', [
                        'documentation_id' => $documentation->id,
                    ]);
                }
            } else {
                // Generate PDF for all documentations
                $documentations = Documentation::all();
                
                Log::info('Starting bulk PDF generation for documentations', [
                    'count' => $documentations->count(),
                ]);

                foreach ($documentations as $documentation) {
                    try {
                        $pdfUrl = $pdfService->generatePdf($documentation);
                        
                        if ($pdfUrl) {
                            $documentation->pdf_url = $pdfUrl;
                            $documentation->saveQuietly(); // Use saveQuietly to avoid triggering observer
                            
                            Log::info('Documentation PDF generated successfully via job', [
                                'documentation_id' => $documentation->id,
                                'pdf_url' => $pdfUrl,
                            ]);
                        } else {
                            Log::error('Failed to generate PDF for documentation', [
                                'documentation_id' => $documentation->id,
                            ]);
                        }
                    } catch (\Exception $e) {
                        Log::error('Error generating PDF for documentation in bulk job', [
                            'documentation_id' => $documentation->id,
                            'error' => $e->getMessage(),
                        ]);
                        // Continue with next documentation
                    }
                }

                Log::info('Bulk PDF generation for documentations completed', [
                    'count' => $documentations->count(),
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Failed to generate documentation PDF via job', [
                'documentation_id' => $this->documentationId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }
}

