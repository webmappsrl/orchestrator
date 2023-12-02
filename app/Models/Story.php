<?php

namespace App\Models;

use App\Models\Epic;
use App\Enums\UserRole;
use AWS\CRT\HTTP\Request;
use Spatie\MediaLibrary\HasMedia;
use Illuminate\Support\Facades\Mail;
use App\Mail\CustomerNewStoryCreated;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\InteractsWithMedia;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Facades\Log;

class Story extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia;

    protected $fillable = [
        'status',
        'creator_id',
    ];
    public function parent()
    {
        return $this->config();
    }

    public function config()
    {
        //if the user was in the epic view, the epic name will be shown in the breadcrumbs, otherwise the project name

        if ($this->belongsTo(Epic::class)) {
            return $this->belongsTo(Epic::class, 'epic_id');
        } else {
            return $this->belongsTo(Project::class, 'project_id');
        }
    }


    protected static function booted()
    {
        //update epic status whenever a story is created or updated
        static::saved(function (Story $story) {
            if (!empty($story->epic)) {
                $epic = $story->epic;
                $epic->status = $epic->getStatusFromStories()->value;
                $epic->save();
            }
        });

        static::created(function (Story $story) {
            if (auth()->user()) {
                $story->creator_id = auth()->user()->id;
                $story->save();

                if (auth()->user()->hasRole(UserRole::Customer) && $story->creator_id == auth()->user()->id) {
                    $developers = User::whereJsonContains('roles', UserRole::Developer)->get();
                    foreach ($developers as $developer) {
                        try {
                            Mail::to($developer->email)->send(new CustomerNewStoryCreated($story));
                        } catch (\Exception $e) {
                            Log::error($e->getMessage());
                        }
                    }
                }
            }
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
    public function creator()
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * It returns the corresponding EPIC
     *
     * @return BelongsTo
     */
    public function epic(): BelongsTo
    {
        return $this->belongsTo(Epic::class);
    }

    public function deadlines(): MorphToMany
    {
        return $this->morphToMany(Deadline::class, 'deadlineable');
    }

    public function tester()
    {
        return $this->belongsTo(User::class, 'tester_id');
    }

    /**
     * Register a spatie media collection
     * @return void
     * @link https://spatie.be/docs/laravel-medialibrary/v9/working-with-media-collections/defining-media-collections
     */
    public function registerMediaCollections(): void
    {

        $this->addMediaCollection('documents')->acceptsMimeTypes(config('services.media-library.allowed_document_formats'));

        $this->addMediaCollection('images')->acceptsMimeTypes(config('services.media-library.allowed_image_formats'));
    }
}
