<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Epic extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'milestone_id',
        'user_id'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function milestone()
    {
        return $this->belongsTo(Milestone::class);
    }
}
