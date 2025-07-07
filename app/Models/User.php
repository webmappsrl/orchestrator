<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;

use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Casts\AsEnumCollection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Notifications\Notifiable;
use Laravel\Nova\Auth\Impersonatable;
use Laravel\Sanctum\HasApiTokens;
use Overtrue\LaravelFavorite\Traits\Favoriter;
use Wm\WmPackage\Model\User as Authenticatable;

class User extends Authenticatable
{
    use Favoriter, HasApiTokens, HasFactory, Impersonatable, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'roles',
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
        'roles' => AsEnumCollection::class.':'.UserRole::class,
    ];

    /**
     * Check if user has a specific single role
     */
    public function hasRole(UserRole $role): bool
    {
        return $this->roles->contains($role);
    }

    public function stories()
    {
        return $this->hasMany(Story::class);
    }

    public function participatingStories()
    {
        return $this->belongsToMany(Story::class, 'story_participants');
    }

    public function customerStories()
    {
        return $this->hasMany(Story::class, 'creator_id');
    }

    public function testerStories()
    {
        return $this->hasMany(Story::class, 'tester_id');
    }

    public function epics()
    {
        return $this->hasMany(Epic::class);
    }

    public function quotes()
    {
        return $this->hasMany(Quote::class);
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

    /**
     * define if user has breadcrumbs
     */
    public function wantsBreadcrumbs(): bool
    {
        return ! $this->hasRole(UserRole::Customer);
    }

    /**
     * Define the initial nova path for the logged user
     */
    public function initialPath(): string
    {
        if ($this->hasRole(UserRole::Customer)) {
            return '/resources/story-showed-by-customers';
        } else {
            return '/dashboards/kanban';
        }
    }

    public function apps()
    {
        return $this->belongsToMany(App::class, 'user_app', 'user_id', 'app_id');
    }
}
