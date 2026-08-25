<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
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
        // NFR-PERF-003 - catch typos in fill()/update() payloads early. Lazy
        // loading is deliberately left enabled: policies traverse relations
        // (record -> tenancy -> property) during authorisation, so list
        // queries eager-load explicitly instead.
        Model::preventSilentlyDiscardingAttributes(! $this->app->isProduction());

        // SRS FR-AUTH-006 - a suspended account fails every policy check.
        Gate::before(fn (User $user) => $user->isActive() ? null : false);
    }
}
