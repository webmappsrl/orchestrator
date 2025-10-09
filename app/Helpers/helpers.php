<?php

if (! function_exists('wrap_and_format_name')) {
    function wrap_and_format_name($name)
    {
        $wrappedName = wordwrap($name, config('orchestrator.utility.word-wrap-length'), "\n", true);
        $htmlName = str_replace("\n", '<br>', $wrappedName);

        return $htmlName;
    }
}

if (! function_exists('log_story_activity')) {
    /**
     * Log story activity to activity.log file
     *
     * @param  string  $action The action performed (e.g., 'created', 'updated', 'deleted')
     * @param  \App\Models\Story  $story The story object
     * @param  \App\Models\User|null  $user The user who performed the action
     * @param  array  $additionalData Additional data to log
     * @param  string  $level Log level (info, warning, error, etc.)
     * @return void
     */
    function log_story_activity(string $action, $story, $user = null, array $additionalData = [], string $level = 'info')
    {
        if (is_null($user)) {
            $user = auth()->user();
            if (is_null($user)) {
                $user = \App\Models\User::where('email', 'orchestrator_artisan@webmapp.it')->first();
            }
        }

        if ($user) {
            $logData = array_merge([
                'action' => $action,
                'story_id' => $story->id,
                'story_name' => $story->name,
                'user_id' => $user->id,
                'user_name' => $user->name,
                'user_email' => $user->email,
                'timestamp' => now()->format('Y-m-d H:i:s'),
            ], $additionalData);

            \Illuminate\Support\Facades\Log::channel('activity')->{$level}("Story {$action}", $logData);
        }
    }
}
