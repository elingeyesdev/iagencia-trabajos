<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Spatie\Permission\PermissionRegistrar;
use Illuminate\Validation\Rules\Password;
use Illuminate\Support\Facades\URL;

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
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
        Password::defaults(fn () => Password::min(12));

        // Forzar HTTPS en entorno de producción o al estar desplegado en Render
        if (config('app.env') === 'production' || env('HAS_SSL', true)) {
            URL::forceScheme('https');
        }
    }
}