<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;

class HistoryController extends Controller
{
    /**
     * Listar histórico de auditoria
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $company = $user->company;

        if (!$company) {
            return response()->json(['error' => 'Empresa não encontrada'], 403);
        }

        $query = AuditLog::forCompany($company->id);

        // Filtros
        if ($request->has('action')) {
            $query->byAction($request->action);
        }

        if ($request->has('model')) {
            $query->byModel($request->model);
        }

        if ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('model', 'like', "%$search%")
                  ->orWhere('endpoint', 'like', "%$search%")
                  ->orWhere('ip_address', 'like', "%$search%");
            });
        }

        // Data range
        if ($request->has('from_date')) {
            $query->where('created_at', '>=', $request->from_date);
        }

        if ($request->has('to_date')) {
            $query->where('created_at', '<=', $request->to_date . ' 23:59:59');
        }

        // Paginação
        $perPage = min($request->get('per_page', 20), 100);
        $logs = $query->with('user')
                     ->orderByDesc('created_at')
                     ->paginate($perPage);

        return response()->json([
            'data' => $logs->items(),
            'pagination' => [
                'total' => $logs->total(),
                'per_page' => $logs->perPage(),
                'current_page' => $logs->currentPage(),
                'last_page' => $logs->lastPage(),
            ]
        ]);
    }

    /**
     * Ver detalhes de um log específico
     */
    public function show(Request $request, $id)
    {
        $user = $request->user();
        $company = $user->company;

        if (!$company) {
            return response()->json(['error' => 'Empresa não encontrada'], 403);
        }

        $log = AuditLog::forCompany($company->id)
                       ->with('user')
                       ->find($id);

        if (!$log) {
            return response()->json(['error' => 'Registro não encontrado'], 404);
        }

        // Enriquecer com informações extras
        $logData = $log->toArray();
        $logData['action_label'] = $log->getActionLabel();
        $logData['model_label'] = $log->getModelLabel();
        $logData['icon'] = $log->getIconForAction();

        return response()->json(['data' => $logData]);
    }

    /**
     * Estatísticas do histórico
     */
    public function stats(Request $request)
    {
        $user = $request->user();
        $company = $user->company;

        if (!$company) {
            return response()->json(['error' => 'Empresa não encontrada'], 403);
        }

        $daysAgo = $request->get('days', 30);
        $baseQuery = AuditLog::forCompany($company->id)->recent($daysAgo);

        $stats = [
            'total' => $baseQuery->count(),
            'by_action' => [
                'create' => $baseQuery->clone()->byAction('create')->count(),
                'update' => $baseQuery->clone()->byAction('update')->count(),
                'delete' => $baseQuery->clone()->byAction('delete')->count(),
                'restore' => $baseQuery->clone()->byAction('restore')->count(),
            ],
            'by_model' => $baseQuery->clone()
                                   ->selectRaw('model, COUNT(*) as count')
                                   ->groupBy('model')
                                   ->get()
                                   ->map(function($item) {
                                       return [
                                           'model' => $item->model,
                                           'model_label' => (new AuditLog(['model' => $item->model]))->getModelLabel(),
                                           'count' => $item->count,
                                       ];
                                   })
                                   ->toArray(),
            'by_user' => $baseQuery->clone()
                                  ->selectRaw('user_id, COUNT(*) as count')
                                  ->with('user:id,name')
                                  ->groupBy('user_id')
                                  ->get()
                                  ->map(function($item) {
                                      return [
                                          'user_id' => $item->user_id,
                                          'user_name' => $item->user?->name ?? 'Desconhecido',
                                          'count' => $item->count,
                                      ];
                                  })
                                  ->toArray(),
        ];

        return response()->json(['data' => $stats]);
    }

    /**
     * Histórico de um modelo específico
     */
    public function modelHistory(Request $request)
    {
        $user = $request->user();
        $company = $user->company;

        if (!$company) {
            return response()->json(['error' => 'Empresa não encontrada'], 403);
        }

        $model = $request->query('model');
        $modelId = $request->query('model_id');

        if (!$model || !$modelId) {
            return response()->json(['error' => 'Parâmetros model e model_id são obrigatórios'], 400);
        }

        $logs = AuditLog::forCompany($company->id)
                       ->where('model', $model)
                       ->where('model_id', $modelId)
                       ->with('user')
                       ->orderByDesc('created_at')
                       ->paginate(50);

        return response()->json([
            'data' => $logs->items(),
            'pagination' => [
                'total' => $logs->total(),
                'per_page' => $logs->perPage(),
                'current_page' => $logs->currentPage(),
                'last_page' => $logs->lastPage(),
            ]
        ]);
    }

    /**
     * Exportar histórico (futuro)
     */
    public function export(Request $request)
    {
        // TODO: Implementar exportação em CSV/Excel
        return response()->json(['message' => 'Em desenvolvimento']);
    }
}
