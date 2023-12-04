<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Story;
use App\Enums\UserRole;
use Illuminate\Auth\Access\HandlesAuthorization;

class StoryPolicy
{
    use HandlesAuthorization;


    /**
     * Determine whether the user can view any models.
     *
     * @param  \App\Models\User  $user
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function viewAny(User $user)
    {
        return true;
    }

    /**
     * Determine whether the user can view the model.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Story  $story
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function view(User $user, Story $story)
    {
        return true;
    }

    /**
     * Determine whether the user can create models.
     *
     * @param  \App\Models\User  $user
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function create(User $user)
    {
        return true;
    }

    /**
     * Determine whether the user can update the model.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Story  $story
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function update(User $user, Story $story)
    {
        return $user->hasRole(UserRole::Admin) || $user->hasRole(UserRole::Developer);
    }

    /**
     * Determine whether the user can delete the model.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Story  $story
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function delete(User $user, Story $story)
    {
        return $user->hasRole(UserRole::Admin) || $user->hasRole(UserRole::Developer);
    }

    /**
     * Determine whether the user can restore the model.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Story  $story
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function restore(User $user, Story $story)
    {
        return $user->hasRole(UserRole::Admin) || $user->hasRole(UserRole::Developer);
    }

    /**
     * Determine whether the user can permanently delete the model.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Story  $story
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function forceDelete(User $user, Story $story)
    {
        return $user->hasRole(UserRole::Admin) || $user->hasRole(UserRole::Developer);
    }
}