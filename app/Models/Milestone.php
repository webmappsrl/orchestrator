<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
    public function epics(): HasMany {
        return $this->hasMany(Epic::class);
    }
}
