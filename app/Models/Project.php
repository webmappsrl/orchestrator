<?php

namespace App\Models;

use App\Models\Epic;
use App\Models\Tag;
use Exception;
use Spatie\MediaLibrary\HasMedia;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\InteractsWithMedia;
use Overtrue\LaravelFavorite\Traits\Favoriteable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Log;

class Project extends Model implements HasMedia
{
    use HasFactory, Favoriteable, InteractsWithMedia;

    protected $fillable = [
        'name',
        'description',
        'customer_id',
        'wmpm_id'
    ];

    protected $casts = [
        'due_date' => 'date',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function epics()
    {
        return $this->hasMany(Epic::class);
    }



    /**
     * Get all the tags for the project.
     */
    public function tags()
    {
        return $this->morphMany(Tag::class, 'taggable');
    }

    public function tagEpics()
    {
        return $this->belongsToMany(Epic::class, 'epic_project_tags');
    }

    public function stories()
    {
        return $this->hasMany(Story::class);
    }

    /**
     * Returns only the stories that are not in some epic(backlog stories)
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */

    public function backlogStories()
    {
        return $this->hasMany(Story::class)
            ->whereNull('epic_id')
            ->where('status', '!=', 'done')
            ->doesntHave('deadlines');
    }

    /**
     * Returns the SAL of the milestone
     *
     * @return int
     */
    public function wip(): string
    {
        $totalEpics = $this->epics->count();
        $doneEpics = $this->epics->where('status', 'done')->count();

        return count($this->epics) == 0 ? 'ND' : $doneEpics . '/' . $totalEpics;
    }

    protected static function booted()
    {
        static::saved(function (Project $entity) {
            try {
                $tag = Tag::firstOrCreate([
                    'name' => class_basename($entity) . ': ' . $entity->name
                ]);

                if ($tag && $entity) {
                    $tag->taggable()->saveQuietly($entity);
                    $entity->tags()->saveQuietly($tag);
                }
            } catch (Exception $e) {
                // Logga l'errore con maggiori dettagli
                Log::error('Error saving tags: ' . $e->getMessage(), [
                    'entity' => $entity,
                    'tag' => isset($tag) ? $tag : null,
                ]);
            }
        });
    }
}
