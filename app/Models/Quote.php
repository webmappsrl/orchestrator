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
}
