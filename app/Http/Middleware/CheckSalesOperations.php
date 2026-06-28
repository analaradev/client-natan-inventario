<?php

namespace App\Http\Middleware;

use App\Helpers\AuthHelper;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckSalesOperations
{
    /**
     * Permite crear ventas, apartados y clientes a Admin Libreria y Supervisor.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (!AuthHelper::canManageSalesOperations()) {
            return redirect()->route('dashboard')
                ->with('error', 'No tienes permisos para realizar esta operación.');
        }

        return $next($request);
    }
}
