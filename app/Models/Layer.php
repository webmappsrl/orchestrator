<?php

namespace App\Models;

use App\Models\App;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Spatie\Translatable\HasTranslations;

class Layer extends Model
{
    use HasFactory, HasTranslations;


    //translatable fields
    public $translatable = ['title'];


    public $fillable = [
        'name',
        'app_id',
        'title',
        'color',
        'query_string'
    ];

    /**
     * Relationship with App
     * 
     * @return belongsTo
     */

    public function app()
    {
        return $this->belongsTo(App::class);
    }
}
