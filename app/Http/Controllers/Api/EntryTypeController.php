<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreEntryTypeRequest;
use App\Http\Requests\UpdateEntryTypeRequest;
use App\Models\EntryType;
use Illuminate\Http\Request;

class EntryTypeController extends Controller
{
    public function index(Request $request)
    {
        $types = EntryType::forCompany()
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

        $entryType->delete();

        return response()->json([
            'message' => 'Tipo de entrada removido com sucesso',
        ]);
    }
}
