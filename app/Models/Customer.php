<?php

namespace App\Models;

use App\Models\Quote;
use App\Models\Deadline;
use Exception;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Log;

class Customer extends Model
{
    use HasFactory;

    //Casts of the model dates
    protected $casts = [
        'subscription_last_payment' => 'date'
    ];


    protected $fillable = [
        'name',
        'description',
        'wmpm_id',
        'notes',
        'hs_id',
        'domain_name',
        'full_name',
        'has_subscription',
        'subscription_amount',
        'subscription_last_payment',
        'subscription_last_covered_year',
        'subscription_last_invoice',
        'score',
        'score_cash',
        'score_pain',
        'score_business'

    ];

    public function projects()
    {
        return $this->hasMany(Project::class);
    }

    public function quotes()
    {
        return $this->hasMany(Quote::class);
    }

    public function deadlines(): HasMany
    {
        return $this->hasMany(Deadline::class);
    }
    /**
     * Get all the tags for the project.
     */
    public function tags()
    {
        return $this->morphMany(Tag::class, 'taggable');
    }

    protected static function booted()
    {
        static::created(function (Project $entity) {
            try {
                $tag = Tag::firstOrCreate([
                    'name' => class_basename($entity) . ': ' . $entity->name,
                    'taggable_id' => $entity->id,
                    'taggable_type' => get_class($entity)
                ]);
                if ($tag && $entity) {
                    $entity->tags()->saveQuietly($tag);
                }
            } catch (Exception $e) {
                // Logga l'errore con maggiori dettagli
                Log::error('Error saving tags: ' . $e->getMessage(), [
                    'entity' => $entity,
                    'tag' => isset($tag) ? $tag : null,
                ]);
            }
        });
    }
}
