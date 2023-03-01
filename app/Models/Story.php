<?php

namespace App\Models;

use App\Models\Epic;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Story extends Model
{
    use HasFactory;

    protected $fillable = [
        'status'
    ];

    protected static function booted()
    {
        //update epic status whenever a story is created or updated
        static::saved(function (Story $story) {
            $epic = $story->epic;
            $epic->status = $epic->getStatusFromStories()->value;
            $epic->save();
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class);
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
}
