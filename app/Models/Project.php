<?php

namespace App\Models;

use App\Models\Epic;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Project extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'customer_id',
        'wmpm_id'
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function epics()
    {
        return $this->hasMany(Epic::class);
    }
}
