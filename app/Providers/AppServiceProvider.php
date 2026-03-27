<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Gate;
use App\Models\User;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Admins can do everything
        Gate::before(function (User $user, string $ability) {
            if ($user->hasRole('admin')) {
                return true;
            }
        });
    }
}
