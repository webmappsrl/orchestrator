<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StoryLog extends Model
{
    use HasFactory;

    protected $fillable = ['story_id', 'user_id', 'viewed_at', 'changes',];

    protected $casts = [
        'viewed_at' => 'datetime',
        'changes' => 'json',
    ];
    public function story()
    {
        return $this->belongsTo(Story::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
