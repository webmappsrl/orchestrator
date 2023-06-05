<?php

namespace App\Models;

use App\Models\Epic;
use App\Models\Story;
use App\Models\Customer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

class Deadline extends Model
{
    use HasFactory;

    protected $casts = [
        'due_date' => 'datetime',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function stories(): MorphToMany
    {
        return $this->morphedByMany(Story::class, 'deadlineable');
    }
    public function epics(): MorphToMany
    {
        return $this->morphedByMany(Epic::class, 'deadlineable');
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
}
