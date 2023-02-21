<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Layer;
use App\Enums\UserRole;
use Illuminate\Auth\Access\HandlesAuthorization;

class LayerPolicy
{
    use HandlesAuthorization;

    /**
     * Only Editor can access to Layers
     *
     * @param User $user
     * @return bool
     */
    public function before(User $user)
    {
        return $user->hasRole(UserRole::Editor);
    }

    /**
     * Determine whether the user can view any models.
     *
     * @param  \App\Models\User  $user
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function viewAny(User $user)
    {
        //
    }

    /**
     * Determine whether the user can view the model.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Layer  $layer
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function view(User $user, Layer $layer)
    {
        //
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
     * @param  \App\Models\Layer  $layer
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function update(User $user, Layer $layer)
    {
        //
    }

    /**
     * Determine whether the user can delete the model.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Layer  $layer
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function delete(User $user, Layer $layer)
    {
        //
    }

    /**
     * Determine whether the user can restore the model.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Layer  $layer
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function restore(User $user, Layer $layer)
    {
        //
    }

    /**
     * Determine whether the user can permanently delete the model.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Layer  $layer
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function forceDelete(User $user, Layer $layer)
    {
        //
    }
}
