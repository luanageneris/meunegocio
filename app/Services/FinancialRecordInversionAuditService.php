<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Commission;
use App\Models\FinancialRecord;
use Illuminate\Http\Request;

class FinancialRecordInversionAuditService
{
    public function __construct(private FinancialRecordAuditSnapshot $snapshot)
    {
    }

    public function snapshot(FinancialRecord $record): array
    {
        return $this->snapshot->capture($record);
    }

    public function recordUpdate(Request $request, FinancialRecord $record, array $oldValues): void
    {
        $newValues = $this->snapshot($record);
        $changes = [];

        foreach ($newValues as $field => $newValue) {
            if (in_array($field, ['id', 'company_id', 'created_at', 'updated_at', 'deleted_at'], true)
                || str_ends_with($field, '_name')) {
                continue;
            }

            $oldValue = $oldValues[$field] ?? null;
            if ($oldValue !== $newValue) {
                $changes[$field] = ['old' => $oldValue, 'new' => $newValue];
            }
        }

        $this->write($request, 'update', FinancialRecord::class, $record->id, $oldValues, $newValues, $changes);
    }

    public function recordCommissionDeletion(Request $request, Commission $commission): void
    {
        $commission->load(['collaborator' => fn ($query) => $query->withTrashed()]);
        $oldValues = $commission->attributesToArray();
        $oldValues['collaborator_name'] = $commission->collaborator?->name;
        $commission->delete();

        $this->write($request, 'delete', Commission::class, $commission->id, $oldValues, null, null);
    }

    private function write(Request $request, string $action, string $model, int $id, ?array $oldValues, ?array $newValues, ?array $changes): void
    {
        AuditLog::create([
            'company_id' => $request->user()->company_id,
            'user_id' => $request->user()->id,
            'action' => $action,
            'model' => $model,
            'model_id' => $id,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'changes' => $changes,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'endpoint' => $request->path(),
        ]);
    }
}
