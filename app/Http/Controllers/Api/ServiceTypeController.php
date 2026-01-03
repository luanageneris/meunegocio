<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreServiceTypeRequest;
use App\Http\Requests\UpdateServiceTypeRequest;
use App\Models\ServiceType;
use Illuminate\Http\Request;

class ServiceTypeController extends Controller
{
    public function index(Request $request)
    {
        $types = ServiceType::forCompany()
            ->orderBy('name')
            ->get();

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

        if (!$request->user()->isRoot()) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $serviceType->delete();

        return response()->json([
            'message' => 'Tipo de serviço removido com sucesso',
        ]);
    }
}
