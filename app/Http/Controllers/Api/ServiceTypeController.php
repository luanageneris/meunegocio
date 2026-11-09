<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreServiceTypeRequest;
use App\Http\Requests\UpdateServiceTypeRequest;
use App\Models\ServiceType;
use App\Services\MasterDataService;
use Illuminate\Http\Request;

class ServiceTypeController extends Controller
{
    public function index(Request $request)
    {
        $query = ServiceType::forCompany();
        if (!$request->boolean('include_inactive')) {
            $query->where('active', true);
        }
        $types = $query->orderBy('name')->get();

        return response()->json($types);
    }

    public function store(StoreServiceTypeRequest $request)
    {
        $type = ServiceType::create(array_merge(
            $request->validated(),
            ['company_id' => $request->user()->company_id]
        ));

        return response()->json($type, 201);
    }

    public function show(Request $request, ServiceType $serviceType)
    {
        if ($serviceType->company_id !== $request->user()->company_id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return response()->json($serviceType);
    }

    public function update(UpdateServiceTypeRequest $request, ServiceType $serviceType)
    {
        if ($serviceType->company_id !== $request->user()->company_id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $serviceType->update($request->validated());

        return response()->json($serviceType);
    }

    public function destroy(Request $request, ServiceType $serviceType)
    {
        if ($serviceType->company_id !== $request->user()->company_id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if (!$request->user()->canManageFinancialRecords()) {
            return response()->json(['message' => 'Apenas o proprietário pode remover este item.'], 403);
        }

        $validated = $request->validate(['replacement_id' => 'nullable|integer']);
        app(MasterDataService::class)->delete($serviceType, $validated['replacement_id'] ?? null);

        return response()->json([
            'message' => 'Tipo de serviço removido com sucesso',
        ]);
    }

    public function withTrashed(Request $request)
    {
        $types = ServiceType::where('company_id', $request->user()->company_id)
            ->withTrashed()
            ->orderBy('name')
            ->get();

        return response()->json($types);
    }

    public function restore(Request $request, $id)
    {
        $type = ServiceType::withTrashed()->find($id);

        if (!$type) {
            return response()->json(['message' => 'Não encontrado'], 404);
        }

        if ($type->company_id !== $request->user()->company_id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if (!$request->user()->canManageFinancialRecords()) {
            return response()->json(['message' => 'Apenas o proprietário pode restaurar este item.'], 403);
        }

        $type->restore();

        return response()->json([
            'message' => 'Tipo de serviço restaurado com sucesso',
            'data' => $type,
        ]);
    }
}
