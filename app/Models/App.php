<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class App extends Model
{
    use HasFactory, HasTranslations;

    //translatable fields
    public $translatable = ['name', 'description'];

    public $fillable = ['name', 'description'];

    //TODO relationship with LAYERS
}
