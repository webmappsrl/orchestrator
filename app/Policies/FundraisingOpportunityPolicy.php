<?php

namespace App\Policies;

use App\Models\FundraisingOpportunity;
use App\Models\User;
use App\Enums\UserRole;
use Illuminate\Auth\Access\Response;

class FundraisingOpportunityPolicy
{
    /**
     * Determine whether the user can view any models.
     * Tutti gli utenti possono vedere le opportunità di finanziamento.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the model.
     * Tutti gli utenti possono vedere i dettagli delle opportunità.
     */
    public function view(User $user, FundraisingOpportunity $fundraisingOpportunity): bool
    {
        return true;
    }

    /**
     * Determine whether the user can create models.
     * Solo utenti con ruolo fundraising o admin possono creare opportunità.
     */
    public function create(User $user): bool
    {
        return $user->hasRole(UserRole::Fundraising) || $user->hasRole(UserRole::Admin);
    }

    /**
     * Determine whether the user can update the model.
     * Solo il creatore, il responsabile, o admin possono modificare.
     */
    public function update(User $user, FundraisingOpportunity $fundraisingOpportunity): bool
    {
        return $user->hasRole(UserRole::Admin) ||
               $user->id === $fundraisingOpportunity->created_by ||
               $user->id === $fundraisingOpportunity->responsible_user_id ||
               $user->hasRole(UserRole::Fundraising);
    }

    /**
     * Determine whether the user can delete the model.
     * Solo admin o il creatore possono eliminare.
     */
    public function delete(User $user, FundraisingOpportunity $fundraisingOpportunity): bool
    {
        return $user->hasRole(UserRole::Admin) ||
               $user->id === $fundraisingOpportunity->created_by;
    }

    /**
     * Determine whether the user can restore the model.
     * Solo admin possono ripristinare.
     */
    public function restore(User $user, FundraisingOpportunity $fundraisingOpportunity): bool
    {
        return $user->hasRole(UserRole::Admin);
    }

    /**
     * Determine whether the user can permanently delete the model.
     * Solo admin possono eliminare definitivamente.
     */
    public function forceDelete(User $user, FundraisingOpportunity $fundraisingOpportunity): bool
    {
        return $user->hasRole(UserRole::Admin);
    }
}
