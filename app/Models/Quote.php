<?php

namespace App\Models;

use App\Models\Product;
use App\Models\Customer;
use App\Models\RecurringProduct;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Quote extends Model
{
    use HasFactory;

    protected $casts = [
        'additional_services' => 'array',
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

    /**
     * Get the total price of the quote.
     * @return float
     */
    public function getTotalPrice(): float
    {
        $totalPrice = 0;

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

        if (!$this->additional_services) return 0;

        foreach ($this->additional_services as $additionalService) {
            $totalAdditionalServicesPrice += $additionalService['price'] ?? 0;
        }
        return $totalAdditionalServicesPrice;
    }
}