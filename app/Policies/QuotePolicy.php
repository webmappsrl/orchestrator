<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Quote;
use App\Enums\UserRole;
use App\Enums\QuoteStatus;
use Illuminate\Auth\Access\Response;

class QuotePolicy
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
    public function view(User $user, Quote $quote)
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
    public function update(User $user, Quote $quote)
    {
        //can not update if the quote has status closed_lost closed_won or paid, partially_paid
        return $quote->status != QuoteStatus::Partially_Paid->value &&
            $quote->status != QuoteStatus::Paid->value &&
            $quote->status != QuoteStatus::Closed_Won->value &&
            $quote->status != QuoteStatus::Closed_Lost->value;
    }

    /**
     * Determine wether the user can replicate the model.
     */
    public function replicate(User $user, Quote $quote)
    {
        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Quote $quote)
    {
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Quote $quote)
    {
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Quote $quote)
    {
    }
}