<?php

namespace App\Http\Controllers\Api;

use App\Models\FinancialRecord;
use App\Http\Requests\StoreFinancialRecordRequest;
use App\Http\Requests\UpdateFinancialRecordRequest;
use Illuminate\Http\Request;

class FinancialRecordController
{
    /**
     * List financial records for current company
     */
    public function index(Request $request)
    {
        $records = FinancialRecord::forCompany()
            ->with('collaborator', 'entryType', 'exitType', 'serviceType')
            ->orderBy('date', 'desc')
            ->paginate(15);

        return response()->json($records);
    }

    /**
     * Store a new financial record
     */
    public function store(StoreFinancialRecordRequest $request)
    {
        $record = FinancialRecord::create($request->validated());
        
        return response()->json($record->load('collaborator', 'entryType', 'exitType', 'serviceType'), 201);
    }

    /**
     * Show a financial record
     */
    public function show(Request $request, FinancialRecord $financialRecord)
    {
        if ($financialRecord->company_id !== $request->user()->company_id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return response()->json($financialRecord->load('collaborator', 'entryType', 'exitType', 'serviceType'));
    }

    /**
     * Update a financial record
     */
    public function update(UpdateFinancialRecordRequest $request, FinancialRecord $financialRecord)
    {
        if ($financialRecord->company_id !== $request->user()->company_id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $financialRecord->update($request->validated());

        return response()->json($financialRecord->load('collaborator', 'entryType', 'exitType', 'serviceType'));
    }

    /**
     * Delete a financial record (soft delete)
     */
    public function destroy(Request $request, FinancialRecord $financialRecord)
    {
        if ($financialRecord->company_id !== $request->user()->company_id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if (!$request->user()->isRoot()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $financialRecord->delete();

        return response()->json(['message' => 'Registro financeiro removido com sucesso']);
    }

    /**
     * Get financial records for a specific date
     */
    public function byDate(Request $request, $date)
    {
        $records = FinancialRecord::forCompany()
            ->whereDate('date', $date)
            ->with('collaborator', 'entryType', 'exitType', 'serviceType')
            ->get();

        return response()->json($records);
    }

    /**
     * Get financial records for a date range
     */
    public function betweenDates(Request $request)
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        $records = FinancialRecord::forCompany()
            ->whereBetween('date', [$request->start_date, $request->end_date])
            ->with('collaborator', 'entryType', 'exitType', 'serviceType')
            ->orderBy('date', 'desc')
            ->paginate(50);

        return response()->json($records);
    }

    /**
     * Calculate daily totals (entries, exits, profit)
     */
    public function dailyTotals(Request $request)
    {
        $request->validate([
            'date' => 'required|date',
        ]);

        $date = $request->date;

        $entries = FinancialRecord::forCompany()
            ->whereDate('date', $date)
            ->whereNotNull('entry_type_id')
            ->sum('value');

        $exits = FinancialRecord::forCompany()
            ->whereDate('date', $date)
            ->whereNotNull('exit_type_id')
            ->sum('value');

        $profit = $entries - $exits;

        return response()->json([
            'date' => $date,
            'entries' => $entries,
            'exits' => $exits,
            'profit' => $profit,
            'closed' => false, // TODO: check if day is closed in DailyClosure
        ]);
    }
}
