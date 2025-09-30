<?php

namespace App\Policies;

use App\Models\FundraisingProject;
use App\Models\User;
use App\Enums\UserRole;
use Illuminate\Auth\Access\Response;

class FundraisingProjectPolicy
{
    /**
     * Determine whether the user can view any models.
     * Solo utenti con ruolo fundraising o admin possono vedere la risorsa originale.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasRole(UserRole::Fundraising) || $user->hasRole(UserRole::Admin);
    }

    /**
     * Determine whether the user can view the model.
     * Solo utenti con ruolo fundraising o admin possono vedere i dettagli.
     */
    public function view(User $user, FundraisingProject $fundraisingProject): bool
    {
        return $user->hasRole(UserRole::Fundraising) || $user->hasRole(UserRole::Admin);
    }

    /**
     * Determine whether the user can create models.
     * Solo utenti con ruolo fundraising o admin possono creare progetti.
     */
    public function create(User $user): bool
    {
        return $user->hasRole(UserRole::Fundraising) || $user->hasRole(UserRole::Admin);
    }

    /**
     * Determine whether the user can update the model.
     * Solo il creatore, il responsabile, il capofila, o admin possono modificare.
     */
    public function update(User $user, FundraisingProject $fundraisingProject): bool
    {
        return $user->hasRole(UserRole::Admin) ||
               $user->id === $fundraisingProject->created_by ||
               $user->id === $fundraisingProject->responsible_user_id ||
               $user->id === $fundraisingProject->lead_user_id ||
               $user->hasRole(UserRole::Fundraising);
    }

    /**
     * Determine whether the user can delete the model.
     * Solo admin o il creatore possono eliminare.
     */
    public function delete(User $user, FundraisingProject $fundraisingProject): bool
    {
        return $user->hasRole(UserRole::Admin) ||
               $user->id === $fundraisingProject->created_by;
    }

    /**
     * Determine whether the user can restore the model.
     * Solo admin possono ripristinare.
     */
    public function restore(User $user, FundraisingProject $fundraisingProject): bool
    {
        return $user->hasRole(UserRole::Admin);
    }

    /**
     * Determine whether the user can permanently delete the model.
     * Solo admin possono eliminare definitivamente.
     */
    public function forceDelete(User $user, FundraisingProject $fundraisingProject): bool
    {
        return $user->hasRole(UserRole::Admin);
    }

    /**
     * Determine whether the user can attach any partners to the model.
     * Solo utenti con ruolo fundraising o admin possono aggiungere partner.
     */
    public function attachAnyPartner(User $user, FundraisingProject $fundraisingProject): bool
    {
        return $user->hasRole(UserRole::Fundraising) || $user->hasRole(UserRole::Admin);
    }

    /**
     * Determine whether the user can attach a specific partner to the model.
     * Solo utenti con ruolo fundraising o admin possono aggiungere partner specifici.
     */
    public function attachPartner(User $user, FundraisingProject $fundraisingProject, User $partner): bool
    {
        return $user->hasRole(UserRole::Fundraising) || $user->hasRole(UserRole::Admin);
    }

    /**
     * Determine whether the user can detach any partners from the model.
     * Solo utenti con ruolo fundraising o admin possono rimuovere partner.
     */
    public function detachAnyPartner(User $user, FundraisingProject $fundraisingProject): bool
    {
        return $user->hasRole(UserRole::Fundraising) || $user->hasRole(UserRole::Admin);
    }

    /**
     * Determine whether the user can detach a specific partner from the model.
     * Solo utenti con ruolo fundraising o admin possono rimuovere partner specifici.
     */
    public function detachPartner(User $user, FundraisingProject $fundraisingProject, User $partner): bool
    {
        return $user->hasRole(UserRole::Fundraising) || $user->hasRole(UserRole::Admin);
    }
}
