<?php

namespace App\Observers;

use App\Models\Story;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class MediaObserver
{
    /**
     * Handle the Media "created" event (when a file is uploaded).
     */
    public function created(Media $media): void
    {
        // Check if the media belongs to a Story model
        if ($media->model_type === 'App\\Models\\Story' && $media->model) {
            $story = $media->model;
            $user = Auth::user();

            if (is_null($user)) {
                $user = User::where('email', 'orchestrator_artisan@webmapp.it')->first();
            }

            if ($user && $story) {
                // Log activity to activity.log file
                $message = sprintf(
                    '%s (%s) added attachment "%s" (%s) to story #%d "%s" on %s',
                    $user->name,
                    $user->email,
                    $media->file_name,
                    $media->mime_type,
                    $story->id,
                    $story->name,
                    now()->format('d-m-Y H:i:s')
                );

                $context = [
                    'story_id' => $story->id,
                    'story_name' => $story->name,
                    'user_id' => $user->id,
                    'user_name' => $user->name,
                    'user_email' => $user->email,
                    'attachment_name' => $media->file_name,
                    'attachment_type' => $media->mime_type,
                    'attachment_size' => $media->size,
                    'collection_name' => $media->collection_name,
                    'timestamp' => now()->format('Y-m-d H:i:s'),
                ];

                Log::channel('activity')->info($message, $context);
            }
        }
    }

    /**
     * Handle the Media "deleted" event (when a file is removed).
     */
    public function deleted(Media $media): void
    {
        // Check if the media belongs to a Story model
        if ($media->model_type === 'App\\Models\\Story') {
            $user = Auth::user();

            if (is_null($user)) {
                $user = User::where('email', 'orchestrator_artisan@webmapp.it')->first();
            }

            if ($user) {
                // Log activity to activity.log file
                $message = sprintf(
                    '%s (%s) deleted attachment "%s" (%s) from story #%d on %s',
                    $user->name,
                    $user->email,
                    $media->file_name,
                    $media->mime_type,
                    $media->model_id,
                    now()->format('d-m-Y H:i:s')
                );

                $context = [
                    'story_id' => $media->model_id,
                    'user_id' => $user->id,
                    'user_name' => $user->name,
                    'user_email' => $user->email,
                    'attachment_name' => $media->file_name,
                    'attachment_type' => $media->mime_type,
                    'attachment_size' => $media->size,
                    'collection_name' => $media->collection_name,
                    'timestamp' => now()->format('Y-m-d H:i:s'),
                ];

                Log::channel('activity')->warning($message, $context);
            }
        }
    }
}
