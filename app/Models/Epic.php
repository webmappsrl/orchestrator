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
    ];

    public function stories()
    {
        return $this->hasMany(Story::class);
    }
}
