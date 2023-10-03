<?php

namespace App\Policies;

use App\Models\User;
use App\Enums\UserRole;
use App\Models\Deadline;
use Illuminate\Auth\Access\Response;

class DeadlinePolicy
{
    public function before(User $user)
    {
        return $user->hasRole(UserRole::Admin) || $user->hasRole(UserRole::Manager) || $user->hasRole(UserRole::Developer);
    }
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user)
    {
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Deadline $deadline)
    {
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user)
    {
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Deadline $deadline)
    {
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Deadline $deadline)
    {
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Deadline $deadline)
    {
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Deadline $deadline)
    {
    }
}