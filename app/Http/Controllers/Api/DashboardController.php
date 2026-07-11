<?php

namespace App\Http\Controllers\Api;

use App\Models\Commission;
use App\Models\FinancialRecord;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Carbon;

class DashboardController extends Controller
{
    public function summary(Request $request)
    {
        return response()->json([
            'data' => $this->buildMonthlySummary($request),
        ]);
    }

    public function commissionsByCollaborator(Request $request)
    {
        return response()->json([
            'data' => [],
        ]);
    }

    public function topProfitableDays(Request $request)
    {
        return response()->json([
            'data' => [],
        ]);
    }

    public function entriesExits(Request $request)
    {
        return response()->json([
            'data' => $this->buildMonthlySummary($request),
        ]);
    }

    private function buildMonthlySummary(Request $request): array
    {
        $year = (int) $request->integer('year', now()->year);
        $month = (int) $request->integer('month', now()->month);

        if ($year < 2000) {
            $year = (int) now()->year;
        }

        if ($month < 1 || $month > 12) {
            $month = (int) now()->month;
        }

        $monthNames = [
            1 => 'Janeiro',
            2 => 'Fevereiro',
            3 => 'Março',
            4 => 'Abril',
            5 => 'Maio',
            6 => 'Junho',
            7 => 'Julho',
            8 => 'Agosto',
            9 => 'Setembro',
            10 => 'Outubro',
            11 => 'Novembro',
            12 => 'Dezembro',
        ];

        $monthLabel = sprintf('%s de %d', $monthNames[$month] ?? 'Mês', $year);
        $monthStart = Carbon::create($year, $month, 1)->startOfMonth();
        $monthEnd = (clone $monthStart)->endOfMonth();
        $today = now()->toDateString();

        $entriesByDay = FinancialRecord::forCompany()
            ->byMonth($year, $month)
            ->entry()
            ->selectRaw('DATE(`date`) as day, COALESCE(SUM(' . FinancialRecord::FINAL_AMOUNT_SQL . '), 0) as total')
            ->groupBy('day')
            ->orderBy('day')
            ->get()
            ->mapWithKeys(fn ($item) => [$item->day => (float) $item->total]);

        $exitsByDay = FinancialRecord::forCompany()
            ->byMonth($year, $month)
            ->exit()
            ->selectRaw('DATE(`date`) as day, COALESCE(SUM(' . FinancialRecord::FINAL_AMOUNT_SQL . '), 0) as total')
            ->groupBy('day')
            ->orderBy('day')
            ->get()
            ->mapWithKeys(fn ($item) => [$item->day => (float) $item->total]);

        $days = [];
        $daysWithActivity = 0;

        for ($cursor = $monthStart->copy(); $cursor->lessThanOrEqualTo($monthEnd); $cursor->addDay()) {
            $dayKey = $cursor->toDateString();
            $entries = (float) ($entriesByDay[$dayKey] ?? 0);
            $exits = (float) ($exitsByDay[$dayKey] ?? 0);
            $hasEntries = $entries > 0;
            $hasExits = $exits > 0;
            $net = $entries - $exits;

            $status = 'empty';

            if ($hasEntries || $hasExits) {
                $daysWithActivity++;

                if ($hasEntries && !$hasExits) {
                    $status = 'entry';
                } elseif (!$hasEntries && $hasExits) {
                    $status = 'exit';
                } elseif ($net > 0) {
                    $status = 'positive';
                } elseif ($net < 0) {
                    $status = 'negative';
                } else {
                    $status = 'zero';
                }
            }

            $days[] = [
                'date' => $dayKey,
                'day' => (int) $cursor->day,
                'entries' => $entries,
                'exits' => $exits,
                'net' => $net,
                'status' => $status,
                'has_entries' => $hasEntries,
                'has_exits' => $hasExits,
                'is_today' => $dayKey === $today,
            ];
        }

        $monthEntries = FinancialRecord::sumFinalAmount(
            FinancialRecord::forCompany()->byMonth($year, $month)->entry(),
        );

        $monthExits = FinancialRecord::sumFinalAmount(
            FinancialRecord::forCompany()->byMonth($year, $month)->exit(),
        );

        $monthCommissions = (float) Commission::forCompany()
            ->whereYear('date', $year)
            ->whereMonth('date', $month)
            ->sum('amount');

        $todayEntries = FinancialRecord::sumFinalAmount(
            FinancialRecord::forCompany()->byDate($today)->entry(),
        );

        $todayExits = FinancialRecord::sumFinalAmount(
            FinancialRecord::forCompany()->byDate($today)->exit(),
        );

        $todayNet = $todayEntries - $todayExits;

        return [
            'company' => [
                'name' => $request->user()?->company?->name,
            ],
            'month' => $month,
            'year' => $year,
            'month_label' => $monthLabel,
            'totals' => [
                'entries' => $monthEntries,
                'exits' => $monthExits,
                'commissions' => $monthCommissions,
                'profit' => $monthEntries - $monthExits,
                'days_with_activity' => $daysWithActivity,
            ],
            'today' => [
                'date' => $today,
                'entries' => $todayEntries,
                'exits' => $todayExits,
                'net' => $todayNet,
                'has_activity' => $todayEntries > 0 || $todayExits > 0,
            ],
            'days' => $days,
        ];
    }
}
