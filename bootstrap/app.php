<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
   ->withMiddleware(function (Middleware $middleware) {
    $middleware->alias([
        'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
        'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
    ]);

    // Proxy inverso (Render termina TLS y reenvía por HTTP): los proxies de confianza se leen de
    // TRUSTED_PROXIES (config/trustedproxy.php; vacío en Laragon = no se confía en ningún proxy).
    // Solo se aceptan la IP del cliente y el esquema; el host y el puerto salen de la cabecera Host,
    // para que un X-Forwarded-Host o X-Forwarded-Port falsificado no altere las URLs generadas.
    $middleware->trustProxies(
        headers: \Illuminate\Http\Request::HEADER_X_FORWARDED_FOR | \Illuminate\Http\Request::HEADER_X_FORWARDED_PROTO,
    );
})
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
