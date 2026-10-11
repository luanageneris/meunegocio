<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureMasterAdminAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user || !$user->isMasterAdmin()) {
            if (!$request->expectsJson() && !$request->is('api/*')) {
                return redirect('/dashboard');
            }

            return response()->json([
                'message' => 'Acesso restrito ao administrador master.',
            ], 403);
        }

        return $next($request);
    }
}
