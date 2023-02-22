<?php

namespace App\Models;

use App\Models\Layer;
use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class App extends Model
{
    use HasFactory, HasTranslations;

    //translatable fields
    public $translatable = ['name', 'description'];

    public $fillable = ['name', 'description'];


    /**
     * relationship with layers
     * @return hasMany
     */
    public function layers()
    {
        return $this->hasMany(Layer::class);
    }
}
