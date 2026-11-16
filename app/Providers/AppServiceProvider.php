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
        // Política de contraseñas nuevas o cambiadas (igual en todos los entornos).
        // Sin uncompromised(): requiere red externa. No afecta a contraseñas existentes.
        Password::defaults(fn () => Password::min(12)->mixedCase()->numbers()->symbols());

        // Forzar HTTPS en entorno de producción o al estar desplegado en Render.
        // Si HAS_SSL no está definido, se fuerza HTTPS salvo en el entorno local
        // (Laragon por HTTP). Un HAS_SSL explícito (true/false) siempre manda.
        // Valor leído en config/app.php ('force_https' ← HAS_SSL) para que sobreviva a config:cache.
        $hasSsl = config('app.force_https');
        $forzarHttps = $hasSsl === null
            ? ! $this->app->environment('local')
            : filter_var($hasSsl, FILTER_VALIDATE_BOOL);

        if (config('app.env') === 'production' || $forzarHttps) {
            URL::forceScheme('https');
        }
    }
}