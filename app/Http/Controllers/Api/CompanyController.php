<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateCompanyRequest;
use App\Models\Company;
use Illuminate\Http\Request;

class CompanyController extends Controller
{
    /**
     * Obter dados da empresa do usuário autenticado
     */
    public function show(Request $request)
    {
        $user = $request->user('sanctum');

        if (!$user) {
            return response()->json(['message' => 'Não autenticado'], 401);
        }

        $company = $user->company;

        return response()->json([
            'data' => $company,
        ]);
    }

    /**
     * Atualizar dados da empresa
     */
    public function update(UpdateCompanyRequest $request)
    {
        $user = $request->user('sanctum');

        if (!$user) {
            return response()->json(['message' => 'Não autenticado'], 401);
        }

        $company = $user->company;

        // Validar se é proprietário/root
        if ($user->role !== 'root' && $user->role !== 'owner') {
            return response()->json(['message' => 'Não autorizado'], 403);
        }

        $company->update($request->validated());

        return response()->json([
            'data' => $company,
            'message' => 'Empresa atualizada com sucesso',
        ], 200);
    }

    /**
     * Verificar se empresa precisa de setup
     */
    public function needsSetup(Request $request)
    {
        $user = $request->user('sanctum');

        if (!$user) {
            return response()->json(['message' => 'Não autenticado'], 401);
        }

        $company = $user->company;

        // Uma empresa precisa de setup se não tem nome definido
        $needsSetup = !$company->name || $company->name === '';

        return response()->json([
            'data' => [
                'needs_setup' => $needsSetup,
                'company' => $company,
            ],
        ]);
    }
}
