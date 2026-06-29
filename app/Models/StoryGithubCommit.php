<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StoryGithubCommit extends Model
{
    protected $fillable = ['story_id', 'sha', 'repo', 'author_email', 'committed_at'];

    protected $casts = ['committed_at' => 'datetime'];

    public function story(): BelongsTo
    {
        return $this->belongsTo(Story::class);
    }

    public static function forStory(int $storyId): \Illuminate\Database\Eloquent\Collection
    {
        return static::where('story_id', $storyId)->orderBy('committed_at')->get();
    }

    public static function hasCommitsForStory(int $storyId): bool
    {
        return static::where('story_id', $storyId)->exists();
    }
}
