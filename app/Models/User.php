<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;

use App\Models\Quote;
use App\Enums\UserRole;
use App\Models\Project;
use Laravel\Sanctum\HasApiTokens;
use Laravel\Nova\Auth\Impersonatable;
use Illuminate\Notifications\Notifiable;
use Overtrue\LaravelFavorite\Traits\Favoriter;
use Wm\WmPackage\Models\User as Authenticatable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Casts\AsEnumCollection;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Impersonatable, Notifiable, Favoriter;

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
     * @return boolean
     */
    public function wantsBreadcrumbs(): bool
    {
        return !$this->hasRole(UserRole::Customer);
    }

    /**
     * Define the initial nova path for the logged user
     * @return string
     */
    public function initialPath(): string
    {
        if ($this->hasRole(UserRole::Customer)) {
            return '/resources/story-showed-by-customers';
        } else {
            return '/dashboard/main';
        }
    }

    public function apps()
    {
        return $this->belongsToMany(App::class, 'user_app', 'user_id', 'app_id');
    }
}
