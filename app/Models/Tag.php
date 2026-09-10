<?php

namespace App\Models;

use App\Enums\StoryStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Tag extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'description', 'taggable_type', 'taggable_id', 'type'];

    public function taggable()
    {
        return $this->morphTo();
    }

    /**
     * Estimate del tag = somma dei valori `estimated_hours` delle stories taggate.
     * Non è più una colonna persistita: il valore viene sempre calcolato runtime.
     */
    public function getEstimateAttribute(): ?float
    {
        $sum = $this->tagged()->sum('estimated_hours');

        return $sum > 0 ? (float) $sum : null;
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

    protected ?float $totalHoursMemo = null;
    protected bool $totalHoursComputed = false;

    /**
     * Somma di `hours` sull'unione deduplicata (story taggate ∪ loro figli diretti).
     * Query dei figli su `parent_id`, non su childStories() — vedi oc:8421 overview §Decisioni.
     * Memoizzato per istanza: il valore e letto piu volte per riga in Nova\Tag/Nova\TagGroup ("SAL t").
     */
    public function getTotalHoursAttribute()
    {
        if ($this->totalHoursComputed) {
            return $this->totalHoursMemo;
        }
        $this->totalHoursComputed = true;

        $taggedIds = $this->tagged()->pluck((new Story)->getTable() . '.id');
        if ($taggedIds->isEmpty()) {
            return $this->totalHoursMemo = null;
        }

        $allIds = Story::idsWithChildren($taggedIds);

        return $this->totalHoursMemo = round(Story::whereIn('id', $allIds)->sum('hours'), 2);
    }

    public function calculateSalPercentage()
    {
        $actual = $this->getTotalHoursAttribute();
        $estimated = $this->estimate;

        if ($actual && $estimated) {
            return round(($actual / $estimated) * 100, 2); // Calcola la percentuale se entrambi i valori sono presenti
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
        if (! $this->taggable_type || strtolower($this->taggable_type) === 'project') {
            return url("resources/tags/{$this->id}"); // Ritorna l'URL del tag se il tipo manca o è 'project'
        }

        if (! $this->taggable_id) {
            return '#'; // Ritorna un link non cliccabile se non ci sono informazioni sufficienti.
        }

        // Rimuovi il namespace e converti il nome della classe in snake_case per il path
        $baseName = class_basename($this->taggable_type);
        $resourcePath = Str::kebab(Str::plural($baseName));

        return url("resources/{$resourcePath}/{$this->taggable_id}");
    }

    /**
     * Story statuses treated as "closed" for SAL # and Sal t.
     */
    public static function salClosedStoryStatusValues(): array
    {
        return [
            StoryStatus::PendingRelease->value,
            StoryStatus::Released->value,
            StoryStatus::Done->value,
            StoryStatus::Rejected->value,
        ];
    }

    public function salTicketCounts(): array
    {
        $statuses = self::salClosedStoryStatusValues();

        return [
            (int) $this->tagged()->whereIn('status', $statuses)->count(),
            (int) $this->tagged()->count(),
        ];
    }

    public function isClosed()
    {
        return ! $this->tagged()->whereNotIn('status', self::salClosedStoryStatusValues())->exists();
    }
}
