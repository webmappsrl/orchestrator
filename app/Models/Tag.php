<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Tag extends Model
{
    protected $fillable = ['name', 'taggable_type', 'taggable_id', 'estimate'];

    public function taggable()
    {
        return $this->morphTo();
    }

    public function tagged()
    {
        return $this->morphedByMany(Story::class, 'taggable');
    }

    public function getSalAttribute()
    {
        // Calcola la somma delle ore dalle story associate
        $totalHours = $this->getTotalHoursAttribute(); // Usa la relazione 'tagged' per ottenere le story
        return $this->estimate ? ($totalHours / $this->estimate) * 100 : 0; // Calcola la percentuale di avanzamento
    }

    public function getTotalHoursAttribute()
    {
        return round($this->tagged()->sum('hours'), 2); // Somma delle ore delle storie associate arrotondata a due cifre
    }

    public function calculateSalPercentage($actual, $estimated)
    {
        if ($actual && $estimated) {
            return ($actual / $estimated) * 100; // Calcola la percentuale se entrambi i valori sono presenti
        }

        return $actual; // Restituisci solo il valore di actual se estimated non è presente
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
