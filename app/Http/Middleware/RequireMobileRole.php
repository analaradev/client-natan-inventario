<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Traits\HasRoleChecks;

class RequireMobileRole
{
    use HasRoleChecks;

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (!$this->hasValidMobileRole($request)) {
            return response()->json([
                'success' => false,
                'message' => 'No tienes autorización para acceder a los recursos de la API móvil.'
            ], 403);
        }

        return $next($request);
    }
}
