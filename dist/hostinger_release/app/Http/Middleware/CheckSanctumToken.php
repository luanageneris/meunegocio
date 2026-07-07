<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckSanctumToken
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Se está autenticado com Sanctum, permite
        if ($request->user('sanctum')) {
            return $next($request);
        }

        // Se não tem token Sanctum, redireciona para login
        return redirect('/login?error=unauthorized');
    }
}
