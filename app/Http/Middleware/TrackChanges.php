<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Models\AuditLog;

class TrackChanges
{
    protected $actionsToTrack = ['store', 'update', 'destroy', 'restore'];
    
    protected $modelsToTrack = [
        'collaborators' => 'App\Models\Collaborator',
        'financial-records' => 'App\Models\FinancialRecord',
        'commissions' => 'App\Models\Commission',
        'work-shifts' => 'App\Models\WorkShift',
        'service-types' => 'App\Models\ServiceType',
        'entry-types' => 'App\Models\EntryType',
        'exit-types' => 'App\Models\ExitType',
        'daily-closures' => 'App\Models\DailyClosure',
        'balances' => 'App\Models\Balance',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $preState = $this->capturePreState($request);

        $response = $next($request);

        // Só registrar se autenticado e for request de modificação
        if ($request->user() && $this->isModificationRequest($request)) {
            $this->logAudit($request, $response, $preState);
        }

        return $response;
    }

    private function isModificationRequest(Request $request): bool
    {
        $method = $request->getMethod();
        return in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE']);
    }

    private function logAudit(Request $request, Response $response, ?array $preState = null): void
    {
        try {
            $action = $this->getAction($request);
            $modelInfo = $this->getModelInfo($request);

            if (!$modelInfo) {
                return;
            }

            [$modelClass, $modelId] = $modelInfo;
            $user = $request->user();
            $company = $user->company;

            if (!$company) {
                return;
            }

            $oldValues = $preState['data'] ?? $this->getOldValues($request, $modelClass, $modelId);
            $newValues = $this->getNewValues($request, $modelClass, $modelId, $response);

            if (($modelId === 0 || $modelId === null) && is_array($newValues)) {
                $modelId = $newValues['id'] ?? ($newValues['data']['id'] ?? $modelId);
            }
            $changes = $this->calculateChanges($oldValues, $newValues);

            AuditLog::create([
                'company_id' => $company->id,
                'user_id' => $user->id,
                'action' => $action,
                'model' => $modelClass,
                'model_id' => $modelId,
                'old_values' => $oldValues,
                'new_values' => $newValues,
                'changes' => $changes,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'endpoint' => $request->path(),
            ]);
        } catch (\Exception $e) {
            // Log silenciosamente para não quebrar a requisição
            \Log::error('Erro ao registrar auditoria: ' . $e->getMessage());
        }
    }

    private function getAction(Request $request): string
    {
        $method = $request->getMethod();
        $path = $request->path();

        if ($method === 'POST' && !str_contains($path, '/mark-as-paid') && !str_contains($path, '/generate-from-date') && !str_contains($path, '/restore')) {
            return 'create';
        }

        if ($method === 'POST' && str_contains($path, '/restore')) {
            return 'restore';
        }

        if (in_array($method, ['PUT', 'PATCH'])) {
            return 'update';
        }

        if ($method === 'DELETE') {
            return 'delete';
        }

        return 'unknown';
    }

    private function getModelInfo(Request $request): ?array
    {
        $path = trim($request->path(), '/');
        $segments = explode('/', $path);

        // Formatos aceitos:
        // - api/<resource>/<id>
        // - api/personalizar/<resource>/<id>
        // - api/registros/<resource>/<id>
        if (count($segments) < 2 || $segments[0] !== 'api') {
            return null;
        }

        $resourceIndex = 1;

        // Ajusta índice quando há prefixos agrupados
        if (isset($segments[1]) && in_array($segments[1], ['personalizar', 'registros'])) {
            $resourceIndex = 2;
        }

        $resource = $segments[$resourceIndex] ?? null;
        $modelClass = $resource ? ($this->modelsToTrack[$resource] ?? null) : null;

        if (!$modelClass) {
            return null;
        }

        // Para POST (create), ID vem do response, não da URL
        if ($request->getMethod() === 'POST' && !str_contains($path, '/restore')) {
            return [$modelClass, 0];
        }

        $idIndex = $resourceIndex + 1;

        if (isset($segments[$idIndex]) && is_numeric($segments[$idIndex])) {
            return [$modelClass, (int) $segments[$idIndex]];
        }

        return [$modelClass, 0];
    }

    private function capturePreState(Request $request): ?array
    {
        if (!$this->isModificationRequest($request)) {
            return null;
        }

        $modelInfo = $this->getModelInfo($request);

        if (!$modelInfo) {
            return null;
        }

        [$modelClass, $modelId] = $modelInfo;

        // Para POST, não há estado anterior
        if ($request->getMethod() === 'POST' || !$modelId) {
            return null;
        }

        try {
            $model = $modelClass::withTrashed()->find($modelId);
            if ($model) {
                return [
                    'class' => $modelClass,
                    'id' => $modelId,
                    'data' => $model->toArray(),
                ];
            }
        } catch (\Exception $e) {
            \Log::error('Erro ao capturar estado anterior: ' . $e->getMessage());
        }

        return null;
    }

    private function getOldValues(Request $request, string $modelClass, int $modelId): ?array
    {
        if ($request->getMethod() === 'POST') {
            return null;
        }

        if (!$modelId) {
            return null;
        }

        try {
            $model = $modelClass::withTrashed()->find($modelId);
            if ($model) {
                return $model->toArray();
            }
        } catch (\Exception $e) {
            \Log::error('Erro ao obter valores antigos: ' . $e->getMessage());
        }

        return null;
    }

    private function getNewValues(Request $request, string $modelClass, int $modelId, Response $response): ?array
    {
        try {
            if ($request->getMethod() === 'POST') {
                // Para create, prioriza dados do response (para capturar ID)
                $body = $response->getContent();
                $json = json_decode($body ?? '', true);

                if (is_array($json)) {
                    if (isset($json['data']) && is_array($json['data'])) {
                        return $json['data'];
                    }

                    return $json;
                }

                return $request->all();
            }

            if ($request->getMethod() === 'DELETE') {
                // Para delete, não há novos valores
                return null;
            }

            // Para PUT/PATCH, tenta recuperar o modelo após atualização
            $model = $modelClass::withTrashed()->find($modelId);
            if ($model) {
                return $model->toArray();
            }

            return $request->all();
        } catch (\Exception $e) {
            return $request->all();
        }
    }

    private function calculateChanges(?array $oldValues, ?array $newValues): ?array
    {
        if (!$oldValues || !$newValues) {
            return null;
        }

        $changes = [];
        $fieldsToIgnore = ['updated_at', 'deleted_at', 'created_at', 'id', 'company_id'];

        foreach ($newValues as $key => $newValue) {
            if (in_array($key, $fieldsToIgnore)) {
                continue;
            }

            $oldValue = $oldValues[$key] ?? null;

            if ($oldValue !== $newValue) {
                $changes[$key] = [
                    'old' => $oldValue,
                    'new' => $newValue,
                ];
            }
        }

        return empty($changes) ? null : $changes;
    }
}
