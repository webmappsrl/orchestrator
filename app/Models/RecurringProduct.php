<?php

namespace App\Models;

use App\Models\Quote;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Spatie\Translatable\HasTranslations;

class RecurringProduct extends Model
{
    use HasFactory, HasTranslations;


    protected $fillable = [
        'name',
        'description',
        'sku',
        'price',
    ];
    public $translatable = ['name', 'description'];

    public function quotes()
    {
        return $this->belongsToMany(Quote::class)->withPivot('quantity');
    }
}
