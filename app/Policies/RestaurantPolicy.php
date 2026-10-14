<?php

namespace App\Policies;

use App\Models\Restaurant;
use App\Models\RestaurantUser;
use App\Models\User;

class RestaurantPolicy
{
    public function view(User|RestaurantUser $user, Restaurant $restaurant): bool
    {
        return ($user instanceof RestaurantUser && $user->id === $restaurant->owner_id)
            || ($user instanceof User && ($user->hasRole('admin') || $user->hasRole('super_admin')));
    }

    public function update(User|RestaurantUser $user, Restaurant $restaurant): bool
    {
        return ($user instanceof RestaurantUser && $user->id === $restaurant->owner_id)
            || ($user instanceof User && ($user->hasRole('admin') || $user->hasRole('super_admin')));
    }

    public function approve(User $user, Restaurant $restaurant): bool
    {
        return $user->hasRole('admin') || $user->hasRole('super_admin');
    }
}
