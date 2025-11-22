<?php

namespace App\Observers;

use App\Models\ActivityReport;

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
}

