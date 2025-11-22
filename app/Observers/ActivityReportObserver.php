<?php

namespace App\Observers;

use App\Models\ActivityReport;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class ActivityReportObserver
{
    /**
     * Handle the ActivityReport "created" event.
     */
    public function created(ActivityReport $activityReport): void
    {
        $activityReport->syncStories();
    }

    /**
     * Handle the ActivityReport "updated" event.
     */
    public function updated(ActivityReport $activityReport): void
    {
        // Sync stories if relevant fields have changed
        if ($activityReport->isDirty(['owner_type', 'customer_id', 'organization_id', 'report_type', 'year', 'month'])) {
            $activityReport->syncStories();
        }
    }

    /**
     * Handle the ActivityReport "saved" event.
     */
    public function saved(ActivityReport $activityReport): void
    {
        // This is handled in created/updated events
    }

    /**
     * Handle the ActivityReport "deleted" event.
     */
    public function deleted(ActivityReport $activityReport): void
    {
        // Delete associated PDF file if it exists
        if ($activityReport->pdf_url) {
            try {
                // Extract filename from URL
                $filename = basename(parse_url($activityReport->pdf_url, PHP_URL_PATH));
                
                if ($filename) {
                    $storagePath = storage_path('app/public/activity-reports/' . $filename);
                    
                    if (File::exists($storagePath)) {
                        File::delete($storagePath);
                        Log::info('Deleted PDF file for activity report', [
                            'activity_report_id' => $activityReport->id,
                            'filename' => $filename,
                        ]);
                    }
                }
            } catch (\Exception $e) {
                Log::error('Failed to delete PDF file for activity report', [
                    'activity_report_id' => $activityReport->id,
                    'pdf_url' => $activityReport->pdf_url,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}

