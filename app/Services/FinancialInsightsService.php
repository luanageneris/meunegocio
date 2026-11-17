<?php

namespace App\Services;

use App\Models\EntryType;
use App\Models\ExitType;
use App\Models\FinancialRecord;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class FinancialInsightsService
{
    private const REQUIRED_ACTIVE_DAYS = 30;

    private const ELIGIBILITY_WINDOW_DAYS = 90;

    public function opportunities(
        int $companyId,
        Carbon $startDate,
        Carbon $endDate,
        array $category,
        array $openingDays,
    ): array {
        $eligibility = $this->eligibility($companyId);
        $categories = $this->categoryOptions($companyId);

        $result = [
            'eligible' => $eligibility['eligible'],
            'eligibility' => $eligibility,
            'period' => [
                'start_date' => $startDate->toDateString(),
                'end_date' => $endDate->toDateString(),
            ],
            'selected_category' => $category,
            'categories' => $categories,
            'period_has_activity' => false,
            'insights' => [],
        ];

        if (! $eligibility['eligible']) {
            return $result;
        }

        $periodDays = $startDate->diffInDays($endDate) + 1;
        $previousEnd = $startDate->copy()->subDay();
        $previousStart = $previousEnd->copy()->subDays($periodDays - 1);

        $currentEntries = $this->sumType($companyId, 'entry', $startDate, $endDate, $category);
        $currentExits = $this->sumType($companyId, 'exit', $startDate, $endDate, $category);
        $result['period_has_activity'] = $currentEntries > 0 || $currentExits > 0;
        if (! $result['period_has_activity']) {
            return $result;
        }

        $previousEntries = $this->sumType($companyId, 'entry', $previousStart, $previousEnd, $category);
        $previousExits = $this->sumType($companyId, 'exit', $previousStart, $previousEnd, $category);
        $weekdayData = $this->weekdayAverages($companyId, $startDate, $endDate, $category, $openingDays);

        $result['insights'] = [
            'revenue_trend' => $this->revenueTrend($currentEntries, $previousEntries),
            'weekday_opportunity' => $this->weekdayOpportunity($weekdayData, $endDate, $periodDays),
            'cash_flow' => $this->cashFlow($currentEntries, $currentExits, $previousEntries, $previousExits),
        ];

        return $result;
    }

    public function curiosity(int $companyId, string $kind, int $month, int $year): array
    {
        $eligibility = $this->eligibility($companyId);
        $comparisonThroughDay = $this->comparisonThroughDay($month, $year);
        $result = [
            'eligible' => $eligibility['eligible'],
            'eligibility' => $eligibility,
            'available' => false,
            'message' => null,
            'comparison_through_day' => $comparisonThroughDay,
            'current_month_progress' => null,
        ];

        if (! $eligibility['eligible']) {
            $result['message'] = 'São necessários registos em pelo menos 30 dias diferentes nos últimos 90 dias.';

            return $result;
        }

        if ($kind === 'best-day') {
            return array_merge($result, $this->bestDay($companyId, $month, $year));
        }

        $history = $this->monthlyHistory($companyId, $month, $year, $comparisonThroughDay);
        if ($history->isEmpty()) {
            $result['message'] = 'Ainda não há registos deste mês em anos anteriores para fazer esta comparação.';
            $result['current_month_progress'] = $this->currentMonthProgress($companyId, $month, $year);

            return $result;
        }

        if ($kind === 'monthly-average') {
            return array_merge($result, [
                'available' => true,
                'average_entries' => round((float) $history->avg('entries'), 2),
                'average_exits' => round((float) $history->avg('exits'), 2),
                'years_count' => $history->count(),
                'years' => $history->pluck('year')->all(),
            ]);
        }

        if ($kind === 'monthly-ranking') {
            $ranked = $history
                ->sortByDesc('entries')
                ->values()
                ->map(function (array $item, int $index) use ($history): array {
                    $higherTotals = $history->where('entries', '>', $item['entries'])->count();

                    return [
                        ...$item,
                        'rank' => $higherTotals + 1,
                    ];
                });
            $latestYear = (int) $history->max('year');
            $latestMonth = $ranked->firstWhere('year', $latestYear);

            return array_merge($result, [
                'available' => true,
                'month' => $month,
                'latest_comparable' => $latestMonth,
                'ranked_history' => $ranked->sortBy('year')->values()->all(),
                'years_count' => $history->count(),
            ]);
        }

        return array_merge($result, [
            'available' => true,
            'weekdays' => $this->historicalWeekdayAverages(
                $companyId,
                $month,
                $year,
                $history,
                $comparisonThroughDay,
            ),
            'years_count' => $history->count(),
            'years' => $history->pluck('year')->all(),
        ]);
    }

    private function comparisonThroughDay(int $month, int $year): ?int
    {
        $today = now();

        return $year === (int) $today->year && $month === (int) $today->month
            ? (int) $today->day
            : null;
    }

    private function currentMonthProgress(int $companyId, int $month, int $year): ?array
    {
        $today = now();
        if (
            $year !== (int) $today->year
            || $month !== (int) $today->month
            || $today->copy()->endOfMonth()->day <= $today->day
        ) {
            return null;
        }

        $throughDay = (int) $today->day;
        $finalAmount = FinancialRecord::FINAL_AMOUNT_SQL;
        $monthlyTotals = FinancialRecord::forCompany($companyId)
            ->where('type', 'entry')
            ->whereYear('date', $year)
            ->whereMonth('date', '<=', $month)
            ->whereDay('date', '<=', $throughDay)
            ->selectRaw('MONTH(`date`) as month_number, COALESCE(SUM('.$finalAmount.'), 0) as entries')
            ->groupBy('month_number')
            ->get()
            ->mapWithKeys(fn ($row) => [(int) $row->month_number => (float) $row->entries]);

        $yearToDateMonths = collect(range(1, $month))
            ->mapWithKeys(fn (int $monthNumber) => [
                $monthNumber => round((float) ($monthlyTotals[$monthNumber] ?? 0), 2),
            ]);
        $entries = (float) ($yearToDateMonths[$month] ?? 0);
        $rank = $entries > 0
            ? $yearToDateMonths->filter(fn (float $total) => $total > $entries)->count() + 1
            : null;

        return [
            'entries' => round($entries, 2),
            'exits' => FinancialRecord::sumFinalAmount(
                FinancialRecord::forCompany($companyId)
                    ->where('type', 'exit')
                    ->whereBetween('date', [$today->copy()->startOfMonth()->toDateString(), $today->toDateString()]),
            ),
            'through_day' => $throughDay,
            'rank' => $rank,
            'months_compared' => $month,
            'weekday_averages' => $this->currentMonthWeekdayAverages($companyId, $today),
        ];
    }

    private function currentMonthWeekdayAverages(int $companyId, Carbon $today): array
    {
        $start = $today->copy()->startOfMonth()->startOfDay();
        $end = $today->copy()->startOfDay();
        $dailyTotals = FinancialRecord::forCompany($companyId)
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->selectRaw("DATE(`date`) as day, COALESCE(SUM(CASE WHEN `type` = 'entry' THEN ".FinancialRecord::FINAL_AMOUNT_SQL.' ELSE 0 END), 0) as entries')
            ->selectRaw("COALESCE(SUM(CASE WHEN `type` = 'exit' THEN ".FinancialRecord::FINAL_AMOUNT_SQL.' ELSE 0 END), 0) as exits')
            ->groupBy('day')
            ->get()
            ->keyBy('day');

        $totals = array_fill(0, 7, 0.0);
        $exitTotals = array_fill(0, 7, 0.0);
        $occurrences = array_fill(0, 7, 0);

        for ($date = $start->copy(); $date->lessThanOrEqualTo($end); $date->addDay()) {
            $weekday = $date->dayOfWeek;
            $totals[$weekday] += (float) ($dailyTotals[$date->toDateString()]->entries ?? 0);
            $exitTotals[$weekday] += (float) ($dailyTotals[$date->toDateString()]->exits ?? 0);
            $occurrences[$weekday]++;
        }

        $weekdays = [];
        for ($weekday = 0; $weekday <= 6; $weekday++) {
            $weekdays[] = [
                'day' => $weekday,
                'label' => ['Domingo', 'Segunda-feira', 'Terça-feira', 'Quarta-feira', 'Quinta-feira', 'Sexta-feira', 'Sábado'][$weekday],
                'average_entries' => $occurrences[$weekday] > 0
                    ? round($totals[$weekday] / $occurrences[$weekday], 2)
                    : 0,
                'average_exits' => $occurrences[$weekday] > 0
                    ? round($exitTotals[$weekday] / $occurrences[$weekday], 2)
                    : 0,
            ];
        }

        return $weekdays;
    }

    private function eligibility(int $companyId): array
    {
        $today = now()->startOfDay();
        $start = $today->copy()->subDays(self::ELIGIBILITY_WINDOW_DAYS - 1);
        $activeDays = (int) FinancialRecord::forCompany($companyId)
            ->whereBetween('date', [$start->toDateString(), $today->toDateString()])
            ->selectRaw('COUNT(DISTINCT DATE(`date`)) as active_days')
            ->value('active_days');

        return [
            'active_days' => $activeDays,
            'required_active_days' => self::REQUIRED_ACTIVE_DAYS,
            'window_days' => self::ELIGIBILITY_WINDOW_DAYS,
            'eligible' => $activeDays >= self::REQUIRED_ACTIVE_DAYS,
        ];
    }

    private function categoryOptions(int $companyId): array
    {
        $options = [['value' => 'all', 'label' => 'Todas as categorias']];

        foreach (EntryType::forCompany($companyId)->where('active', true)->orderBy('name')->get(['id', 'name']) as $type) {
            $options[] = [
                'value' => 'entry:'.$type->id,
                'label' => 'Entrada: '.$type->name,
            ];
        }

        foreach (ExitType::forCompany($companyId)->where('active', true)->orderBy('name')->get(['id', 'name']) as $type) {
            $options[] = [
                'value' => 'exit:'.$type->id,
                'label' => 'Saída: '.$type->name,
            ];
        }

        return $options;
    }

    private function sumType(int $companyId, string $type, Carbon $start, Carbon $end, array $category): float
    {
        return FinancialRecord::sumFinalAmount(
            $this->recordsForType($companyId, $type, $start, $end, $category),
        );
    }

    private function recordsForType(
        int $companyId,
        string $type,
        Carbon $start,
        Carbon $end,
        array $category,
    ): Builder {
        $query = FinancialRecord::forCompany($companyId)
            ->where('type', $type)
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()]);

        if ($category['type'] === $type && $category['id'] !== null) {
            $foreignKey = $type === 'entry' ? 'entry_type_id' : 'exit_type_id';
            $query->where($foreignKey, $category['id']);
        }

        return $query;
    }

    private function weekdayAverages(
        int $companyId,
        Carbon $start,
        Carbon $end,
        array $category,
        array $openingDays,
    ): array {
        $dailyTotals = $this->recordsForType($companyId, 'entry', $start, $end, $category)
            ->selectRaw('DATE(`date`) as day, COALESCE(SUM('.FinancialRecord::FINAL_AMOUNT_SQL.'), 0) as total')
            ->groupBy('day')
            ->get()
            ->mapWithKeys(fn ($row) => [$row->day => (float) $row->total]);

        $totals = array_fill(0, 7, 0.0);
        $occurrences = array_fill(0, 7, 0);

        for ($date = $start->copy()->startOfDay(); $date->lessThanOrEqualTo($end); $date->addDay()) {
            $weekday = $date->dayOfWeek;
            $totals[$weekday] += (float) ($dailyTotals[$date->toDateString()] ?? 0);
            $occurrences[$weekday]++;
        }

        $openWeekdays = collect($openingDays)
            ->map(fn ($weekday) => (int) $weekday)
            ->filter(fn (int $weekday) => $weekday >= 0 && $weekday <= 6)
            ->unique()
            ->sort()
            ->values();

        if ($openWeekdays->isEmpty()) {
            $openWeekdays = collect(range(0, 6));
        }

        $weekdays = [];
        foreach ($openWeekdays as $weekday) {
            $weekdays[] = [
                'day' => $weekday,
                'label' => ['Domingo', 'Segunda-feira', 'Terça-feira', 'Quarta-feira', 'Quinta-feira', 'Sexta-feira', 'Sábado'][$weekday],
                'average_entries' => $occurrences[$weekday] > 0
                    ? round($totals[$weekday] / $occurrences[$weekday], 2)
                    : 0,
            ];
        }

        return $weekdays;
    }

    private function revenueTrend(float $current, float $previous): array
    {
        $difference = $current - $previous;
        $direction = $difference > 0 ? 'up' : ($difference < 0 ? 'down' : 'stable');
        $impact = $direction === 'down'
            ? $this->impactRange(abs($difference))
            : null;

        return [
            'current_entries' => round($current, 2),
            'previous_entries' => round($previous, 2),
            'difference' => round($difference, 2),
            'change_percent' => $previous > 0 ? round(($difference / $previous) * 100, 1) : null,
            'direction' => $direction,
            'recommendation' => $direction === 'down'
                ? 'As entradas diminuíram. Reveja os dias e serviços que tiveram menos movimento e escolha uma ação simples para testar.'
                : ($direction === 'up'
                    ? 'As entradas aumentaram. Identifique o que contribuiu para esse resultado e veja se é possível repetir.'
                    : 'As entradas ficaram estáveis. Observe os dias com menor movimento para encontrar uma oportunidade de melhoria.'),
            'estimated_impact' => $impact,
        ];
    }

    private function weekdayOpportunity(array $weekdays, Carbon $endDate, int $periodDays): array
    {
        $sorted = collect($weekdays)->sortBy('average_entries')->values();
        $weakest = $sorted->first();
        $strongest = $sorted->last();
        $gap = max(0, (float) $strongest['average_entries'] - (float) $weakest['average_entries']);
        $occurrences = 0;
        $projectionEnd = $endDate->copy()->addDays($periodDays);

        for ($date = $endDate->copy()->addDay(); $date->lessThanOrEqualTo($projectionEnd); $date->addDay()) {
            if ($date->dayOfWeek === (int) $weakest['day']) {
                $occurrences++;
            }
        }

        return [
            'weakest_weekday' => $weakest,
            'strongest_weekday' => $strongest,
            'weekday_averages' => $weekdays,
            'recommendation' => $gap > 0
                ? 'O dia com menor média é '.strtolower($weakest['label']).'. Considere testar uma divulgação ou oferta nesse dia e compare o resultado.'
                : 'As médias de entradas são parecidas entre os dias da semana; continue acompanhando para identificar diferenças.',
            'estimated_impact' => $gap > 0
                ? $this->impactRange($gap * $occurrences)
                : null,
        ];
    }

    private function cashFlow(float $entries, float $exits, float $previousEntries, float $previousExits): array
    {
        $net = $entries - $exits;
        $previousNet = $previousEntries - $previousExits;
        $additionalExits = max(0, $exits - $previousExits);

        return [
            'entries' => round($entries, 2),
            'exits' => round($exits, 2),
            'net' => round($net, 2),
            'previous_net' => round($previousNet, 2),
            'recommendation' => $net < 0
                ? 'As saídas superaram as entradas neste período. Reveja as despesas e planeie os pagamentos dos próximos dias.'
                : 'As entradas cobriram as saídas neste período. Continue acompanhando as despesas para manter esse equilíbrio.',
            'estimated_impact' => $additionalExits > 0
                ? $this->impactRange($additionalExits)
                : null,
        ];
    }

    private function impactRange(float $observedDifference): array
    {
        return [
            'minimum' => round($observedDifference * 0.25, 2),
            'maximum' => round($observedDifference * 0.5, 2),
            'basis' => 'Faixa de referência calculada a partir de uma parte da diferença observada nos registos; não é uma promessa de resultado.',
        ];
    }

    private function monthlyHistory(
        int $companyId,
        int $month,
        int $selectedYear,
        ?int $comparisonThroughDay,
    ): Collection {
        $finalAmount = FinancialRecord::FINAL_AMOUNT_SQL;
        $entryAmount = "CASE WHEN `type` = 'entry' THEN {$finalAmount} ELSE 0 END";
        $exitAmount = "CASE WHEN `type` = 'exit' THEN {$finalAmount} ELSE 0 END";

        $query = FinancialRecord::forCompany($companyId)
            ->whereMonth('date', $month)
            ->whereRaw('YEAR(`date`) < ?', [$selectedYear]);

        if ($comparisonThroughDay !== null) {
            $query->whereDay('date', '<=', $comparisonThroughDay);
        }

        return $query
            ->selectRaw('YEAR(`date`) as year, COALESCE(SUM('.$entryAmount.'), 0) as entries')
            ->selectRaw('COALESCE(SUM('.$exitAmount.'), 0) as exits')
            ->groupBy('year')
            ->orderBy('year')
            ->get()
            ->map(fn ($row) => [
                'year' => (int) $row->year,
                'entries' => round((float) $row->entries, 2),
                'exits' => round((float) $row->exits, 2),
            ]);
    }

    private function historicalWeekdayAverages(
        int $companyId,
        int $month,
        int $selectedYear,
        Collection $history,
        ?int $comparisonThroughDay,
    ): array {
        $finalAmount = FinancialRecord::FINAL_AMOUNT_SQL;
        $query = FinancialRecord::forCompany($companyId)
            ->whereMonth('date', $month)
            ->whereRaw('YEAR(`date`) < ?', [$selectedYear]);

        if ($comparisonThroughDay !== null) {
            $query->whereDay('date', '<=', $comparisonThroughDay);
        }

        $dailyTotals = $query
            ->selectRaw("DATE(`date`) as day, COALESCE(SUM(CASE WHEN `type` = 'entry' THEN ".$finalAmount.' ELSE 0 END), 0) as entries')
            ->selectRaw("COALESCE(SUM(CASE WHEN `type` = 'exit' THEN ".$finalAmount.' ELSE 0 END), 0) as exits')
            ->groupBy('day')
            ->get()
            ->keyBy('day');

        $totals = array_fill(0, 7, 0.0);
        $exitTotals = array_fill(0, 7, 0.0);
        $occurrences = array_fill(0, 7, 0);

        foreach ($history as $item) {
            $monthStart = Carbon::create((int) $item['year'], $month, 1)->startOfMonth();
            $lastDay = $comparisonThroughDay === null
                ? (int) $monthStart->daysInMonth
                : min((int) $monthStart->daysInMonth, $comparisonThroughDay);
            $monthEnd = $monthStart->copy()->day($lastDay);

            for ($date = $monthStart->copy(); $date->lessThanOrEqualTo($monthEnd); $date->addDay()) {
                $weekday = $date->dayOfWeek;
                $totals[$weekday] += (float) ($dailyTotals[$date->toDateString()]->entries ?? 0);
                $exitTotals[$weekday] += (float) ($dailyTotals[$date->toDateString()]->exits ?? 0);
                $occurrences[$weekday]++;
            }
        }

        $weekdays = [];
        for ($weekday = 0; $weekday <= 6; $weekday++) {
            $weekdays[] = [
                'day' => $weekday,
                'label' => ['Domingo', 'Segunda-feira', 'Terça-feira', 'Quarta-feira', 'Quinta-feira', 'Sexta-feira', 'Sábado'][$weekday],
                'average_entries' => $occurrences[$weekday] > 0
                    ? round($totals[$weekday] / $occurrences[$weekday], 2)
                    : 0,
                'average_exits' => $occurrences[$weekday] > 0
                    ? round($exitTotals[$weekday] / $occurrences[$weekday], 2)
                    : 0,
            ];
        }

        return $weekdays;
    }

    private function bestDay(int $companyId, int $month, int $year): array
    {
        $start = Carbon::create($year, 1, 1)->startOfDay();
        $selectedMonthEnd = Carbon::create($year, $month, 1)->endOfMonth()->endOfDay();
        $end = $selectedMonthEnd->lessThan(now()->endOfDay()) ? $selectedMonthEnd : now()->endOfDay();

        if ($end->lessThan($start)) {
            return [
                'available' => false,
                'message' => 'Ainda não há registos neste ano até ao mês selecionado.',
            ];
        }

        $bestDay = FinancialRecord::forCompany($companyId)
            ->where('type', 'entry')
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->selectRaw('DATE(`date`) as day, COALESCE(SUM('.FinancialRecord::FINAL_AMOUNT_SQL.'), 0) as total')
            ->groupBy('day')
            ->orderByDesc('total')
            ->orderBy('day')
            ->first();

        if (! $bestDay || (float) $bestDay->total <= 0) {
            return [
                'available' => false,
                'message' => 'Ainda não há entradas registadas neste ano até ao mês selecionado.',
            ];
        }

        return [
            'available' => true,
            'best_day' => [
                'date' => $bestDay->day,
                'entries' => round((float) $bestDay->total, 2),
            ],
        ];
    }
}
