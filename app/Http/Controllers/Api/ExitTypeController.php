<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreExitTypeRequest;
use App\Http\Requests\UpdateExitTypeRequest;
use App\Models\ExitType;
use Illuminate\Http\Request;

class ExitTypeController extends Controller
{
    public function index(Request $request)
    {
        $types = ExitType::forCompany()
            ->orderBy('name')
            ->get();

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

        $exitType->delete();

        return response()->json([
            'message' => 'Tipo de saída removido com sucesso',
        ]);
    }

    public function withTrashed(Request $request)
    {
        $types = ExitType::where('company_id', $request->user()->company_id)
            ->withTrashed()
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
}
