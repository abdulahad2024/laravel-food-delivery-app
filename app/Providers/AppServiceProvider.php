<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::define('access-admin-dashboard', function (User $user) {
            return in_array($user->status, [null, 'active'], true)
                && ($user->hasRole('admin') || $user->hasRole('super_admin'));
        });

        Gate::define('manage-user-roles', function (User $user) {
            return in_array($user->status, [null, 'active'], true)
                && ($user->hasRole('admin') || $user->hasRole('super_admin'));
        });
    }
}
