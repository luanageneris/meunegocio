<?php

namespace App\Http\Controllers\Api;

use App\Models\DailyClosure;
use App\Models\FinancialRecord;
use App\Models\Commission;
use App\Http\Requests\StoreDailyClosureRequest;
use Illuminate\Http\Request;

class DailyClosureController
{
    /**
     * List daily closures for current company
     */
    public function index(Request $request)
    {
        $closures = DailyClosure::forCompany()
            ->orderBy('date', 'desc')
            ->paginate(30);

        return response()->json(['data' => $closures->items(), 'pagination' => ['total' => $closures->total(), 'per_page' => $closures->perPage(), 'current_page' => $closures->currentPage()]]);
    }

    /**
     * Show a daily closure
     */
    public function show(Request $request, DailyClosure $dailyClosure)
    {
        if ($dailyClosure->company_id !== $request->user()->company_id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return response()->json($dailyClosure);
    }

    /**
     * Close a day - calculate totals and lock
     */
    public function store(StoreDailyClosureRequest $request)
    {
        $date = $request->date;
        $companyId = $request->user()->company_id;

        // Check if already closed
        $existing = DailyClosure::forCompany()
            ->where('date', $date)
            ->first();

        if ($existing) {
            return response()->json([
                'message' => 'Este dia já foi fechado',
                'closure' => $existing,
            ], 409);
        }

        // Calculate totals
        $entries = FinancialRecord::forCompany()
            ->whereDate('date', $date)
            ->whereNotNull('entry_type_id');

        $entries = FinancialRecord::sumFinalAmount($entries);

        $exits = FinancialRecord::forCompany()
            ->whereDate('date', $date)
            ->whereNotNull('exit_type_id');

        $exits = FinancialRecord::sumFinalAmount($exits);

        $commissions = Commission::forCompany()
            ->whereDate('date', $date)
            ->whereNull('paid_at')
            ->sum('amount');

        $profit = $entries - $exits - $commissions;

        // Create closure
        $closure = DailyClosure::create([
            'company_id' => $companyId,
            'date' => $date,
            'entries' => $entries,
            'exits' => $exits,
            'commissions' => $commissions,
            'profit' => $profit,
            'closed_by' => $request->user()->id,
            'closed_at' => now(),
            'notes' => $request->notes ?? null,
        ]);

        return response()->json([
            'message' => 'Dia fechado com sucesso',
            'closure' => $closure,
        ], 201);
    }

    /**
     * Reopen a closed day (only root)
     */
    public function reopen(Request $request, DailyClosure $dailyClosure)
    {
        if ($dailyClosure->company_id !== $request->user()->company_id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if (!$request->user()->isRoot()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $dailyClosure->update([
            'closed_at' => null,
            'closed_by' => null,
        ]);

        return response()->json([
            'message' => 'Dia reabertor com sucesso',
            'closure' => $dailyClosure,
        ]);
    }

    /**
     * Get closure summary for a date range
     */
    public function summary(Request $request)
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        $closures = DailyClosure::forCompany()
            ->whereBetween('date', [$request->start_date, $request->end_date])
            ->orderBy('date')
            ->get();

        $totalEntries = $closures->sum('entries');
        $totalExits = $closures->sum('exits');
        $totalCommissions = $closures->sum('commissions');
        $totalProfit = $closures->sum('profit');
        $closedDays = $closures->count();

        return response()->json([
            'closures' => $closures,
            'summary' => [
                'closed_days' => $closedDays,
                'total_entries' => $totalEntries,
                'total_exits' => $totalExits,
                'total_commissions' => $totalCommissions,
                'total_profit' => $totalProfit,
                'average_daily_profit' => $closedDays > 0 ? $totalProfit / $closedDays : 0,
            ],
        ]);
    }

    /**
     * Check if a day is closed
     */
    public function isClosed(Request $request, $date)
    {
        $closure = DailyClosure::forCompany()
            ->where('date', $date)
            ->first();

        return response()->json([
            'date' => $date,
            'closed' => $closure !== null,
            'closure' => $closure,
        ]);
    }
}
