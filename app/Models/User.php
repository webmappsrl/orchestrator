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
        'activity_report_language',
        'google_drive_url',
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
            return '/dashboards/customer-dashboard';
        } elseif ($this->hasRole(UserRole::Fundraising)) {
            return '/resources/fundraising-opportunities';
        } else {
            return '/dashboards/kanban';
        }
    }

    public function apps()
    {
        return $this->belongsToMany(App::class, 'user_app', 'user_id', 'app_id');
    }

    /**
     * Get the fundraising opportunities created by this user.
     */
    public function createdFundraisingOpportunities()
    {
        return $this->hasMany(FundraisingOpportunity::class, 'created_by');
    }

    /**
     * Get the fundraising opportunities this user is responsible for.
     */
    public function responsibleFundraisingOpportunities()
    {
        return $this->hasMany(FundraisingOpportunity::class, 'responsible_user_id');
    }

    /**
     * Get the fundraising projects created by this user.
     */
    public function createdFundraisingProjects()
    {
        return $this->hasMany(FundraisingProject::class, 'created_by');
    }

    /**
     * Get the fundraising projects this user is responsible for.
     */
    public function responsibleFundraisingProjects()
    {
        return $this->hasMany(FundraisingProject::class, 'responsible_user_id');
    }

    /**
     * Get the fundraising projects where this user is the lead.
     */
    public function leadFundraisingProjects()
    {
        return $this->hasMany(FundraisingProject::class, 'lead_user_id');
    }

    /**
     * Get the fundraising projects where this user is a partner.
     */
    public function partnerFundraisingProjects()
    {
        return $this->belongsToMany(FundraisingProject::class, 'fundraising_project_partners', 'user_id', 'fundraising_project_id');
    }

    /**
     * Get all fundraising projects where this user is involved (lead or partner).
     */
    public function involvedFundraisingProjects()
    {
        return FundraisingProject::where(function ($query) {
            $query->where('lead_user_id', $this->id)
                  ->orWhereHas('partners', function ($subQuery) {
                      $subQuery->where('user_id', $this->id);
                  });
        });
    }

    /**
     * Check if user has fundraising role.
     */
    public function isFundraising(): bool
    {
        return $this->hasRole(UserRole::Fundraising);
    }

    /**
     * Check if user has customer role.
     */
    public function isCustomer(): bool
    {
        return $this->hasRole(UserRole::Customer);
    }

    /**
     * Get the organizations that this user belongs to.
     */
    public function organizations()
    {
        return $this->belongsToMany(Organization::class, 'organization_user');
    }
}
