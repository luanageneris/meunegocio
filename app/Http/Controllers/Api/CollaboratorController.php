<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCollaboratorRequest;
use App\Http\Requests\UpdateCollaboratorRequest;
use App\Models\Collaborator;
use Illuminate\Http\Request;

class CollaboratorController extends Controller
{
    /**
     * Listar colaboradores
     */
    public function index(Request $request)
    {
        $collaborators = Collaborator::forCompany()
            ->orderBy('name')
            ->get();

        return response()->json($collaborators);
    }

    /**
     * Criar colaborador
     */
    public function store(StoreCollaboratorRequest $request)
    {
        $collaborator = Collaborator::create(array_merge(
            $request->validated(),
            ['company_id' => $request->user()->company_id]
        ));

        return response()->json($collaborator, 201);
    }

    /**
     * Obter um colaborador
     */
    public function show(Request $request, Collaborator $collaborator)
    {
        // Validar isolamento
        if ($collaborator->company_id !== $request->user()->company_id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return response()->json($collaborator);
    }

    /**
     * Atualizar colaborador
     */
    public function update(UpdateCollaboratorRequest $request, Collaborator $collaborator)
    {
        // Validar isolamento
        if ($collaborator->company_id !== $request->user()->company_id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $collaborator->update($request->validated());

        return response()->json($collaborator);
    }

    /**
     * Deletar colaborador (soft delete)
     */
    public function destroy(Request $request, Collaborator $collaborator)
    {
        // Validar isolamento e permissão
        if ($collaborator->company_id !== $request->user()->company_id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if (!$request->user()->isRoot()) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $collaborator->delete();

        return response()->json([
            'message' => 'Colaborador removido com sucesso',
        ]);
    }
}
