<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateCompanyRequest;
use App\Models\Company;
use App\Models\User;
use App\Services\CompanyDefaultsService;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;

class CompanyController extends Controller
{
    public function __construct(private CompanyDefaultsService $companyDefaultsService)
    {
    }

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

        // Validar se é proprietário/root
        if ($user->role !== 'root' && $user->role !== 'owner') {
            return response()->json(['message' => 'Não autorizado'], 403);
        }

        $validated = $request->validated();
        $company = $user->company;

        // Primeiro acesso: usuário ainda não está associado a uma empresa.
        if (!$company) {
            $company = DB::transaction(function () use ($user, $validated) {
                $newCompany = Company::create([
                    'owner_id' => $user->id,
                    'name' => $validated['name'],
                    'slug' => $this->generateUniqueSlug($validated['name']),
                    'email' => $validated['email'] ?? null,
                    'phone' => $validated['phone'] ?? null,
                    'document' => $validated['document'] ?? null,
                    'address' => $validated['address'] ?? null,
                    'city' => $validated['city'] ?? null,
                    'state' => $validated['state'] ?? null,
                    'postal_code' => $validated['postal_code'] ?? null,
                    'active' => true,
                    'use_service_types' => (bool) ($validated['use_service_types'] ?? false),
                ]);

                $user->forceFill([
                    'company_id' => $newCompany->id,
                ])->save();

                $this->companyDefaultsService->ensureForCompany((int) $newCompany->id);

                return $newCompany;
            });

            return response()->json([
                'data' => $company->fresh(),
                'message' => 'Empresa criada com sucesso',
            ], 200);
        }

        $company->update($validated);

        return response()->json([
            'data' => $company,
            'message' => 'Empresa atualizada com sucesso',
        ], 200);
    }

    private function generateUniqueSlug(string $name): string
    {
        $baseSlug = Str::slug($name);
        $baseSlug = $baseSlug !== '' ? $baseSlug : 'empresa';
        $slug = $baseSlug;
        $suffix = 2;

        while (Company::withTrashed()->where('slug', $slug)->exists()) {
            $slug = $baseSlug . '-' . $suffix;
            $suffix++;
        }

        return $slug;
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

    /**
     * Apagar conta (empresa) e todos os dados relacionados.
     */
    public function destroy(Request $request)
    {
        $user = $request->user('sanctum');

        if (!$user) {
            return response()->json(['message' => 'Não autenticado'], 401);
        }

        if ($user->role !== 'root' && $user->role !== 'owner') {
            return response()->json(['message' => 'Não autorizado'], 403);
        }

        $company = $user->company;

        if (!$company) {
            return response()->json(['message' => 'Empresa não encontrada'], 404);
        }

        DB::transaction(function () use ($company) {
            $userIds = $company->users()->pluck('id');

            if ($userIds->isNotEmpty()) {
                PersonalAccessToken::where('tokenable_type', User::class)
                    ->whereIn('tokenable_id', $userIds)
                    ->delete();
            }

            // Remove usuários associados (delete físico) e então a empresa
            $company->users()->delete();

            // Cascading delete apagará registros com company_id (constraints via migrations)
            $company->forceDelete();
        });

        return response()->json([
            'message' => 'Conta apagada com sucesso. Todos os dados foram removidos.',
        ]);
    }
}
