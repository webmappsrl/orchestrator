<?php

namespace App\Policies;

use App\Models\User;
use App\Enums\UserRole;
use App\Models\Project;
use Illuminate\Auth\Access\HandlesAuthorization;

class ProjectPolicy
{
    use HandlesAuthorization;

    // public function before(User $user)
    // {
    //     return $user->hasRole(UserRole::Admin) || $user->hasRole(UserRole::Manager) || $user->hasRole(UserRole::Developer);
    // }

    /**
     * Determine whether the user can view any models.
     *
     * @param  \App\Models\User  $user
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function viewAny(User $user)
    {
        return
            true;
    }

    /**
     * Determine whether the user can view the model.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Project  $project
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function view(User $user, Project $project)
    {
        if ($user->hasRole(UserRole::Customer)) {
            return $user->id === $project->user_id;
        }
        return
            $user->hasRole(UserRole::Admin) || $user->hasRole(UserRole::Manager) || $user->hasRole(UserRole::Developer);
    }

    /**
     * Determine whether the user can create models.
     *
     * @param  \App\Models\User  $user
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function create(User $user)
    {
        return   $user->hasRole(UserRole::Admin) || $user->hasRole(UserRole::Manager) || $user->hasRole(UserRole::Developer);
    }

    /**
     * Determine whether the user can update the model.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Project  $project
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function update(User $user, Project $project)
    {
        return
            $user->hasRole(UserRole::Admin) || $user->hasRole(UserRole::Manager) || $user->hasRole(UserRole::Developer) || $user->id === $project->user_id;
    }

    /**
     * Determine whether the user can delete the model.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Project  $project
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function delete(User $user, Project $project)
    {
        return
            $user->hasRole(UserRole::Admin) || $user->hasRole(UserRole::Manager) || $user->hasRole(UserRole::Developer);
    }

    /**
     * Determine whether the user can restore the model.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Project  $project
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function restore(User $user, Project $project)
    {
        return
            $user->hasRole(UserRole::Admin) || $user->hasRole(UserRole::Manager) || $user->hasRole(UserRole::Developer);
    }

    /**
     * Determine whether the user can permanently delete the model.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Project  $project
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function forceDelete(User $user, Project $project)
    {
        return
            $user->hasRole(UserRole::Admin) || $user->hasRole(UserRole::Manager) || $user->hasRole(UserRole::Developer);
    }
}
