<?php

namespace App\Providers;

use App\Models\User;
use App\Modules\User\Domain\Role;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class UserServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        Gate::define('admin-access', function (User $user) {
            return $user->role === Role::Admin;
        });
    }
}
