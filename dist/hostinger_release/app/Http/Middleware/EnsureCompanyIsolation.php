<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCompanyIsolation
{
    /**
     * Garante que o usuário só acessa dados de sua empresa
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user() && !$request->user()->company_id) {
            return response()->json([
                'message' => 'User must be associated with a company.',
            ], 403);
        }

        // Armazenar company_id na requisição para uso nos controllers
        if ($request->user()) {
            $request->attributes->set('company_id', $request->user()->company_id);
        }

        return $next($request);
    }
}
