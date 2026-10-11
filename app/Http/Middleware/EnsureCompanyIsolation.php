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
        if ($request->user() && !$request->user()->company_id && !$this->isAllowedWithoutCompany($request)) {
            return response()->json([
                'message' => 'Usuário deve estar associado a uma empresa.',
            ], 403);
        }

        // Armazenar company_id na requisição para uso nos controllers
        if ($request->user()) {
            $request->attributes->set('company_id', $request->user()->company_id);
        }

        return $next($request);
    }

    private function isAllowedWithoutCompany(Request $request): bool
    {
        if ($request->is('api/company') && in_array($request->method(), ['GET', 'PUT'], true)) {
            return true;
        }

        if ($request->is('api/company/needs-setup') && $request->isMethod('GET')) {
            return true;
        }

        return false;
    }
}
