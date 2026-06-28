<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Blade;
use App\Helpers\AuthHelper;
use App\Models\Movimiento;
use App\Models\Venta;
use App\Observers\MovimientoObserver;
use App\Observers\VentaObserver;

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
        // Registrar Observers de inventario
        Movimiento::observe(MovimientoObserver::class);
        Venta::observe(VentaObserver::class);

        // Forzar HTTPS en producción
        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }

        // También forzar HTTPS si la aplicación está detrás de un proxy
        if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
            URL::forceScheme('https');
        }

        // Forzar HTTPS si se detecta el header X-Forwarded-Proto
        if (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') {
            URL::forceScheme('https');
        }

        // Registrar helper global para verificar si es admin
        Blade::if('isAdmin', function () {
            return AuthHelper::isAdmin();
        });
    }
}
