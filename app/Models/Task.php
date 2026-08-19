<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\User;

class Task extends Model
{
    use HasFactory;

    public const STATUS_TODO = 'todo';
    public const STATUS_COMPLETED = 'completed';

    protected $fillable = [
        'quote_id',
        'title',
        'notes',
        'due_date',
        'status',
        'completed_at',
        'creator_id',
    ];

    protected $casts = [
        'due_date' => 'datetime',
        'completed_at' => 'datetime',
    ];

    protected $attributes = [
        'status' => self::STATUS_TODO,
    ];

    public function quote(): BelongsTo
    {
        return $this->belongsTo(Quote::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    public function getAssigneeAttribute(): ?User
    {
        return $this->quote?->user;
    }

    public function scopeForUser($query, User $user)
    {
        return $query->where(function ($q) use ($user) {
            $q->whereHas('quote', function ($quoteQuery) use ($user) {
                $quoteQuery->where('user_id', $user->id);
            })->orWhere('creator_id', $user->id);
        });
    }

    public function scopeOverdue($query)
    {
        return $query->where('status', self::STATUS_TODO)
            ->whereDate('due_date', '<', now()->toDateString());
    }

    public function scopeDueToday($query)
    {
        return $query->where('status', self::STATUS_TODO)
            ->whereDate('due_date', now()->toDateString());
    }

    public function scopeUpcoming($query)
    {
        return $query->where('status', self::STATUS_TODO)
            ->whereDate('due_date', '>', now()->toDateString());
    }

    public function scopeCompletedStatus($query)
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }

    protected static function booted()
    {
        static::creating(function (Task $task) {
            if ($task->creator_id === null && auth()->check()) {
                $task->creator_id = auth()->id();
            }
        });

        static::saving(function (Task $task) {
            if (!$task->isDirty('status')) {
                return;
            }

            if ($task->status === self::STATUS_COMPLETED) {
                $task->completed_at = $task->completed_at ?? now();
            } else {
                $task->completed_at = null;
            }
        });
    }
}
