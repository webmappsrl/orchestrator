<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * Pivot Quote ↔ Product. Esiste per far passare attach/detach/sync da un
 * modello che lancia eventi, così `QuoteActivity` (oc:8724) registra aggiunta e
 * rimozione dei prodotti da ogni punto di scrittura.
 */
class ProductQuote extends Pivot
{
    protected $table = 'product_quote';

    public $incrementing = false;

    public $timestamps = false;

    protected static function booted(): void
    {
        static::created(fn (ProductQuote $pivot) => QuoteActivity::recordProduct($pivot->quote_id, Product::class, $pivot->product_id, QuoteActivity::EVENT_PRODUCT_ADDED));
        static::deleted(fn (ProductQuote $pivot) => QuoteActivity::recordProduct($pivot->quote_id, Product::class, $pivot->product_id, QuoteActivity::EVENT_PRODUCT_REMOVED));
    }
}
