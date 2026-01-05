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
     * Admin / Manager / Developer: possono visualizzare tutti i ticket
     *
     * @param  \App\Models\User  $user
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function viewAny(User $user)
    {
        return $user->hasRole(UserRole::Admin) 
            || $user->hasRole(UserRole::Manager) 
            || $user->hasRole(UserRole::Developer);
    }

    /**
     * Determine whether the user can view the model.
     * Admin / Manager / Developer: possono visualizzare tutti i ticket
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Story  $story
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function view(User $user, Story $story)
    {
        return $user->hasRole(UserRole::Admin) 
            || $user->hasRole(UserRole::Manager) 
            || $user->hasRole(UserRole::Developer);
    }

    /**
     * Determine whether the user can create models.
     * Admin / Manager / Developer: creazione di un ticket nuovo
     *
     * @param  \App\Models\User  $user
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function create(User $user)
    {
        return $user->hasRole(UserRole::Admin) 
            || $user->hasRole(UserRole::Manager) 
            || $user->hasRole(UserRole::Developer);
    }

    /**
     * Determine whether the user can update the model.
     * Admin / Manager: possono modificare tutti i ticket
     * Developer: possono modificare solo i ticket assegnati o di cui sono tester
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Story  $story
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function update(User $user, Story $story)
    {
        // Admin e Manager possono modificare tutti i ticket
        if ($user->hasRole(UserRole::Admin) || $user->hasRole(UserRole::Manager)) {
            return true;
        }

        // Developer può modificare solo i ticket assegnati o di cui è tester
        if ($user->hasRole(UserRole::Developer)) {
            return $story->user_id === $user->id || $story->tester_id === $user->id;
        }

        return false;
    }

    /**
     * Determine whether the user can delete the model.
     * Admin / Manager: possono eliminare tutti i ticket
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Story  $story
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function delete(User $user, Story $story)
    {
        return $user->hasRole(UserRole::Admin) || $user->hasRole(UserRole::Manager);
    }

    /**
     * Determine whether the user can replicate the model.
     * Admin / Manager / Developer: possono lanciare il replicate di un qualsiasi ticket
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Story  $story
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function replicate(User $user, Story $story)
    {
        return $user->hasRole(UserRole::Admin) 
            || $user->hasRole(UserRole::Manager) 
            || $user->hasRole(UserRole::Developer);
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
