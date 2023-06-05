<?php

namespace App\Models;

use App\Models\Epic;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

class Story extends Model
{
    use HasFactory;

    protected $fillable = [
        'status'
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
    }

    public function user()
    {
        return $this->belongsTo(User::class);
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
}
