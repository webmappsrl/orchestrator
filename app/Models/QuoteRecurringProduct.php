<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * Pivot Quote ↔ RecurringProduct, con lo stesso scopo di ProductQuote:
 * registrare fra le attività (oc:8724) aggiunta e rimozione dei prodotti ricorrenti.
 */
class QuoteRecurringProduct extends Pivot
{
    protected $table = 'quote_recurring_product';

    public $incrementing = false;

    public $timestamps = false;

    protected static function booted(): void
    {
        static::created(fn (QuoteRecurringProduct $pivot) => QuoteActivity::recordProduct($pivot->quote_id, RecurringProduct::class, $pivot->recurring_product_id, QuoteActivity::EVENT_RECURRING_PRODUCT_ADDED));
        static::deleted(fn (QuoteRecurringProduct $pivot) => QuoteActivity::recordProduct($pivot->quote_id, RecurringProduct::class, $pivot->recurring_product_id, QuoteActivity::EVENT_RECURRING_PRODUCT_REMOVED));
    }
}
