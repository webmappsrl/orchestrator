<?php

namespace App\Policies;

use App\Models\FundraisingProject;
use App\Models\User;
use App\Enums\UserRole;
use Illuminate\Auth\Access\Response;

class CustomerFundraisingProjectPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        // Solo utenti customer possono vedere questa risorsa
        return $user->hasRole(UserRole::Customer);
    }

    /**
     * Determine whether the user can view the model.
     * Il customer può vedere solo i progetti dove è coinvolto come capofila o partner
     */
    public function view(User $user, FundraisingProject $fundraisingProject): bool
    {
        // Solo utenti customer possono vedere questa risorsa
        if (!$user->hasRole(UserRole::Customer)) {
            return false;
        }

        // Il customer può vedere solo i progetti dove è coinvolto
        return $fundraisingProject->isUserInvolved($user->id);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        // I customer non possono creare, solo visualizzare
        return false;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, FundraisingProject $fundraisingProject): bool
    {
        // I customer non possono modificare, solo visualizzare
        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, FundraisingProject $fundraisingProject): bool
    {
        // I customer non possono eliminare
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, FundraisingProject $fundraisingProject): bool
    {
        // I customer non possono ripristinare
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, FundraisingProject $fundraisingProject): bool
    {
        // I customer non possono eliminare definitivamente
        return false;
    }
}
