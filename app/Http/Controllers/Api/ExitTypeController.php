<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreExitTypeRequest;
use App\Http\Requests\UpdateExitTypeRequest;
use App\Models\ExitType;
use App\Services\CompanyDefaultsService;
use Illuminate\Http\Request;

class ExitTypeController extends Controller
{
    public function index(Request $request)
    {
        $query = ExitType::forCompany();

        if (!$request->boolean('include_defaults')) {
            $query->whereRaw('LOWER(name) NOT IN (?, ?)', [mb_strtolower(CompanyDefaultsService::DEFAULT_LABEL), 'nao definido']);
        }

        $types = $query->orderBy('name')->get();

        return response()->json($types);
    }

    public function store(StoreExitTypeRequest $request)
    {
        $type = ExitType::create(array_merge(
            $request->validated(),
            ['company_id' => $request->user()->company_id]
        ));

        return response()->json($type, 201);
    }

    public function show(Request $request, ExitType $exitType)
    {
        if ($exitType->company_id !== $request->user()->company_id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return response()->json($exitType);
    }

    public function update(UpdateExitTypeRequest $request, ExitType $exitType)
    {
        if ($exitType->company_id !== $request->user()->company_id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if ($this->isProtectedDefault($exitType->name)) {
            return response()->json(['message' => 'Este tipo padrão não pode ser renomeado'], 422);
        }

        $exitType->update($request->validated());

        return response()->json($exitType);
    }

    public function destroy(Request $request, ExitType $exitType)
    {
        if ($exitType->company_id !== $request->user()->company_id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if (!$request->user()->isRoot()) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        if ($this->isProtectedDefault($exitType->name)) {
            return response()->json(['message' => 'Este tipo padrão não pode ser removido'], 422);
        }

        $exitType->delete();

        return response()->json([
            'message' => 'Tipo de saída removido com sucesso',
        ]);
    }

    public function withTrashed(Request $request)
    {
        $types = ExitType::where('company_id', $request->user()->company_id)
            ->withTrashed()
            ->whereRaw('LOWER(name) NOT IN (?, ?)', [mb_strtolower(CompanyDefaultsService::DEFAULT_LABEL), 'nao definido'])
            ->orderBy('name')
            ->get();

        return response()->json($types);
    }

    public function restore(Request $request, $id)
    {
        $type = ExitType::withTrashed()->find($id);

        if (!$type) {
            return response()->json(['message' => 'Não encontrado'], 404);
        }

        if ($type->company_id !== $request->user()->company_id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if (!$request->user()->isRoot()) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $type->restore();

        return response()->json([
            'message' => 'Tipo de saída restaurado com sucesso',
            'data' => $type,
        ]);
    }

    private function isProtectedDefault(string $name): bool
    {
        $normalized = mb_strtolower(trim($name));
        $defaultName = mb_strtolower(CompanyDefaultsService::DEFAULT_LABEL);
        $commissionDefaultName = mb_strtolower(CompanyDefaultsService::DEFAULT_EXIT_TYPE_COMMISSION_LABEL);

        return in_array($normalized, [
            $defaultName,
            'nao definido',
            $commissionDefaultName,
            'comissao',
        ], true);
    }
}
