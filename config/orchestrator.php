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

];
