<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ImportLog;
use App\Services\FinancialRecordsExcelImportService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Storage;

class ImportController extends Controller
{
    private const TEMPLATE_FILE_NAME = 'template_meu_negocio.xlsx';
    private const TEMPLATE_DOWNLOAD_NAME = 'template-importacao-entradas-saidas.xlsx';

    public function __construct(private FinancialRecordsExcelImportService $financialRecordsExcelImportService)
    {
    }

    public function import(Request $request)
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls'],
            'default_entry_type_id' => [
                'required',
                Rule::exists('entry_types', 'id')->where(function ($query) use ($request) {
                    $query->where('company_id', $request->user()->company_id);
                }),
            ],
            'default_exit_type_id' => [
                'required',
                Rule::exists('exit_types', 'id')->where(function ($query) use ($request) {
                    $query->where('company_id', $request->user()->company_id);
                }),
            ],
            'default_collaborator_id' => [
                'required',
                Rule::exists('collaborators', 'id')->where(function ($query) use ($request) {
                    $query->where('company_id', $request->user()->company_id);
                }),
            ],
        ]);

        $result = $this->financialRecordsExcelImportService->import(
            $request->file('file'),
            (int) $request->user()->company_id,
            (int) $request->user()->id,
            (int) $request->integer('default_entry_type_id'),
            (int) $request->integer('default_exit_type_id'),
            (int) $request->integer('default_collaborator_id')
        );

        return response()->json([
            'data' => $result['payload'],
        ], $result['status']);
    }

    public function logs(Request $request)
    {
        $logs = ImportLog::forCompany()
            ->with('importedByUser:id,name,email')
            ->orderByDesc('created_at')
            ->paginate(20);

        return response()->json([
            'data' => $logs->items(),
            'pagination' => [
                'total' => $logs->total(),
                'per_page' => $logs->perPage(),
                'current_page' => $logs->currentPage(),
            ],
        ]);
    }

    public function template()
    {
        $relativePath = self::TEMPLATE_FILE_NAME;

        if (!Storage::disk('public')->exists($relativePath)) {
            abort(404, 'O template de importação não foi encontrado.');
        }

        $filePath = Storage::disk('public')->path($relativePath);

        return response()->download(
            $filePath,
            self::TEMPLATE_DOWNLOAD_NAME,
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']
        );
    }
}
