<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;

use App\Enums\UserRole;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Support\Facades\Gate;
use Laravel\Nova\Auth\Impersonatable;
use Illuminate\Notifications\Notifiable;
use Wm\WmPackage\Model\User as Authenticatable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Casts\AsEnumCollection;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Impersonatable, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'roles'
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'roles' => AsEnumCollection::class . ':' . UserRole::class,
    ];

    /**
     * Check if user has a specific single role
     *
     * @param UserRole $role
     * @return boolean
     */
    public function hasRole(UserRole $role): bool
    {
        return $this->roles->contains($role);
    }

    public function stories()
    {
        return $this->hasMany(Story::class);
    }

    public function epics()
    {
        return $this->hasMany(Epic::class);
    }

    public function projects()
    {
        return $this->belongsToMany(Project::class);
    }


    /**
     * Determine if the user can impersonate another user.
     *
     * @return bool
     */
    public function canImpersonate()
    {
        return $this->hasRole(UserRole::Admin);
    }


    /**
     * Determine if the user can be impersonated.
     *
     * @return bool
     */
    public function canBeImpersonated()
    {
        return true;
    }
}
