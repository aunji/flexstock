<?php

namespace App\Policies;

use App\Models\SaleOrder;
use App\Models\User;

class SaleOrderPolicy
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
    public function view(User $user, SaleOrder $saleOrder): bool
    {
        return true;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        $role = $this->getUserRole($user);

        // Admin and Cashier can create orders
        return in_array($role, ['admin', 'cashier']);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, SaleOrder $saleOrder): bool
    {
        $role = $this->getUserRole($user);

        // Admin and Cashier can update orders (but only Draft orders)
        if ($saleOrder->status !== 'Draft') {
            return false;
        }

        return in_array($role, ['admin', 'cashier']);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, SaleOrder $saleOrder): bool
    {
        $role = $this->getUserRole($user);

        // Only Admin can delete orders
        return $role === 'admin';
    }

    /**
     * Determine whether the user can confirm the order.
     */
    public function confirm(User $user, SaleOrder $saleOrder): bool
    {
        $role = $this->getUserRole($user);

        // Admin and Cashier can confirm orders
        return in_array($role, ['admin', 'cashier']);
    }

    /**
     * Determine whether the user can cancel the order.
     */
    public function cancel(User $user, SaleOrder $saleOrder): bool
    {
        $role = $this->getUserRole($user);

        // Admin and Cashier can cancel orders
        return in_array($role, ['admin', 'cashier']);
    }

    /**
     * Determine whether the user can mark payment as received.
     */
    public function markPaymentReceived(User $user, SaleOrder $saleOrder): bool
    {
        $role = $this->getUserRole($user);

        // Admin and Cashier can mark payment received
        return in_array($role, ['admin', 'cashier']);
    }

    /**
     * Determine whether the user can approve payment.
     */
    public function approvePayment(User $user, SaleOrder $saleOrder): bool
    {
        $role = $this->getUserRole($user);

        // Only Admin can approve payments
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
