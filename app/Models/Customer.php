<?php

namespace App\Models;

use App\Models\Quote;
use App\Models\Deadline;
use Exception;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Log;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Customer extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia;

    /**
     * Days threshold for contracts to be considered "expiring soon"
     */
    public const EXPIRING_SOON_DAYS = 30;

    //Casts of the model dates
    protected $casts = [
        'subscription_last_payment' => 'date',
        'contract_expiration_date' => 'date'
    ];


    protected $fillable = [
        'name',
        'description',
        'wmpm_id',
        'notes',
        'hs_id',
        'domain_name',
        'full_name',
        'acronym',
        'has_subscription',
        'subscription_amount',
        'subscription_last_payment',
        'subscription_last_covered_year',
        'subscription_last_invoice',
        'score_cash',
        'score_pain',
        'score_business',
        'contract_expiration_date',
        'contract_value',
        'status',
        'phone',
        'mobile_phone',
        'user_id',
    ];

    /**
     * Normalize phone string: Unicode spaces to normal space, strip zero-width/invisible chars.
     */
    protected static function normalizePhoneString(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return $value;
        }
        $value = preg_replace('/[\x{200B}\x{200C}\x{200D}\x{200E}\x{200F}\x{FEFF}]/u', '', $value);
        $value = preg_replace('/\p{Z}/u', ' ', $value);
        return trim(preg_replace('/\s+/', ' ', $value));
    }

    /**
     * Set the phone attribute (normalize copy-paste characters).
     */
    public function setPhoneAttribute(?string $value): void
    {
        $this->attributes['phone'] = static::normalizePhoneString($value);
    }

    /**
     * Set the mobile_phone attribute (normalize copy-paste characters).
     */
    public function setMobilePhoneAttribute(?string $value): void
    {
        $this->attributes['mobile_phone'] = static::normalizePhoneString($value);
    }

    /**
     * Owner: the user who manages this customer (Admin or Manager).
     */
    public function owner()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

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

    /**
     * Register media collections (documents: PDF only).
     *
     * @return void
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('documents')->acceptsMimeTypes(['application/pdf']);
    }

    protected static function booted()
    {
        static::created(function (Customer $entity) {
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
