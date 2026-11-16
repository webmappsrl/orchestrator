<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Throwable;

/**
 * Un'attività di una trattativa (oc:8724): chi ha fatto cosa, quando,
 * su una trattativa, sui suoi prodotti o su uno dei suoi task.
 */
class QuoteActivity extends Model
{
    public const EVENT_QUOTE_CREATED = 'quote_created';
    public const EVENT_QUOTE_UPDATED = 'quote_updated';
    public const EVENT_QUOTE_DELETED = 'quote_deleted';
    public const EVENT_PRODUCT_ADDED = 'product_added';
    public const EVENT_PRODUCT_REMOVED = 'product_removed';
    public const EVENT_RECURRING_PRODUCT_ADDED = 'recurring_product_added';
    public const EVENT_RECURRING_PRODUCT_REMOVED = 'recurring_product_removed';
    public const EVENT_TASK_CREATED = 'task_created';
    public const EVENT_TASK_UPDATED = 'task_updated';
    public const EVENT_TASK_COMPLETED = 'task_completed';
    public const EVENT_TASK_REOPENED = 'task_reopened';
    public const EVENT_TASK_NOTE_ADDED = 'task_note_added';
    public const EVENT_TASK_DELETED = 'task_deleted';

    protected $fillable = [
        'quote_id',
        'task_id',
        'user_id',
        'quote_title',
        'task_title',
        'user_name',
        'event',
        'changes',
    ];

    protected $casts = [
        'changes' => 'array',
    ];

    public function quote(): BelongsTo
    {
        return $this->belongsTo(Quote::class);
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scrive un'attività con l'utente autenticato. Non lancia mai:
     * un errore in questa scrittura non deve far fallire il salvataggio che lo ha
     * generato, quindi viene solo segnalato.
     */
    public static function record(string $event, ?Quote $quote, array $attributes = []): ?self
    {
        try {
            $user = auth()->user();

            return static::create(array_merge([
                'event' => $event,
                'quote_id' => $quote?->exists ? $quote->getKey() : null,
                'quote_title' => $quote ? static::titleOf($quote) : null,
                'user_id' => $user?->getKey(),
                'user_name' => $user?->name,
            ], $attributes));
        } catch (Throwable $e) {
            report($e);

            return null;
        }
    }

    /**
     * Aggiunta o rimozione di un prodotto (o prodotto ricorrente), chiamata
     * dagli eventi dei modelli pivot. Non lancia mai, come record().
     *
     * @param class-string<Model> $productClass
     */
    public static function recordProduct(?int $quoteId, string $productClass, ?int $productId, string $event): ?self
    {
        try {
            $quote = $quoteId ? Quote::find($quoteId) : null;
            $product = $productId ? $productClass::find($productId) : null;

            return static::record($event, $quote, [
                'changes' => [
                    'product_id' => $productId,
                    'product_name' => $product?->getTranslation('name', app()->getLocale()),
                ],
            ]);
        } catch (Throwable $e) {
            report($e);

            return null;
        }
    }

    /**
     * Titolo della trattativa nella lingua dell'app, con il fallback di
     * Translatable se manca.
     */
    public static function titleOf(Quote $quote): ?string
    {
        $title = $quote->getTranslation('title', app()->getLocale());

        return $title === '' ? null : $title;
    }
}
