<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreEntryTypeRequest;
use App\Http\Requests\UpdateEntryTypeRequest;
use App\Models\EntryType;
use App\Services\CompanyDefaultsService;
use Illuminate\Http\Request;

class EntryTypeController extends Controller
{
    public function index(Request $request)
    {
        $types = EntryType::forCompany()
            ->whereRaw('LOWER(name) NOT IN (?, ?)', [mb_strtolower(CompanyDefaultsService::DEFAULT_LABEL), 'nao definido'])
            ->orderBy('name')
            ->get();

        return response()->json($types);
    }

    public function store(StoreEntryTypeRequest $request)
    {
        $type = EntryType::create(array_merge(
            $request->validated(),
            ['company_id' => $request->user()->company_id]
        ));

        return response()->json($type, 201);
    }

    public function show(Request $request, EntryType $entryType)
    {
        if ($entryType->company_id !== $request->user()->company_id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return response()->json($entryType);
    }

    public function update(UpdateEntryTypeRequest $request, EntryType $entryType)
    {
        if ($entryType->company_id !== $request->user()->company_id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if ($this->isProtectedDefault($entryType->name)) {
            return response()->json(['message' => 'O tipo padrão "Não definido" não pode ser renomeado'], 422);
        }

        $entryType->update($request->validated());

        return response()->json($entryType);
    }

    public function destroy(Request $request, EntryType $entryType)
    {
        if ($entryType->company_id !== $request->user()->company_id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if (!$request->user()->isRoot()) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        if ($this->isProtectedDefault($entryType->name)) {
            return response()->json(['message' => 'O tipo padrão "Não definido" não pode ser removido'], 422);
        }

        $entryType->delete();

        return response()->json([
            'message' => 'Tipo de entrada removido com sucesso',
        ]);
    }

    public function withTrashed(Request $request)
    {
        $types = EntryType::where('company_id', $request->user()->company_id)
            ->withTrashed()
            ->whereRaw('LOWER(name) NOT IN (?, ?)', [mb_strtolower(CompanyDefaultsService::DEFAULT_LABEL), 'nao definido'])
            ->orderBy('name')
            ->get();

        return response()->json($types);
    }

    public function restore(Request $request, $id)
    {
        $type = EntryType::withTrashed()->find($id);

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
            'message' => 'Tipo de entrada restaurado com sucesso',
            'data' => $type,
        ]);
    }

    private function isProtectedDefault(string $name): bool
    {
        $normalized = mb_strtolower(trim($name));
        $defaultName = mb_strtolower(CompanyDefaultsService::DEFAULT_LABEL);

        return $normalized === $defaultName || $normalized === 'nao definido';
    }
}
