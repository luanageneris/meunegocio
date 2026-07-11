<?php

namespace App\Http\Controllers\Api;

use App\Models\Commission;
use App\Models\FinancialRecord;
use App\Http\Requests\StoreFinancialRecordRequest;
use App\Http\Requests\UpdateFinancialRecordRequest;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class FinancialRecordController
{
    /**
     * List financial records for current company
     */
    public function index(Request $request)
    {
        $baseQuery = FinancialRecord::forCompany();
        $this->applyTrashFilter($baseQuery, $request);

        $records = (clone $baseQuery)
            ->with('collaborator', 'entryType', 'exitType', 'serviceType')
            ->orderBy('date', 'desc')
            ->paginate(15);

        $totals = $this->calculateFinancialTotals(clone $baseQuery);

        $commissionQuery = Commission::forCompany();
        $this->applyTrashFilter($commissionQuery, $request);
        $totals['commissions'] = (float) $commissionQuery->sum('amount');
        $totals['final_total'] = $totals['entries'] - $totals['exits'] + $totals['commissions'];

        return response()->json([
            'data' => $records->items(),
            'pagination' => [
                'total' => $records->total(),
                'per_page' => $records->perPage(),
                'current_page' => $records->currentPage(),
            ],
            'totals' => $totals,
        ]);
    }

    /**
     * Store a new financial record
     */
    public function store(StoreFinancialRecordRequest $request)
    {
        $record = FinancialRecord::create($request->validated());
        
        return response()->json(['data' => $record->load('collaborator', 'entryType', 'exitType', 'serviceType')], 201);
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

        return response()->json(['data' => $financialRecord->load('collaborator', 'entryType', 'exitType', 'serviceType')]);
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
     * Restore a soft-deleted financial record
     */
    public function restore(Request $request, $id)
    {
        $record = FinancialRecord::withTrashed()->forCompany()->findOrFail($id);

        if ($record->company_id !== $request->user()->company_id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if (!$request->user()->isRoot()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if (is_null($record->deleted_at)) {
            return response()->json(['message' => 'Registro não está removido'], 422);
        }

        $record->restore();

        return response()->json(['data' => $record->fresh()->load('collaborator', 'entryType', 'exitType', 'serviceType')]);
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

        return response()->json(['data' => $records]);
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

        $baseQuery = FinancialRecord::forCompany()
            ->whereBetween('date', [$request->start_date, $request->end_date]);
        $this->applyTrashFilter($baseQuery, $request);

        $records = (clone $baseQuery)
            ->with('collaborator', 'entryType', 'exitType', 'serviceType')
            ->orderBy('date', 'desc')
            ->paginate(50);

        $totals = $this->calculateFinancialTotals(clone $baseQuery);

        $commissionQuery = Commission::forCompany()
            ->whereBetween('date', [$request->start_date, $request->end_date]);
        $this->applyTrashFilter($commissionQuery, $request);
        $totals['commissions'] = (float) $commissionQuery->sum('amount');
        $totals['final_total'] = $totals['entries'] - $totals['exits'] + $totals['commissions'];

        return response()->json([
            'data' => $records->items(),
            'pagination' => [
                'total' => $records->total(),
                'per_page' => $records->perPage(),
                'current_page' => $records->currentPage(),
            ],
            'totals' => $totals,
        ]);
    }

    /**
     * Export financial records and commissions for a date range
     */
    public function exportBetweenDates(Request $request)
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        $records = FinancialRecord::forCompany()
            ->whereBetween('date', [$request->start_date, $request->end_date])
            ->with('collaborator', 'entryType', 'exitType', 'serviceType')
            ->orderBy('date', 'asc')
            ->orderBy('id', 'asc')
            ->get();

        $commissions = Commission::forCompany()
            ->whereBetween('date', [$request->start_date, $request->end_date])
            ->with('collaborator')
            ->orderBy('date', 'asc')
            ->orderBy('id', 'asc')
            ->get();

        $spreadsheet = new Spreadsheet();

        $recordsSheet = $spreadsheet->getActiveSheet();
        $recordsSheet->setTitle('Registros');
        $recordsSheet->fromArray([
            'Data',
            'Tipo',
            'Categoria',
            'Colaborador',
            'Servico',
            'Valor unitario',
            'Quantidade',
            'Desconto',
            'Valor final',
            'Descricao',
            'Registrado em',
        ], null, 'A1');

        $recordsRow = 2;
        foreach ($records as $record) {
            $recordsSheet->setCellValue("A{$recordsRow}", optional($record->date)->format('d/m/Y') ?? '');
            $recordsSheet->setCellValue("B{$recordsRow}", $record->type === 'entry' ? 'Entrada' : 'Saida');
            $recordsSheet->setCellValue("C{$recordsRow}", $record->entryType?->name ?? $record->exitType?->name ?? '');
            $recordsSheet->setCellValue("D{$recordsRow}", $record->collaborator?->name ?? '');
            $recordsSheet->setCellValue("E{$recordsRow}", $record->serviceType?->name ?? '');
            $recordsSheet->setCellValue("F{$recordsRow}", (float) $record->amount);
            $recordsSheet->setCellValue("G{$recordsRow}", (int) ($record->quantity ?? 1));
            $recordsSheet->setCellValue("H{$recordsRow}", (float) ($record->discount ?? 0));
            $recordsSheet->setCellValue("I{$recordsRow}", (float) $record->final_amount);
            $recordsSheet->setCellValue("J{$recordsRow}", (string) ($record->description ?? ''));
            $recordsSheet->setCellValue("K{$recordsRow}", optional($record->created_at)?->format('d/m/Y H:i') ?? '');
            $recordsRow++;
        }

        $brlFormat = '[$R$-416] #,##0.00';

        if ($recordsRow > 2) {
            $recordsSheet->getStyle("F2:F" . ($recordsRow - 1))->getNumberFormat()->setFormatCode($brlFormat);
            $recordsSheet->getStyle("H2:I" . ($recordsRow - 1))->getNumberFormat()->setFormatCode($brlFormat);
        }

        foreach (range('A', 'K') as $column) {
            $recordsSheet->getColumnDimension($column)->setAutoSize(true);
        }

        $summarySheet = $spreadsheet->createSheet();
        $summarySheet->setTitle('Resumo');

        $entriesTotal = (float) $records->where('type', 'entry')->sum(function (FinancialRecord $record) {
            return $record->final_amount;
        });
        $exitsTotal = (float) $records->where('type', 'exit')->sum(function (FinancialRecord $record) {
            return $record->final_amount;
        });
        $commissionsTotal = (float) $commissions->sum('amount');
        $finalTotal = $entriesTotal - $exitsTotal + $commissionsTotal;

        $summarySheet->fromArray([
            ['Filtro inicio', $request->start_date],
            ['Filtro fim', $request->end_date],
            ['Entradas', $entriesTotal],
            ['Saidas', $exitsTotal],
            ['Comissoes', $commissionsTotal],
            ['Total final', $finalTotal],
            ['Registros', $records->count()],
            ['Comissoes registradas', $commissions->count()],
        ], null, 'A1');

        $summarySheet->getStyle('B3:B6')->getNumberFormat()->setFormatCode($brlFormat);
        $summarySheet->getColumnDimension('A')->setAutoSize(true);
        $summarySheet->getColumnDimension('B')->setAutoSize(true);

        $commissionsSheet = $spreadsheet->createSheet();
        $commissionsSheet->setTitle('Comissoes');
        $commissionsSheet->fromArray([
            'Data',
            'Colaborador',
            'Valor',
            'Observacao',
            'Pago em',
            'Registrado em',
        ], null, 'A1');

        $commissionsRow = 2;
        foreach ($commissions as $commission) {
            $commissionsSheet->setCellValue("A{$commissionsRow}", optional($commission->date)->format('d/m/Y') ?? '');
            $commissionsSheet->setCellValue("B{$commissionsRow}", $commission->collaborator?->name ?? '');
            $commissionsSheet->setCellValue("C{$commissionsRow}", (float) $commission->amount);
            $commissionsSheet->setCellValue("D{$commissionsRow}", (string) ($commission->notes ?? ''));
            $commissionsSheet->setCellValue("E{$commissionsRow}", optional($commission->paid_at)->format('d/m/Y') ?? '');
            $commissionsSheet->setCellValue("F{$commissionsRow}", optional($commission->created_at)?->format('d/m/Y H:i') ?? '');
            $commissionsRow++;
        }

        if ($commissionsRow > 2) {
            $commissionsSheet->getStyle("C2:C" . ($commissionsRow - 1))->getNumberFormat()->setFormatCode($brlFormat);
        }

        foreach (range('A', 'F') as $column) {
            $commissionsSheet->getColumnDimension($column)->setAutoSize(true);
        }

        $period = (string) $request->query('period', 'periodo');
        $safePeriod = preg_replace('/[^a-z0-9_-]/i', '-', strtolower($period));
        $fileName = sprintf(
            'registros-%s-%s-a-%s.xlsx',
            $safePeriod ?: 'periodo',
            str_replace('-', '', $request->start_date),
            str_replace('-', '', $request->end_date)
        );

        return response()->streamDownload(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    private function applyTrashFilter(Builder $query, Request $request): void
    {
        if ($request->boolean('only_deleted')) {
            $query->onlyTrashed();
        } elseif ($request->boolean('include_deleted')) {
            $query->withTrashed();
        }
    }

    private function calculateFinancialTotals(Builder $query): array
    {
        $entriesQuery = (clone $query)->where('type', 'entry');
        $entries = $this->sumFinalAmount(clone $entriesQuery);
        $exits = $this->sumFinalAmount((clone $query)->where('type', 'exit'));
        $entryDiscounts = FinancialRecord::sumDiscountAmount($entriesQuery);

        return [
            'entries' => $entries,
            'exits' => $exits,
            'entry_discounts' => $entryDiscounts,
        ];
    }

    private function sumFinalAmount(Builder $query): float
    {
        return FinancialRecord::sumFinalAmount($query);
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
            ->whereNotNull('entry_type_id');

        $entries = $this->sumFinalAmount($entries);

        $exits = FinancialRecord::forCompany()
            ->whereDate('date', $date)
            ->whereNotNull('exit_type_id');

        $exits = $this->sumFinalAmount($exits);

        $profit = $entries - $exits;

        return response()->json(['data' => [
            'date' => $date,
            'entries' => $entries,
            'exits' => $exits,
            'profit' => $profit,
            'closed' => false, // TODO: check if day is closed in DailyClosure
        ]]);
    }

    /**
     * Clear all financial records (only root can do this)
     */
    public function clearAll(Request $request)
    {
        if (!$request->user()->isRoot()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $companyId = $request->user()->company_id;

        $financialRecordsCount = FinancialRecord::where('company_id', $companyId)->count();
        $importedCommissionsCount = Commission::where('company_id', $companyId)
            ->whereNull('financial_record_id')
            ->where('notes', 'Comissão importada de planilha')
            ->count();

        DB::transaction(function () use ($companyId) {
            FinancialRecord::where('company_id', $companyId)->delete();

            Commission::where('company_id', $companyId)
                ->whereNull('financial_record_id')
                ->where('notes', 'Comissão importada de planilha')
                ->delete();
        });

        return response()->json([
            'message' => 'Todos os registros e comissões importadas foram removidos com sucesso',
            'count' => $financialRecordsCount + $importedCommissionsCount,
            'financial_records_count' => $financialRecordsCount,
            'imported_commissions_count' => $importedCommissionsCount,
        ]);
    }
}
