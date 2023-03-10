<?php

namespace App\Models;

use App\Models\Quote;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class RecurringProduct extends Model
{
    use HasFactory;


    protected $fillable = [
        'name',
        'description',
        'sku',
        'price',
    ];

    //translatable fields
    public $translatable = ['name'];

    public function quotes()
    {
        return $this->belongsToMany(Quote::class)->withPivot('quantity');
    }
}
