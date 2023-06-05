<?php

namespace App\Models;

use App\Models\Epic;
use App\Models\Story;
use App\Models\Customer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

class Deadline extends Model
{
    use HasFactory;

    public function customers()
    {
        return $this->hasMany(Customer::class);
    }

    public function stories(): MorphToMany
    {
        return $this->morphedByMany(Story::class, 'deadlineable');
    }
    public function epics(): MorphToMany
    {
        return $this->morphedByMany(Epic::class, 'deadlineable');
    }
}
