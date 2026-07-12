<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCollaboratorRequest;
use App\Http\Requests\UpdateCollaboratorRequest;
use App\Models\Collaborator;
use App\Models\Commission;
use App\Models\FinancialRecord;
use App\Services\CompanyDefaultsService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class CollaboratorController extends Controller
{
    /**
     * Listar colaboradores
     */
    public function index(Request $request)
    {
        $query = Collaborator::forCompany();

        if (!$request->boolean('include_defaults')) {
            $query->whereRaw('LOWER(name) NOT IN (?, ?)', [mb_strtolower(CompanyDefaultsService::DEFAULT_LABEL), 'nao definido']);
        }

        $collaborators = $query->orderBy('name')->get();

        return response()->json($collaborators);
    }

    /**
     * Listar colaboradores incluindo removidos
     */
    public function withTrashed(Request $request)
    {
        $collaborators = Collaborator::withTrashed()
            ->forCompany()
            ->whereRaw('LOWER(name) NOT IN (?, ?)', [mb_strtolower(CompanyDefaultsService::DEFAULT_LABEL), 'nao definido'])
            ->orderByDesc('deleted_at')
            ->orderBy('name')
            ->get();

        return response()->json($collaborators);
    }

    /**
     * Criar ou obter colaborador existente (por CPF)
     */
    public function getOrCreate(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'cpf' => ['required', 'regex:/^\d{3}\.\d{3}\.\d{3}-\d{2}$/'],
        ]);

        $collaborator = Collaborator::where('cpf', $request->cpf)
            ->forCompany()
            ->first();

        if ($collaborator) {
            return response()->json($collaborator);
        }

        $collaborator = Collaborator::create(array_merge(
            $request->only(['name', 'cpf']),
            [
                'company_id' => $request->user()->company_id,
                'active' => true,
            ]
        ));

        return response()->json($collaborator, 201);
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
     * Resumo de vendas e comissões por colaborador.
     */
    public function summary(Request $request, Collaborator $collaborator)
    {
        if ($collaborator->company_id !== $request->user()->company_id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $period = (string) $request->query('period', 'ultimos7dias');
        $validPeriods = ['ultimos7dias', 'mensal', 'anual', 'todos'];
        if (!in_array($period, $validPeriods, true)) {
            $period = 'ultimos7dias';
        }

        $startDate = null;
        $endDate = null;

        if ($period === 'ultimos7dias') {
            $startDate = Carbon::today()->subDays(6);
            $endDate = Carbon::today();
        } elseif ($period === 'mensal') {
            $startDate = Carbon::today()->startOfMonth();
            $endDate = Carbon::today()->endOfMonth();
        } elseif ($period === 'anual') {
            $startDate = Carbon::today()->startOfYear();
            $endDate = Carbon::today()->endOfYear();
        }

        $salesQuery = FinancialRecord::query()
            ->forCompany($request->user()->company_id)
            ->where('collaborator_id', $collaborator->id)
            ->where('type', 'entry');

        $commissionsQuery = Commission::query()
            ->forCompany($request->user()->company_id)
            ->where('collaborator_id', $collaborator->id);

        if ($startDate && $endDate) {
            $salesQuery->whereBetween('date', [$startDate->toDateString(), $endDate->toDateString()]);
            $commissionsQuery->whereBetween('date', [$startDate->toDateString(), $endDate->toDateString()]);
        }

        $salesTotal = FinancialRecord::sumFinalAmount($salesQuery);
        $commissionTotal = (float) $commissionsQuery->sum('amount');

        return response()->json([
            'data' => [
                'collaborator_id' => $collaborator->id,
                'period' => $period,
                'sales_total' => $salesTotal,
                'commission_total' => $commissionTotal,
            ],
        ]);
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

        if ($this->isProtectedDefault($collaborator->name)) {
            return response()->json(['message' => 'O colaborador padrão "Não definido" não pode ser renomeado'], 422);
        }

        $collaborator->update($request->validated());

        return response()->json($collaborator);
    }

    /**
     * Deletar colaborador (soft delete) e inativar
     */
    public function destroy(Request $request, Collaborator $collaborator)
    {
        // Validar isolamento
        if ($collaborator->company_id !== $request->user()->company_id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if (!$request->user()->isRoot()) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        if ($this->isProtectedDefault($collaborator->name)) {
            return response()->json(['message' => 'O colaborador padrão "Não definido" não pode ser removido'], 422);
        }

        // Inativar antes de deletar
        $collaborator->update(['active' => false]);
        $collaborator->delete();

        return response()->json([
            'message' => 'Colaborador removido com sucesso',
        ]);
    }

    /**
     * Restaurar colaborador removido
     */
    public function restore(Request $request, int $id)
    {
        $collaborator = Collaborator::onlyTrashed()
            ->where('id', $id)
            ->where('company_id', $request->user()->company_id)
            ->first();

        if (!$collaborator) {
            return response()->json(['message' => 'Not found'], 404);
        }

        // Ativar e restaurar
        $collaborator->update(['active' => true]);
        $collaborator->restore();

        return response()->json([
            'message' => 'Colaborador restaurado com sucesso',
            'data' => $collaborator,
        ]);
    }

    private function isProtectedDefault(string $name): bool
    {
        $normalized = mb_strtolower(trim($name));
        $defaultName = mb_strtolower(CompanyDefaultsService::DEFAULT_LABEL);

        return $normalized === $defaultName || $normalized === 'nao definido';
    }
}
