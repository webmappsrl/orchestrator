<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Scheduled Tasks Configuration
    |--------------------------------------------------------------------------
    |
    | This file controls which scheduled tasks are enabled in the orchestrator.
    | You can enable or disable each task by setting the corresponding
    | environment variable in your .env file to true or false.
    |
    */

    'tasks' => [
        'story_progress_to_todo' => env('ENABLE_STORY_PROGRESS_TO_TODO', false),
        'story_scrum_to_done' => env('ENABLE_STORY_SCRUM_TO_DONE', false),
        'sync_stories_calendar' => env('ENABLE_SYNC_STORIES_CALENDAR', false),
        'story_auto_update_status' => env('ENABLE_STORY_AUTO_UPDATE_STATUS', false),
        'process_inbound_emails' => env('ENABLE_PROCESS_INBOUND_EMAILS', false),
    ],

    'story' => [
        'status' => [
            'color-mapping' => [
                'new' => '#3b82f6', // Blue
                'backlog' => '#64748b', // Slate
                'assigned' => '#ea580c', // Orange 600 (più scuro - da fare)
                'todo' => '#f97316', // Orange 500 (medio - da fare)
                'progress' => '#fb923c', // Orange 400 (chiaro - da fare)
                'testing' => '#fdba74', // Orange 300 (più chiaro - da fare)
                'tested' => '#86efac', // Green 300 (più chiaro - da completare)
                'released' => '#16a34a', // Green 600 (più scuro - rilasciato)
                'done' => '#4ade80', // Green 400 (medio - da completare)
                'problem' => '#dc2626', // Red 600
                'waiting' => '#eab308', // Yellow 500 (giallo scuro)
                'rejected' => '#dc2626', // Red 600
            ],
        ],
    ],

];
