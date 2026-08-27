<?php

namespace App\Models;

use App\Models\User;
use App\Models\Product;
use App\Models\Customer;
use App\Models\RecurringProduct;
use Spatie\MediaLibrary\HasMedia;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\InteractsWithMedia;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Spatie\Translatable\HasTranslations;

class Quote extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia, HasTranslations;

    /**
     * Italian VAT rate applied to quotes. Single source of truth for the
     * Nova detail view (app/Nova/Quote.php) and the API
     * (Api\QuoteController::formatQuote()). NOT used by
     * resources/views/quote-pdf.blade.php, which independently hardcodes
     * the same rate (pre-existing, out of scope for oc:8291) — keep both
     * in sync manually if this rate ever changes.
     */
    public const VAT_RATE = 0.22;

    protected $casts = [
        'additional_services' => 'array',
        'template' => 'bool',
        'priority' => 'int',
    ];

    protected $fillable = [

        'title',
        'name',
        'status',
        'priority',
        'additional_services',
        'customer_id',
        'google_drive_url',
        'discount',
        'notes',
        'template',
    ];

    public $translatable = [
        'title',
        'notes',
        'additional_info',
        'delivery_time',
        'payment_plan',
        'billing_plan',
        'additional_services'
    ];


    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function products()
    {
        return $this->belongsToMany(Product::class)->withPivot('quantity');
    }

    public function recurringProducts()
    {
        return $this->belongsToMany(RecurringProduct::class)->withPivot('quantity');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function tasks()
    {
        return $this->hasMany(Task::class);
    }

    /**
     * Eager-load `tasks` filtered to `todo` and ordered by nearest `due_date`
     * (past or future — see docs/features/8404-.../overview.md), so
     * `$quote->tasks->first()` resolves the "next todo task" without an
     * extra query. Shared by every Nova index query that renders the
     * "Due date" column (App\Nova\Quote and App\Nova\QuoteNoFilter).
     */
    public function scopeWithNextTodoTask($query)
    {
        return $query->with(['tasks' => function ($tasksQuery) {
            $tasksQuery->where('status', Task::STATUS_TODO)->orderBy('due_date', 'asc');
        }]);
    }

    /**
     * Get the total price of the quote.
     * @return float
     */
    public function getTotalPrice(): float
    {
        $totalPrice = 0;

        if (!$this->products) return 0; // if there are no products, return 0 (no products)

        foreach ($this->products as $product) {
            $totalPrice += $product->price * $product->pivot->quantity;
        }
        return $totalPrice;
    }

    /**
     * Get the total recurring price.
     * @return float
     */
    public function getTotalRecurringPrice(): float
    {

        $totalRecurringPrice = 0;

        if (!$this->recurringProducts) return 0; // if there are no recurring products, return 0 (no recurring products

        foreach ($this->recurringProducts as $recurringProduct) {
            $totalRecurringPrice += $recurringProduct->price * $recurringProduct->pivot->quantity;
        }
        return $totalRecurringPrice;
    }

    /**
     * Get the total of additional services price.
     * @return float
     */
    public function getTotalAdditionalServicesPrice(): float
    {
        $translations = $this->getTranslations('additional_services');
        if (empty($translations)) {
            return 0;
        }

        // Get first non-empty translation
        $services = collect($translations)->first(function ($services) {
            return !empty($services);
        });

        if (empty($services)) {
            return 0;
        }

        return collect($services)->reduce(function ($total, $price) {
            if (strpos($price, ',') !== false) {
                $price = str_replace(',', '.', $price);
            }
            return $total + (float)($price ?? 0);
        }, 0);
    }

    /**
     * Get the total price of the quote.
     * @return float
     */
    public function getQuoteNetPrice(): float
    {
        $this->discount = $this->discount ?? 0;
        return $this->getTotalPrice() + $this->getTotalRecurringPrice() + $this->getTotalAdditionalServicesPrice() - $this->discount;
    }

    /**
     * Accessor for display (e.g. Kanban card): total price formatted.
     */
    public function getTotalAttribute(): string
    {
        $total = $this->getTotalPrice() + $this->getTotalRecurringPrice() + $this->getTotalAdditionalServicesPrice();

        return number_format($total, 2, ',', '.') . ' €';
    }
    
    /**
     * Accessor for display (e.g. Kanban card): net total price formatted.
     */
    public function getNetTotalAttribute(): string
    {
        $total = $this->getQuoteNetPrice();

        return number_format($total, 2, ',', '.') . ' €';
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('documents');
    }

    /**
     * Strip out any locale whose `additional_services` translation is an
     * empty array. Spatie\Translatable's fallback (`getTranslatedLocales()`)
     * considers a locale "translated" if its key exists in the underlying
     * JSON, even when the value is empty — without this, an empty-array
     * locale never falls back to a populated one.
     *
     * The in-memory normalization always runs (so rendering is correct
     * regardless of the caller). Only the DB write is gated by `$persist`,
     * so read-only callers (e.g. the anonymous public PDF link) get correct
     * rendering without writing to the database.
     */
    public function clearEmptyAdditionalServicesTranslations(bool $persist = true): void
    {
        if (empty($this->getTranslations('additional_services'))) {
            return;
        }

        $filtered = collect($this->getTranslations('additional_services'))
            ->filter(function ($translation) {
                return !empty($translation);
            })
            ->toArray();

        if (empty($filtered)) {
            $this->replaceTranslations('additional_services', []);
        } else {
            $this->replaceTranslations('additional_services', $filtered);
        }

        if ($persist) {
            $this->save();
        }
    }

    protected static function booted()
    {
        static::saving(function (Quote $quote) {
            if (!$quote->template) {
                return;
            }
            if (!$quote->customer_id) {
                return;
            }

            static::query()
                ->where('customer_id', $quote->customer_id)
                ->where('template', true)
                ->whereKeyNot($quote->getKey())
                ->update(['template' => false]);
        });
    }
}
