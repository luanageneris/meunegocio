<?php

namespace App\Http\Controllers\Api;

use App\Models\Balance;
use App\Models\FinancialRecord;
use App\Models\Commission;
use App\Http\Requests\StoreBalanceRequest;
use Illuminate\Http\Request;

class BalanceController
{
    /**
     * Get current balance for the company
     */
    public function current(Request $request)
    {
        $balance = Balance::forCompany()
            ->orderBy('reference_date', 'desc')
            ->first();

        if (!$balance) {
            return response()->json([
                'message' => 'Nenhum saldo registrado. Configure o saldo inicial.',
                'balance' => null,
            ], 404);
        }

        return response()->json([
            'balance' => $balance,
            'opening' => $balance->opening_balance,
            'closing' => $balance->closing_balance,
        ]);
    }

    /**
     * Get balance history
     */
    public function history(Request $request)
    {
        $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);

        $query = Balance::forCompany();

        if ($request->start_date && $request->end_date) {
            $query->whereBetween('reference_date', [$request->start_date, $request->end_date]);
        }

        $balances = $query->orderBy('reference_date', 'desc')->paginate(30);

        return response()->json($balances);
    }

    /**
     * Set initial balance
     */
    public function store(StoreBalanceRequest $request)
    {
        if (!$request->user()->isRoot()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $balance = Balance::create([
            'company_id' => $request->user()->company_id,
            'reference_date' => $request->reference_date,
            'opening_balance' => $request->opening_balance,
            'closing_balance' => $request->closing_balance,
        ]);

        return response()->json([
            'message' => 'Saldo registrado com sucesso',
            'balance' => $balance,
        ], 201);
    }

    /**
     * Get daily balance progression
     */
    public function dailyProgression(Request $request)
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        // Get all transactions in the range grouped by date
        $transactions = FinancialRecord::forCompany()
            ->whereBetween('date', [$request->start_date, $request->end_date])
            ->with('entryType', 'exitType')
            ->orderBy('date')
            ->get()
            ->groupBy(function($record) {
                return $record->date->format('Y-m-d');
            });

        // Get the latest balance before start date to use as base
        $lastBalance = Balance::forCompany()
            ->where('reference_date', '<', $request->start_date)
            ->orderBy('reference_date', 'desc')
            ->first();

        $baseAmount = $lastBalance ? $lastBalance->closing_balance : 0;

        // Build progression
        $progression = [];
        $currentBalance = $baseAmount;

        foreach ($transactions as $date => $records) {
            $dayTotal = 0;
            foreach ($records as $record) {
                if ($record->entry_type_id) {
                    $dayTotal += $record->final_amount;
                } else {
                    $dayTotal -= $record->final_amount;
                }
            }

            $currentBalance += $dayTotal;

            $progression[] = [
                'date' => $date,
                'day_change' => $dayTotal,
                'balance' => $currentBalance,
                'transaction_count' => $records->count(),
            ];
        }

        return response()->json([
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'base_balance' => $baseAmount,
            'final_balance' => $currentBalance,
            'progression' => $progression,
        ]);
    }

    /**
     * Get monthly balance summary
     */
    public function monthlySummary(Request $request)
    {
        $request->validate([
            'year' => 'required|integer|min:2000',
            'month' => 'required|integer|min:1|max:12',
        ]);

        $year = $request->year;
        $month = $request->month;

        $entries = FinancialRecord::forCompany()
            ->whereYear('date', $year)
            ->whereMonth('date', $month)
            ->whereNotNull('entry_type_id');

        $entries = FinancialRecord::sumFinalAmount($entries);

        $exits = FinancialRecord::forCompany()
            ->whereYear('date', $year)
            ->whereMonth('date', $month)
            ->whereNotNull('exit_type_id');

        $exits = FinancialRecord::sumFinalAmount($exits);

        $commissions = Commission::forCompany()
            ->whereYear('date', $year)
            ->whereMonth('date', $month)
            ->sum('amount');

        $profit = $entries - $exits - $commissions;

        return response()->json([
            'year' => $year,
            'month' => $month,
            'entries' => $entries,
            'exits' => $exits,
            'commissions' => $commissions,
            'profit' => $profit,
            'profit_margin' => $entries > 0 ? (($profit / $entries) * 100) : 0,
        ]);
    }

    /**
     * Get yearly balance summary
     */
    public function yearlySummary(Request $request)
    {
        $request->validate([
            'year' => 'required|integer|min:2000',
        ]);

        $year = $request->year;

        $entries = FinancialRecord::forCompany()
            ->whereYear('date', $year)
            ->whereNotNull('entry_type_id');

        $entries = FinancialRecord::sumFinalAmount($entries);

        $exits = FinancialRecord::forCompany()
            ->whereYear('date', $year)
            ->whereNotNull('exit_type_id');

        $exits = FinancialRecord::sumFinalAmount($exits);

        $commissions = Commission::forCompany()
            ->whereYear('date', $year)
            ->sum('amount');

        $profit = $entries - $exits - $commissions;

        return response()->json([
            'year' => $year,
            'entries' => $entries,
            'exits' => $exits,
            'commissions' => $commissions,
            'profit' => $profit,
            'profit_margin' => $entries > 0 ? (($profit / $entries) * 100) : 0,
        ]);
    }
}
