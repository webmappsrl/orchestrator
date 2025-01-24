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

    protected $casts = [
        'additional_services' => 'array',
    ];

    protected $fillable = [

        'title',
        'name',
        'status',
        'additional_services',
        'customer_id',
        'google_drive_url',
        'discount',
        'notes'
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
        $totalAdditionalServicesPrice = 0;

        if (empty($this->additional_services)) return $totalAdditionalServicesPrice;

        foreach ($this->additional_services as $description => $price) {
            //format $price to float
            if (strpos($price, ',') !== false) {
                $price = str_replace(',', '.', $price);
            }


            $totalAdditionalServicesPrice += $price ?? 0;
        }
        return $totalAdditionalServicesPrice;
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

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('documents');
    }

    public function clearEmptyAdditionalServicesTranslations(): void
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

        $this->save();
    }
}
