<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StoryGithubPr extends Model
{
    protected $fillable = ['story_id', 'repo', 'pr_number', 'change_requests_count', 'merged_at'];

    protected $casts = ['merged_at' => 'datetime'];

    public function story(): BelongsTo
    {
        return $this->belongsTo(Story::class);
    }
}
