<?php

namespace App\Policies;

use App\Models\StockMovement;
use App\Models\User;

class StockMovementPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, StockMovement $stockMovement): bool
    {
        return true;
    }

    /**
     * Determine whether the user can create models.
     * Stock movements are typically created by the system, not manually
     */
    public function create(User $user): bool
    {
        // Read-only resource, no manual creation allowed
        return false;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, StockMovement $stockMovement): bool
    {
        // Read-only resource
        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, StockMovement $stockMovement): bool
    {
        // Read-only resource
        return false;
    }

    /**
     * Get user role for current company
     */
    protected function getUserRole(User $user): ?string
    {
        return app('current_user_role');
    }
}
