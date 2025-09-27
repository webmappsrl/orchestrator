<?php

namespace App\Policies;

use App\Models\FundraisingOpportunity;
use App\Models\User;
use App\Enums\UserRole;
use Illuminate\Auth\Access\Response;

class CustomerFundraisingOpportunityPolicy
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
     */
    public function view(User $user, FundraisingOpportunity $fundraisingOpportunity): bool
    {
        // Solo utenti customer possono vedere questa risorsa
        return $user->hasRole(UserRole::Customer);
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
    public function update(User $user, FundraisingOpportunity $fundraisingOpportunity): bool
    {
        // I customer non possono modificare, solo visualizzare
        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, FundraisingOpportunity $fundraisingOpportunity): bool
    {
        // I customer non possono eliminare
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, FundraisingOpportunity $fundraisingOpportunity): bool
    {
        // I customer non possono ripristinare
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, FundraisingOpportunity $fundraisingOpportunity): bool
    {
        // I customer non possono eliminare definitivamente
        return false;
    }
}
