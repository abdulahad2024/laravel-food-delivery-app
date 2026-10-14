<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function update(User $user, User $target): bool
    {
        return $user->id === $target->id
            || $user->hasRole('admin')
            || $user->hasRole('super_admin');
    }

    public function manageRoles(User $user, User $target): bool
    {
        return $user->hasRole('admin') || $user->hasRole('super_admin');
    }
}
