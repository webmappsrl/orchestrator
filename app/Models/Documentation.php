<?php

namespace App\Models;

use App\Enums\DocumentationCategory;
use App\Models\Tag;
use Exception;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Documentation extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia;

    protected $fillable = [
        'name',
        'creator_id',
        'category',
        'pdf_url'
    ];

    protected $casts = [
        'category' => DocumentationCategory::class,
    ];

    public function tags()
    {
        return $this->morphToMany(Tag::class, 'taggable');
    }


    public function creator()
    {
        return $this->belongsTo(User::class, 'creator_id');
    }


    /**
     * Register a spatie media collection
     * @return void
     * @link https://spatie.be/docs/laravel-medialibrary/v9/working-with-media-collections/defining-media-collections
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('documents')->acceptsMimeTypes(config('services.media-library.allowed_document_formats'));
        $this->addMediaCollection('images')->acceptsMimeTypes(config('services.media-library.allowed_image_formats'));
    }

    protected static function booted()
    {
        static::created(function (Documentation $entity) {
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

        static::updating(function (Documentation $entity) {
            try {
                $oldName = $entity->getOriginal('name');
                $newName = $entity->name;

                $entity->tags->each(function ($tag) use ($oldName, $newName, $entity) {
                    if ($tag->name === class_basename($entity) . ': ' . $oldName) {
                        $tag->update(['name' => class_basename($entity) . ': ' . $newName]);
                    }
                });
            } catch (Exception $e) {
                // Logga l'errore con maggiori dettagli
                Log::error('Errore nell\'aggiornamento dei tag: ' . $e->getMessage(), [
                    'entity' => $entity,
                    'tags' => $entity->tags,
                ]);
            }
        });
    }
}
