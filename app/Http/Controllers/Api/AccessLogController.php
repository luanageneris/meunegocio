<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AccessLogController extends Controller
{
    private function accessLogs(): Builder
    {
        return AuditLog::query()->whereIn('action', ['login', 'login_failed']);
    }

    public function index(): JsonResponse
    {
        $logs = $this->accessLogs()->with('user:id,name,email')
            ->orderByDesc('created_at')->orderByDesc('id')->paginate(20);

        return response()->json([
            'data' => collect($logs->items())->map(fn (AuditLog $log) => $this->presentLog($log)),
            'pagination' => [
                'total' => $logs->total(),
                'per_page' => $logs->perPage(),
                'current_page' => $logs->currentPage(),
                'last_page' => $logs->lastPage(),
            ],
        ]);
    }

    public function statistics(): JsonResponse
    {
        $today = now()->startOfDay();
        $dailyStart = $today->copy()->subDays(29);
        $monthlyStart = $today->copy()->startOfMonth()->subMonths(11);

        $counts = $this->accessLogs()
            ->where('created_at', '>=', $monthlyStart)
            ->where('created_at', '<', $today->copy()->addDay())
            ->selectRaw('DATE(created_at) as day, COUNT(*) as total')
            ->groupByRaw('DATE(created_at)')
            ->pluck('total', 'day');

        $daily = [];
        for ($date = $today->copy(); $date->gte($dailyStart); $date->subDay()) {
            $key = $date->toDateString();
            $daily[] = ['date' => $key, 'count' => (int) ($counts[$key] ?? 0)];
        }

        $monthlyCounts = [];
        foreach ($counts as $day => $count) {
            $month = substr($day, 0, 7);
            $monthlyCounts[$month] = ($monthlyCounts[$month] ?? 0) + (int) $count;
        }

        $monthly = [];
        for ($date = $today->copy()->startOfMonth(); $date->gte($monthlyStart); $date->subMonth()) {
            $key = $date->format('Y-m');
            $monthly[] = ['month' => $key, 'count' => $monthlyCounts[$key] ?? 0];
        }

        return response()->json(['data' => ['daily' => $daily, 'monthly' => $monthly]]);
    }

    public function export(): StreamedResponse
    {
        return response()->streamDownload(function (): void {
            $spreadsheet = new Spreadsheet();
            try {
                $sheet = $spreadsheet->getActiveSheet();
                $sheet->setTitle('Logs de acesso');
                $sheet->fromArray(['Usuário', 'E-mail', 'IP', 'Status', 'Data/Hora (UTC)'], null, 'A1');
                $sheet->getStyle('A1:E1')->getFont()->setBold(true);

                $rowNumber = 2;
                foreach ($this->accessLogs()->with('user:id,name,email')->orderByDesc('created_at')->orderByDesc('id')->lazy() as $log) {
                    $row = $this->presentLog($log);
                    $values = [$row['user_name'], $row['user_email'], $row['ip_address'], $row['status'] === 'success' ? 'Sucesso' : 'Falha', $log->created_at->format('d/m/Y H:i:s')];
                    foreach ($values as $index => $value) {
                        $sheet->setCellValueExplicit(chr(65 + $index).$rowNumber, (string) $value, DataType::TYPE_STRING);
                    }
                    $rowNumber++;
                }

                foreach (['A' => 28, 'B' => 36, 'C' => 20, 'D' => 14, 'E' => 23] as $column => $width) {
                    $sheet->getColumnDimension($column)->setWidth($width);
                }
                (new Xlsx($spreadsheet))->save('php://output');
            } finally {
                $spreadsheet->disconnectWorksheets();
            }
        }, 'logs-de-acesso.xlsx', ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }

    private function presentLog(AuditLog $log): array
    {
        return [
            'id' => $log->id,
            'user_id' => $log->user_id,
            'user_name' => $log->user?->name ?? 'Usuário removido',
            'user_email' => $log->user?->email ?? '',
            'ip_address' => $log->ip_address ?? '',
            'status' => $log->action === 'login' ? 'success' : 'failed',
            'attempted_at' => $log->created_at->toISOString(),
        ];
    }

}
