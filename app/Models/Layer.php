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
    public $translatable = ['name', 'description'];


    public $fillable = [
        'name',
        'description',
        'app_id'
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
