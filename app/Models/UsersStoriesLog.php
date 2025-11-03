<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UsersStoriesLog extends Model
{
    use HasFactory;

    protected $table = 'users_stories_log';

    protected $fillable = [
        'date',
        'user_id',
        'story_id',
        'elapsed_minutes',
    ];

    protected $casts = [
        'date' => 'date',
        'elapsed_minutes' => 'integer',
    ];

    /**
     * Get the user that owns the log entry.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the story that the log entry belongs to.
     */
    public function story(): BelongsTo
    {
        return $this->belongsTo(Story::class);
    }
}
