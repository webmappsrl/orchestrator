<?php


namespace App\Policies;

use App\Models\User;
use App\Enums\UserRole;
use Illuminate\Auth\Access\HandlesAuthorization;

class UserPolicy
{
    use HandlesAuthorization;

    public function before(User $user, $ability, $arguments = [])
    {
        // Se l'utente è admin, permettere tutto
        if ($user->hasRole(UserRole::Admin)) {
            return true;
        }
        
        // Se l'utente è fundraising, permettere solo le operazioni sui partner
        if ($user->hasRole(UserRole::Fundraising)) {
            // Permettere tutte le operazioni sui partner
            if (str_contains($ability, 'Partner') || str_contains($ability, 'partner')) {
                return true;
            }
            // Permettere anche le operazioni di attach/detach generiche
            if (str_contains($ability, 'attach') || str_contains($ability, 'detach')) {
                return true;
            }
            // Permettere anche le operazioni di view per i partner
            if (str_contains($ability, 'view')) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Determine whether the user can view any models.
     *
     * @param  \App\Models\User  $user
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function viewAny(User $user)
    {
        return $user->hasRole(UserRole::Admin);
    }

    /**
     * Determine whether the user can view the model.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\User  $model
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function view(User $user, User $model)
    {
        return $user->hasRole(UserRole::Admin);
    }

    /**
     * Determine whether the user can attach any partners to fundraising projects.
     * Questo permette agli utenti fundraising di vedere gli utenti solo quando aggiungono partner.
     */
    public function attachAnyPartner(User $user, $model)
    {
        // Se il modello è un FundraisingProject, permettere agli utenti fundraising
        if ($model instanceof \App\Models\FundraisingProject) {
            return $user->hasRole(UserRole::Fundraising) || $user->hasRole(UserRole::Admin);
        }
        
        return $user->hasRole(UserRole::Admin);
    }

    /**
     * Determine whether the user can attach a specific partner to fundraising projects.
     */
    public function attachPartner(User $user, $model, User $partner)
    {
        // Se il modello è un FundraisingProject, permettere agli utenti fundraising
        if ($model instanceof \App\Models\FundraisingProject) {
            return $user->hasRole(UserRole::Fundraising) || $user->hasRole(UserRole::Admin);
        }
        
        return $user->hasRole(UserRole::Admin);
    }

    /**
     * Determine whether the user can detach any partners from fundraising projects.
     */
    public function detachAnyPartner(User $user, $model)
    {
        // Se il modello è un FundraisingProject, permettere agli utenti fundraising
        if ($model instanceof \App\Models\FundraisingProject) {
            return $user->hasRole(UserRole::Fundraising) || $user->hasRole(UserRole::Admin);
        }
        
        return $user->hasRole(UserRole::Admin);
    }

    /**
     * Determine whether the user can detach a specific partner from fundraising projects.
     */
    public function detachPartner(User $user, $model, User $partner)
    {
        // Se il modello è un FundraisingProject, permettere agli utenti fundraising
        if ($model instanceof \App\Models\FundraisingProject) {
            return $user->hasRole(UserRole::Fundraising) || $user->hasRole(UserRole::Admin);
        }
        
        return $user->hasRole(UserRole::Admin);
    }


    /**
     * Determine whether the user can create models.
     *
     * @param  \App\Models\User  $user
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function create(User $user)
    {
        //
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
        //
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
        //
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
        //
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
        //
    }
}
