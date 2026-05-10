<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Laravel\Horizon\Horizon;

class HorizonServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Gate::define('viewHorizon', fn (User $user) => (bool) $user->is_admin);

        if (! class_exists(Horizon::class)) {
            return;
        }

        Horizon::auth(
            fn ($request) => app()->environment('local') || optional($request->user())->is_admin
        );
    }
}
