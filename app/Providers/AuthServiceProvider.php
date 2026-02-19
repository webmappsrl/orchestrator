<?php

namespace App\Providers;

use App\Enums\UserRole;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        // 'App\Models\Model' => 'App\Policies\ModelPolicy',
    ];

    /**
     * Register any authentication / authorization services.
     *
     * @return void
     */
    public function boot()
    {
        $this->registerPolicies();

        // Ability per tipi utente da escludere dalla modifica stato Kanban (deniedToUpdateStatus(['manager', 'customer']))
        Gate::define('customer', function ($user) {
            return $user->hasRole(UserRole::Customer);
        });
        Gate::define('manager', function ($user) {
            return $user->hasRole(UserRole::Manager);
        });
        Gate::define('editor', function ($user) {
            return $user->hasRole(UserRole::Editor);
        });
        Gate::define('admin', function ($user) {
            return $user->hasRole(UserRole::Admin);
        });
        Gate::define('developer', function ($user) {
            return $user->hasRole(UserRole::Developer);
        });
    }
}
