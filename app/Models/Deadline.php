<?php

namespace App\Models;

use App\Enums\DeadlineStatus;
use App\Models\Epic;
use App\Models\Story;
use App\Models\Customer;
use App\Enums\StoryStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
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
        $counts = $this->stories()->get()->groupBy('status')->map->count();
        $html = '<ul>';
        foreach ($counts as $status => $count) {
            $html .= '<li>' . htmlspecialchars($status) . ': ' . $count . '</li>';
        }
        $html .= '</ul>';
        return $html;
    }

    /**
     * Get the parent model for the relationship in breadcrumbs
     *
     */
    public function parent()
    {
        return $this->config();
    }
    public function config()
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    /**
     * Update the status of the deadline
     * 
     * @return void
     */
    public function checkIfExpired(): void
    {
        //get the current data
        $today = now();

        //get the due date of the deadline
        $dueDate = $this->due_date;

        //if the deadline status is done then return
        if ($this->status == DeadlineStatus::Done->value) {
            return;
        }

        //if due date is before today then change the status to expired
        if ($today->gt($dueDate)) {
            $this->status = DeadlineStatus::Expired->value;
            $this->save();
        }
    }
}
