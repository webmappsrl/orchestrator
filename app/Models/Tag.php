<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Tag extends Model
{
    protected $fillable = ['name', 'taggable_type', 'taggable_id'];

    public function taggable()
    {
        return $this->morphTo();
    }

    public function tagged()
    {
        return $this->morphedByMany(Story::class, 'taggable');
    }

    public function getTaggableTypeAttribute()
    {
        return isset($this->attributes['taggable_type'])
            ? class_basename($this->attributes['taggable_type'])
            : null;
    }

    public function getResourceUrlAttribute()
    {
        if (!$this->taggable_type || strtolower($this->taggable_type) === 'project') {
            return url("resources/tags/{$this->id}"); // Ritorna l'URL del tag se il tipo manca o è 'project'
        }

        if (!$this->taggable_id) {
            return '#'; // Ritorna un link non cliccabile se non ci sono informazioni sufficienti.
        }

        // Rimuovi il namespace e converti il nome della classe in snake_case per il path
        $baseName = class_basename($this->taggable_type);
        $resourcePath = Str::kebab(Str::plural($baseName));

        return url("resources/{$resourcePath}/{$this->taggable_id}");
    }
}
