<?php

namespace App\Http\Controllers\Api;

use App\Models\Commission;
use App\Models\FinancialRecord;
use App\Models\Collaborator;
use App\Http\Requests\StoreCommissionRequest;
use App\Http\Requests\UpdateCommissionRequest;
use Illuminate\Http\Request;

class CommissionController
{
    /**
     * List commissions for current company
     */
    public function index(Request $request)
    {
        $commissions = Commission::forCompany()
            ->with('collaborator', 'financialRecord')
            ->orderBy('date', 'desc')
            ->paginate(30);

        return response()->json($commissions);
    }

    /**
     * Store a new commission (manual entry)
     */
    public function store(StoreCommissionRequest $request)
    {
        $commission = Commission::create($request->validated());
        
        return response()->json(['data' => $commission->load('collaborator', 'financialRecord')], 201);
    }

    /**
     * Show a commission
     */
    public function show(Request $request, Commission $commission)
    {
        if ($commission->company_id !== $request->user()->company_id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return response()->json($commission->load('collaborator', 'financialRecord'));
    }

    /**
     * Update a commission
     */
    public function update(UpdateCommissionRequest $request, Commission $commission)
    {
        if ($commission->company_id !== $request->user()->company_id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $commission->update($request->validated());

        return response()->json(['data' => $commission->load('collaborator', 'financialRecord')]);
    }

    /**
     * Delete a commission (soft delete)
     */
    public function destroy(Request $request, Commission $commission)
    {
        if ($commission->company_id !== $request->user()->company_id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if (!$request->user()->isRoot()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $commission->delete();

        return response()->json(['message' => 'Comissão removida com sucesso']);
    }

    /**
     * Generate commissions from financial records for a specific date
     */
    public function generateFromDate(Request $request)
    {
        $request->validate([
            'date' => 'required|date',
        ]);

        if (!$request->user()->isRoot()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $date = $request->date;
        $companyId = $request->user()->company_id;

        // Get all financial records for the date with service types
        $records = FinancialRecord::forCompany()
            ->whereDate('date', $date)
            ->whereNotNull('service_type_id')
            ->whereNotNull('collaborator_id')
            ->with('serviceType', 'collaborator')
            ->get();

        $commissions = [];
        foreach ($records as $record) {
            // Calculate commission: amount * (percentage / 100)
            $commissionValue = $record->amount * ($record->serviceType->commission_percentage / 100);

            // Create commission
            $commission = Commission::create([
                'company_id' => $companyId,
                'collaborator_id' => $record->collaborator_id,
                'financial_record_id' => $record->id,
                'date' => $date,
                'amount' => $commissionValue,
                'paid_at' => null,
                'notes' => "Comissão automática de {$record->serviceType->name}",
            ]);

            $commissions[] = $commission;
        }

        return response()->json([
            'message' => 'Comissões geradas com sucesso',
            'count' => count($commissions),
            'commissions' => $commissions,
        ], 201);
    }

    /**
     * Get commissions for a collaborator
     */
    public function collaboratorCommissions(Request $request, $collaboratorId)
    {
        $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);

        $query = Commission::forCompany()
            ->where('collaborator_id', $collaboratorId)
            ->with('collaborator', 'financialRecord');

        if ($request->start_date && $request->end_date) {
            $query->whereBetween('date', [$request->start_date, $request->end_date]);
        }

        $commissions = $query->orderBy('date', 'desc')->paginate(30);

        return response()->json($commissions);
    }

    /**
     * Get unpaid commissions for a collaborator
     */
    public function unpaidCommissions(Request $request, $collaboratorId)
    {
        $commissions = Commission::forCompany()
            ->where('collaborator_id', $collaboratorId)
            ->whereNull('paid_at')
            ->with('collaborator')
            ->orderBy('date')
            ->get();

        $total = $commissions->sum('amount');

        return response()->json([
            'commissions' => $commissions,
            'total' => $total,
        ]);
    }

    /**
     * Mark commissions as paid
     */
    public function markAsPaid(Request $request)
    {
        $request->validate([
            'commission_ids' => 'required|array|min:1',
            'commission_ids.*' => 'integer|exists:commissions,id',
            'paid_at' => 'required|date',
        ]);

        if (!$request->user()->isRoot()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $commissions = Commission::forCompany()
            ->whereIn('id', $request->commission_ids)
            ->update(['paid_at' => $request->paid_at]);

        return response()->json([
            'message' => 'Comissões marcadas como pagas com sucesso',
            'updated' => $commissions,
        ]);
    }

    /**
     * Get commission summary by collaborator for a date range
     */
    public function summaryByCollaborator(Request $request)
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        $summary = Commission::forCompany()
            ->with(['collaborator', 'financialRecord'])
            ->whereBetween('date', [$request->start_date, $request->end_date])
            ->get()
            ->groupBy('collaborator_id')
            ->map(function($commissions) {
                return [
                    'collaborator_id' => $commissions->first()->collaborator_id,
                    'collaborator_name' => $commissions->first()->collaborator->name,
                    'total' => $commissions->sum('amount'),
                    'paid' => $commissions->whereNotNull('paid_at')->sum('amount'),
                    'unpaid' => $commissions->whereNull('paid_at')->sum('amount'),
                    'count' => $commissions->count(),
                ];
            })->values();

        return response()->json($summary);
    }

    /**
     * Get commission summary by month for a date range
     */
    public function summaryByMonth(Request $request)
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        $commissions = Commission::forCompany()
            ->whereBetween('date', [$request->start_date, $request->end_date])
            ->get();

        $monthNames = ['Jan', 'Fev', 'Mar', 'Abr', 'Maio', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'];
        
        $summary = [];
        for ($m = 0; $m < 12; $m++) {
            $summary[$m] = [
                'month' => $monthNames[$m],
                'monthNum' => $m,
                'total' => 0,
                'paid' => 0,
                'unpaid' => 0,
            ];
        }

        $commissions->each(function($commission) use (&$summary) {
            $monthNum = $commission->date->month - 1;
            $summary[$monthNum]['total'] += $commission->amount;
            if ($commission->paid_at) {
                $summary[$monthNum]['paid'] += $commission->amount;
            } else {
                $summary[$monthNum]['unpaid'] += $commission->amount;
            }
        });

        return response()->json(array_values($summary));
    }
}
