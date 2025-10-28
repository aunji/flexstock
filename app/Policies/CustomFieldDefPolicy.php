<?php

namespace App\Policies;

use App\Models\CustomFieldDef;
use App\Models\User;

class CustomFieldDefPolicy
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
    public function view(User $user, CustomFieldDef $customFieldDef): bool
    {
        return true;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        $role = $this->getUserRole($user);

        // Only Admin can create custom fields
        return $role === 'admin';
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, CustomFieldDef $customFieldDef): bool
    {
        $role = $this->getUserRole($user);

        // Only Admin can update custom fields
        return $role === 'admin';
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, CustomFieldDef $customFieldDef): bool
    {
        $role = $this->getUserRole($user);

        // Only Admin can delete custom fields
        return $role === 'admin';
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, CustomFieldDef $customFieldDef): bool
    {
        $role = $this->getUserRole($user);

        return $role === 'admin';
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, CustomFieldDef $customFieldDef): bool
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
