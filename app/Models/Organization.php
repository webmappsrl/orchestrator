<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Organization extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'activity_report_language',
    ];

    /**
     * Get the users that belong to this organization.
     */
    public function users()
    {
        return $this->belongsToMany(User::class, 'organization_user');
    }
}
