<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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



    //Relationship with Epiche to be implemented
}
