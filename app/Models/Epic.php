<?php

namespace App\Models;

use App\Models\Project;
use App\Enums\EpicStatus;
use App\Enums\StoryStatus;
use App\Observers\EpicObserver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Epic extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia;

    protected $fillable = [
        'name',
        'description',
        'milestone_id',
        'project_id',
        'user_id',
        'wmpm_id',
        'text2stories',
        'notes',
    ];

    protected static function boot()
    {
        parent::boot();

        Epic::observe(EpicObserver::class);
    }

    /**
     * Get the parent model for the relationship in breadcrumbs
     *
     */
    public function parent()
    {
        return $this->config();
    }
    public function config()
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    public function stories()
    {
        return $this->hasMany(Story::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function milestone()
    {
        return $this->belongsTo(Milestone::class);
    }

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function tagProjects()
    {
        return $this->belongsToMany(Project::class, 'epic_project_tags');
    }

    public function deadlines(): MorphToMany
    {
        return $this->morphToMany(Deadline::class, 'deadlineable');
    }

    /**
     * Check epic status based on stories status
     * Se la epica non ha storie -> status=new,
     * quando la epica ha tutte le storie in status new ha status new, 
     * quando la epica ha tutte le storie in status test è in status test, 
     * quando la epica ha tutte le storie in status done ha status done.
     * quando la epica ha almeno una storia con status diverso da new ha stato in progress, 
     * quando la epica ha almeno una storia con status rejected ha stato rejected.
     * quando la epica ha tutte le storie in done e almeno una in test ha stato test.
     *
     * @return EpicStatus
     */
    public function getStatusFromStories(): EpicStatus
    {
        $totalStories = $this->stories();
        $newStories = $this->stories()->where('status', StoryStatus::New)->get();
        $TestStories = $this->stories()->where('status', StoryStatus::Test)->get();
        $doneStories = $this->stories()->where('status', StoryStatus::Done)->get();
        $rejectedStories = $this->stories()->where('status', StoryStatus::Rejected)->get();

        if ($totalStories->count() == 0) {
            return EpicStatus::New;
        }

        if ($newStories->count() == $totalStories->count()) {
            return EpicStatus::New;
        }

        if ($TestStories->count() == $totalStories->count()) {
            return EpicStatus::Test;
        }

        if ($doneStories->count() == $totalStories->count()) {
            return EpicStatus::Done;
        }

        if ($rejectedStories->count() > 0) {
            return EpicStatus::Rejected;
        }

        if ($TestStories->count() > 0 && $doneStories->count() == $totalStories->count() - $TestStories->count()) {
            return EpicStatus::Test;
        }

        return EpicStatus::Progress;
    }


    /**
     * It returns a string with WIP (Work in Progress)
     *
     * @return string
     */
    public function wip(): string
    {
        if (count($this->stories) == 0) {
            return 'ND';
        }
        return $this->stories()->whereIn('status', [StoryStatus::Test, StoryStatus::Done])->count() . ' / ' . $this->stories()->count();
    }

    /**
     * Register a spatie media collection
     * @return void
     * @link https://spatie.be/docs/laravel-medialibrary/v9/working-with-media-collections/defining-media-collections
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('documents');
    }
}
