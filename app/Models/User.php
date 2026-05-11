<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;

use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Casts\AsEnumCollection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Notifications\Notifiable;
use Laravel\Nova\Auth\Impersonatable;
use Laravel\Sanctum\HasApiTokens;
use Wm\WmPackage\Models\User as Authenticatable;

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

    public function hasRole($roles, ?string $guard = null): bool
    {
        if ($roles instanceof UserRole) {
            return $this->roles->contains($roles);
        }
        return parent::hasRole($roles, $guard);
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
     * Customers owned by this user (user manages the customer).
     */
    public function ownedCustomers()
    {
        return $this->hasMany(Customer::class, 'user_id');
    }

    /**
     * Customer this user is associated with (when user has Customer role).
     */
    public function associatedCustomer()
    {
        return $this->hasOne(Customer::class, 'associated_user_id');
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

    public function authorizedApps(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(App::class, 'user_app', 'user_id', 'app_id');
    }

    public function favorite($object): void
    {
        if (! $this->hasFavorited($object)) {
            \Illuminate\Support\Facades\DB::table('favorites')->insert([
                'user_id'           => $this->getKey(),
                'favoriteable_id'   => $object->getKey(),
                'favoriteable_type' => $object->getMorphClass(),
                'created_at'        => now(),
                'updated_at'        => now(),
            ]);
        }
    }

    public function unfavorite($object): void
    {
        \Illuminate\Support\Facades\DB::table('favorites')
            ->where('user_id', $this->getKey())
            ->where('favoriteable_id', $object->getKey())
            ->where('favoriteable_type', $object->getMorphClass())
            ->delete();
    }

    public function hasFavorited($object): bool
    {
        return \Illuminate\Support\Facades\DB::table('favorites')
            ->where('user_id', $this->getKey())
            ->where('favoriteable_id', $object->getKey())
            ->where('favoriteable_type', $object->getMorphClass())
            ->exists();
    }

    public function getFavoriteItems(string $class): \Illuminate\Database\Eloquent\Builder
    {
        $favoritedIds = \Illuminate\Support\Facades\DB::table('favorites')
            ->where('user_id', $this->getKey())
            ->where('favoriteable_type', (new $class)->getMorphClass())
            ->pluck('favoriteable_id');

        return $class::whereIn((new $class)->getKeyName(), $favoritedIds);
    }
}
