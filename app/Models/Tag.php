<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class Tag extends Model
{
    protected $fillable = ['name', 'taggable_type'];

    public function taggable()
    {
        return $this->morphTo();
    }

    public function getTaggableTypeAttribute()
    {
        return class_basename($this->attributes['taggable_type']);
    }
    public function getResourceUrlAttribute()
    {
        if (!$this->taggable_type || !$this->taggable_id) {
            return '#'; // Ritorna un link non cliccabile se non ci sono informazioni sufficienti.
        }

        // Rimuovi il namespace e converti il nome della classe in snake_case per il path
        $baseName = class_basename($this->taggable_type);
        $resourcePath = Str::kebab(Str::plural($baseName));

        $url = url("resources/{$resourcePath}/{$this->taggable_id}");
        return $url;
    }
}
