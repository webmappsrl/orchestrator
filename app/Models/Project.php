<?php

namespace App\Models;

use App\Models\Epic;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Project extends Model
{
    use HasFactory;

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
        return $this->hasMany(Story::class)->whereNull('epic_id');
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
}
