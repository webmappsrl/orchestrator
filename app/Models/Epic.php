<?php

namespace App\Models;

use App\Models\Project;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Epic extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'milestone_id',
        'user_id'
    ];

    public function stories()
    {
        return $this->hasMany(Story::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function milestone()
    {
        return $this->belongsTo(Milestone::class);
    }

    public function project()
    {
        return $this->belongsTo(Project::class);
    }
}
