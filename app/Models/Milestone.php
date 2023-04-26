<?php

namespace App\Models;

use App\Enums\EpicStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Milestone extends Model
{
    use HasFactory;


    protected $fillable = [
        'name',
        'description',
        'due_date'
    ];

    protected $casts = [
        'due_date' => 'datetime',
    ];


    /**
     * Returns all the epics that belong to the Milestone
     *
     * @return HasMany
     */
    public function epics(): HasMany
    {
        return $this->hasMany(Epic::class);
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

    /**
     * Returns only the epics that have status = new
     *
     * @return HasMany
     */
    public function newEpics(): HasMany
    {
        return $this->hasMany(Epic::class)->where('status', EpicStatus::New);
    }


    /**
     * Returns the relation of epics that have status = progress
     *
     * @return HasMany
     */
    public function progressEpics(): HasMany
    {
        return $this->hasMany(Epic::class)->where('status', EpicStatus::Progress);
    }

    /**
     * Returns only the epics that have status = test
     *
     * @return HasMany
     */
    public function testEpics(): HasMany
    {
        return $this->hasMany(Epic::class)->where('status', EpicStatus::Test);
    }

    /**
     * Returns only the epics that have status = done
     *
     * @return HasMany
     */
    public function doneEpics(): HasMany
    {
        return $this->hasMany(Epic::class)->where('status', EpicStatus::Done);
    }

    /**
     * Returns only the epics that have status = rejected
     *
     * @return HasMany
     */
    public function rejectedEpics(): HasMany
    {
        return $this->hasMany(Epic::class)->where('status', EpicStatus::Rejected);
    }

    /**
     * Returns only the epics that have status = project
     *
     * @return HasMany
     */
    public function projectEpics(): HasMany
    {
        return $this->hasMany(Epic::class)->where('status', EpicStatus::Project);
    }
}
