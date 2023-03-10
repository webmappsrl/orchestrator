<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Milestone;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class MilestonePolicy
{
    use HandlesAuthorization;


    //To be implemented: authorization based on user role (Admin, Dev)->(Pedram)

    /**
     * Determine whether the user can view any models.
     *
     * @param  \App\Models\User  $user
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function viewAny(User $user)
    {
        return $user->hasRole(UserRole::Admin) || $user->hasRole(UserRole::Developer);
    }

    /**
     * Determine whether the user can view the model.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Milestone  $milestone
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function view(User $user, Milestone $milestone)
    {
        return $user->hasRole(UserRole::Admin) || $user->hasRole(UserRole::Developer);
    }

    /**
     * Determine whether the user can create models.
     *
     * @param  \App\Models\User  $user
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function create(User $user)
    {
        return $user->hasRole(UserRole::Admin) || $user->hasRole(UserRole::Developer);
    }

    /**
     * Determine whether the user can update the model.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Milestone  $milestone
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function update(User $user, Milestone $milestone)
    {
        return $user->hasRole(UserRole::Admin) || $user->hasRole(UserRole::Developer);
    }

    /**
     * Determine whether the user can delete the model.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Milestone  $milestone
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function delete(User $user, Milestone $milestone)
    {
        return $user->hasRole(UserRole::Admin) || $user->hasRole(UserRole::Developer);
    }

    /**
     * Determine whether the user can restore the model.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Milestone  $milestone
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function restore(User $user, Milestone $milestone)
    {
        //
    }

    /**
     * Determine whether the user can permanently delete the model.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Milestone  $milestone
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function forceDelete(User $user, Milestone $milestone)
    {
        //
    }
}
