<?php

namespace App\Policies;

use App\Models\Customer;
use App\Models\User;

class CustomerPolicy
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
    public function view(User $user, Customer $customer): bool
    {
        return true;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        $role = $this->getUserRole($user);

        // Admin and Cashier can create customers
        return in_array($role, ['admin', 'cashier']);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Customer $customer): bool
    {
        $role = $this->getUserRole($user);

        // Admin and Cashier can update customers
        return in_array($role, ['admin', 'cashier']);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Customer $customer): bool
    {
        $role = $this->getUserRole($user);

        // Only Admin can delete customers
        return $role === 'admin';
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Customer $customer): bool
    {
        $role = $this->getUserRole($user);

        return $role === 'admin';
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Customer $customer): bool
    {
        $role = $this->getUserRole($user);

        return $role === 'admin';
    }

    /**
     * Get user role for current company
     */
    protected function getUserRole(User $user): ?string
    {
        return app('current_user_role');
    }
}
