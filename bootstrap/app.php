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
    ->withMiddleware(function (Middleware $middleware): void {
        // Middleware global para verificar acceso al sistema (excepto login y logout)
        $middleware->web(append: [
            \App\Http\Middleware\CheckSystemAccess::class,
        ]);

        $middleware->alias([
            'checkauth' => \App\Http\Middleware\CheckAuth::class,
            'admin.libreria' => \App\Http\Middleware\CheckAdminLibreria::class,
            'sales.operations' => \App\Http\Middleware\CheckSalesOperations::class,
            'secure.api' => \App\Http\Middleware\SecureApiMiddleware::class,
            'mobile.role' => \App\Http\Middleware\RequireMobileRole::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
