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
        'generate_monthly_activity_reports' => env('ENABLE_GENERATE_MONTHLY_ACTIVITY_REPORTS', false),
        'scrum_archive' => env('ENABLE_SCRUM_ARCHIVE', false),
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

    /*
    |--------------------------------------------------------------------------
    | PDF Footer Configuration
    |--------------------------------------------------------------------------
    |
    | This value is used as the footer text in PDF documents generated for
    | documentation. You can customize it by setting PDF_FOOTER in your .env file.
    | Use <br> tags for line breaks in the footer text.
    |
    */

    'pdf_footer' => env('PDF_FOOTER', 'Webmapp S.r.l. - Via Antonio Cei, 2 - 56123 Pisa <br>C.F. / P. IVA: 02266770508 - Tel. +39 328 5360803 <br>www.webmapp.it | info@webmapp.it'),

    /*
    |--------------------------------------------------------------------------
    | PDF Logo Configuration
    |--------------------------------------------------------------------------
    |
    | This value is used as the logo path for the header in PDF documents.
    | The logo should be placed in a directory that is not part of the repository.
    | Default location: storage/app/pdf-logo/logo.png
    | You can customize it by setting PDF_LOGO_PATH in your .env file.
    | If the logo doesn't exist, the header will be generated without the logo.
    |
    */

    'pdf_logo_path' => env('PDF_LOGO_PATH', storage_path('app/pdf-logo/logo.png')),

    /*
     * Nova Logo Configuration
     *--------------------------------------------------------------------------
     *
     * This value is used as the logo path for Nova interface.
     * Nova requires an SVG file. If you have a PNG logo, you can:
     * 1. Convert it to SVG
     * 2. Use a wrapper SVG that references the PNG
     * 3. Place an SVG version in public/images/
     *
     * Default: public/images/logo.svg (fallback to PDF logo if exists)
     *
     */

    'nova_logo_path' => env('NOVA_LOGO_PATH', public_path('images/logo-montagna-servizi.svg')),

    /*
    |--------------------------------------------------------------------------
    | Story Allowed File Types Configuration
    |--------------------------------------------------------------------------
    |
    | This configuration defines which file types are allowed for upload
    | in Story documents. You can customize this by setting the corresponding
    | environment variables in your .env file.
    |
    | File types are organized by category:
    | - documents: PDF, DOC, DOCX, etc.
    | - images: JPEG, PNG, GIF, etc.
    | - audio: MP3, M4A, WAV, etc. (for verbalization)
    |
    */

    'story_allowed_file_types' => [
        'documents' => explode(',', env('STORY_ALLOWED_DOCUMENT_TYPES', 'pdf,doc,docx,json,geojson,txt,csv')),
        'images' => explode(',', env('STORY_ALLOWED_IMAGE_TYPES', 'jpg,jpeg,png,gif,bmp,webp,svg,tiff,heic')),
        'audio' => explode(',', env('STORY_ALLOWED_AUDIO_TYPES', 'mp3,m4a,wav,ogg,aac,flac,mp4')),
    ],

    /*
    |--------------------------------------------------------------------------
    | Story Allowed MIME Types Configuration
    |--------------------------------------------------------------------------
    |
    | This configuration defines which MIME types are allowed for upload
    | in Story documents. This maps to the file extensions above.
    | You can customize this by setting the corresponding environment variables.
    |
    */

    'story_allowed_mime_types' => [
        'documents' => explode(',', env('STORY_ALLOWED_DOCUMENT_MIMES', 'application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document,application/json,application/geo+json,text/plain,text/csv')),
        'images' => explode(',', env('STORY_ALLOWED_IMAGE_MIMES', 'image/jpeg,image/jpg,image/png,image/gif,image/bmp,image/webp,image/svg+xml,image/tiff,image/heic')),
        'audio' => explode(',', env('STORY_ALLOWED_AUDIO_MIMES', 'audio/mpeg,audio/mp4,audio/x-m4a,audio/wav,audio/ogg,audio/aac,audio/flac,audio/x-ms-wma,video/mp4')),
    ],

    /*
    |--------------------------------------------------------------------------
    | Story Maximum File Size Configuration
    |--------------------------------------------------------------------------
    |
    | This configuration defines the maximum file size allowed for upload
    | in Story documents. The value is in bytes. You can customize it by
    | setting STORY_MAX_FILE_SIZE in your .env file (value in MB).
    | Default: 10 MB (matching media-library configuration)
    |
    */

    'story_max_file_size' => env('STORY_MAX_FILE_SIZE', 10) * 1024 * 1024, // Convert MB to bytes

    /*
    |--------------------------------------------------------------------------
    | Platform Configuration
    |--------------------------------------------------------------------------
    |
    | This configuration defines the platform name and acronym used in
    | PDF report filenames and other places. You can customize it by
    | setting PLATFORM_NAME and PLATFORM_ACRONYM in your .env file.
    |
    */

    'platform_name' => env('PLATFORM_NAME', 'Centro Servizi Montagna'),
    'platform_acronym' => env('PLATFORM_ACRONYM', 'CSM'),

];
