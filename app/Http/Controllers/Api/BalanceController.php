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
            ->orderBy('date', 'desc')
            ->first();

        if (!$balance) {
            return response()->json([
                'message' => 'Nenhum saldo registrado. Configure o saldo inicial.',
                'balance' => null,
            ], 404);
        }

        // Calculate current balance with all transactions since last recorded balance
        $transactionsAfter = FinancialRecord::forCompany()
            ->where('date', '>', $balance->date)
            ->selectRaw('SUM(CASE WHEN entry_type_id IS NOT NULL THEN value ELSE -value END) as net_change')
            ->first();

        $currentTotal = $balance->amount + ($transactionsAfter->net_change ?? 0);

        return response()->json([
            'initial_balance' => $balance,
            'current_balance' => $currentTotal,
            'transactions_since_last_balance' => $transactionsAfter->net_change ?? 0,
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
            $query->whereBetween('date', [$request->start_date, $request->end_date]);
        }

        $balances = $query->orderBy('date', 'desc')->paginate(30);

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
            'date' => $request->date,
            'amount' => $request->amount,
            'notes' => $request->notes ?? null,
            'recorded_by' => $request->user()->id,
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

        // Get the balance at the start date
        $startBalance = Balance::forCompany()
            ->where('date', '<=', $request->start_date)
            ->orderBy('date', 'desc')
            ->first();

        $baseAmount = $startBalance ? $startBalance->amount : 0;

        // Get all transactions in the range
        $transactions = FinancialRecord::forCompany()
            ->whereBetween('date', [$request->start_date, $request->end_date])
            ->with('entryType', 'exitType')
            ->orderBy('date')
            ->get()
            ->groupBy(function($record) {
                return $record->date->format('Y-m-d');
            });

        // Build progression
        $progression = [];
        $currentBalance = $baseAmount;

        foreach ($transactions as $date => $records) {
            $dayTotal = 0;
            foreach ($records as $record) {
                if ($record->entry_type_id) {
                    $dayTotal += $record->value;
                } else {
                    $dayTotal -= $record->value;
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
            ->whereNotNull('entry_type_id')
            ->sum('value');

        $exits = FinancialRecord::forCompany()
            ->whereYear('date', $year)
            ->whereMonth('date', $month)
            ->whereNotNull('exit_type_id')
            ->sum('value');

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
            ->whereNotNull('entry_type_id')
            ->sum('value');

        $exits = FinancialRecord::forCompany()
            ->whereYear('date', $year)
            ->whereNotNull('exit_type_id')
            ->sum('value');

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
