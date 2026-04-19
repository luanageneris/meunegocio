<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Models\AuditLog;

class TrackChanges
{
    protected $postEndpointsToIgnore = [
        'financial-records/between-dates',
        'financial-records/daily-totals',
        'work-shifts/monthly-schedule',
        'daily-closures/summary',
        'commissions/summary-by-collaborator',
        'commissions/summary-by-month',
        'balance/daily-progression',
        'balance/monthly-summary',
        'balance/yearly-summary',
        'support',
    ];

    protected $endpointOverrides = [
        'financial-records/clear-all' => ['action' => 'delete', 'model' => 'App\\Models\\FinancialRecord'],
        'commissions/mark-as-paid' => ['action' => 'update', 'model' => 'App\\Models\\Commission'],
        'commissions/generate-from-date' => ['action' => 'create', 'model' => 'App\\Models\\Commission'],
        'daily-closures/reopen' => ['action' => 'update', 'model' => 'App\\Models\\DailyClosure'],
        'collaborators/get-or-create' => ['action' => 'create', 'model' => 'App\\Models\\Collaborator'],
        'import' => ['action' => 'create', 'model' => 'App\\Models\\ImportLog'],
    ];
    
    protected $modelsToTrack = [
        'company' => 'App\Models\Company',
        'collaborators' => 'App\Models\Collaborator',
        'financial-records' => 'App\Models\FinancialRecord',
        'commissions' => 'App\Models\Commission',
        'work-shifts' => 'App\Models\WorkShift',
        'service-types' => 'App\Models\ServiceType',
        'entry-types' => 'App\Models\EntryType',
        'exit-types' => 'App\Models\ExitType',
        'daily-closures' => 'App\Models\DailyClosure',
        'balances' => 'App\Models\Balance',
        'import' => 'App\Models\ImportLog',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $preState = $this->capturePreState($request);

        $response = $next($request);

        // Só registrar se autenticado e for request de modificação
        if ($request->user() && $this->isModificationRequest($request) && $this->isSuccessfulResponse($response)) {
            $this->logAudit($request, $response, $preState);
        }

        return $response;
    }

    private function isModificationRequest(Request $request): bool
    {
        $method = $request->getMethod();

        if (!in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'])) {
            return false;
        }

        if ($method === 'POST' && $this->isIgnoredPostEndpoint($request->path())) {
            return false;
        }

        return true;
    }

    private function isIgnoredPostEndpoint(string $path): bool
    {
        foreach ($this->postEndpointsToIgnore as $endpoint) {
            if (str_contains($path, $endpoint)) {
                return true;
            }
        }

        return false;
    }

    private function isSuccessfulResponse(Response $response): bool
    {
        $status = $response->getStatusCode();
        return $status >= 200 && $status < 400;
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

            [$oldValues, $newValues, $changes] = $this->normalizeAuditPayload(
                $request,
                $response,
                $modelId,
                $oldValues,
                $newValues,
                $changes
            );

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

        $override = $this->resolveEndpointOverride($path);
        if ($override && isset($override['action'])) {
            return $override['action'];
        }

        if ($method === 'POST' && !str_contains($path, '/restore')) {
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

        $override = $this->resolveEndpointOverride($path);
        if ($override && isset($override['model'])) {
            return [$override['model'], $this->extractIdFromSegments($segments)];
        }

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

        $implicitModelId = $this->resolveImplicitModelId($request, $resource, $modelClass);

        // Para POST (create), ID vem do response, não da URL
        if ($request->getMethod() === 'POST' && !str_contains($path, '/restore')) {
            return [$modelClass, 0];
        }

        $resolvedId = $this->extractIdFromSegments($segments, $resourceIndex + 1);

        if ($resolvedId > 0) {
            return [$modelClass, $resolvedId];
        }

        return [$modelClass, $implicitModelId];
    }

    private function resolveImplicitModelId(Request $request, ?string $resource, string $modelClass): int
    {
        if ($resource === 'company' && $modelClass === 'App\Models\Company') {
            return (int) ($request->user()?->company_id ?? 0);
        }

        return 0;
    }

    private function extractIdFromSegments(array $segments, int $startIndex = 0): int
    {
        for ($i = $startIndex; $i < count($segments); $i++) {
            if (is_numeric($segments[$i])) {
                return (int) $segments[$i];
            }
        }

        return 0;
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

                if (is_array($json) && !empty($json)) {
                    if (isset($json['data']) && is_array($json['data'])) {
                        return $json['data'];
                    }

                    return $json;
                }

                return $this->sanitizeRequestData($request->all());
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

            return $this->sanitizeRequestData($request->all());
        } catch (\Exception $e) {
            return $this->sanitizeRequestData($request->all());
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

    private function resolveEndpointOverride(string $path): ?array
    {
        foreach ($this->endpointOverrides as $pattern => $override) {
            if (str_contains($path, $pattern)) {
                return $override;
            }
        }

        return null;
    }

    private function sanitizeRequestData(array $data): array
    {
        $sensitiveKeys = [
            'password',
            'password_confirmation',
            'token',
            'access_token',
            'refresh_token',
        ];

        foreach ($sensitiveKeys as $key) {
            if (array_key_exists($key, $data)) {
                $data[$key] = '***';
            }
        }

        return $data;
    }

    private function normalizeAuditPayload(
        Request $request,
        Response $response,
        int $modelId,
        ?array $oldValues,
        ?array $newValues,
        ?array $changes
    ): array {
        $requestData = $this->sanitizeRequestData($request->all());
        $responseJson = json_decode($response->getContent() ?? '', true);

        if ($modelId > 0) {
            return [$oldValues, $newValues, $changes];
        }

        // Para ações em lote/sem id explícito, mantém um formato padronizado
        // para o histórico sempre ter contexto de rastreio.
        $normalizedNewValues = [
            'request' => $requestData,
            'response' => is_array($responseJson) ? $responseJson : null,
            'endpoint' => $request->path(),
        ];

        if (is_array($newValues) && !empty($newValues)) {
            $normalizedNewValues['payload'] = $newValues;
        }

        if (!$oldValues && in_array($request->method(), ['DELETE', 'PUT', 'PATCH'])) {
            $oldValues = [
                'request' => $requestData,
                'endpoint' => $request->path(),
            ];
        }

        return [$oldValues, $normalizedNewValues, $changes];
    }
}
