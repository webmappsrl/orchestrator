<?php

namespace App\Observers;

use App\Models\Documentation;
use App\Jobs\GenerateDocumentationPdfJob;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class DocumentationObserver
{
    /**
     * Handle the Documentation "created" event.
     */
    public function created(Documentation $documentation): void
    {
        // Dispatch job to generate PDF
        GenerateDocumentationPdfJob::dispatch($documentation->id);
        
        Log::info('Dispatched PDF generation job for new documentation', [
            'documentation_id' => $documentation->id,
        ]);
    }

    /**
     * Handle the Documentation "updated" event.
     */
    public function updated(Documentation $documentation): void
    {
        // Regenerate PDF if name or description changed
        if ($documentation->isDirty(['name', 'description'])) {
            GenerateDocumentationPdfJob::dispatch($documentation->id);
            
            Log::info('Dispatched PDF regeneration job for updated documentation', [
                'documentation_id' => $documentation->id,
                'changed_fields' => $documentation->getDirty(),
            ]);
        }
    }

    /**
     * Handle the Documentation "saved" event.
     */
    public function saved(Documentation $documentation): void
    {
        // This is handled in created/updated events
    }

    /**
     * Handle the Documentation "deleted" event.
     */
    public function deleted(Documentation $documentation): void
    {
        // Delete associated PDF file if it exists
        if ($documentation->pdf_url) {
            try {
                // Extract filename from URL
                $filename = basename(parse_url($documentation->pdf_url, PHP_URL_PATH));
                
                if ($filename) {
                    $storagePath = storage_path('app/public/documentations/' . $filename);
                    
                    if (File::exists($storagePath)) {
                        File::delete($storagePath);
                        Log::info('Deleted PDF file for documentation', [
                            'documentation_id' => $documentation->id,
                            'filename' => $filename,
                        ]);
                    }
                }
            } catch (\Exception $e) {
                Log::error('Failed to delete PDF file for documentation', [
                    'documentation_id' => $documentation->id,
                    'pdf_url' => $documentation->pdf_url,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}

