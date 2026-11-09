<?php

namespace App\Services;

use App\Models\FinancialRecord;

class FinancialRecordAuditSnapshot
{
    public function capture(FinancialRecord $record): array
    {
        $record->load([
            'entryType' => fn ($query) => $query->withTrashed(),
            'exitType' => fn ($query) => $query->withTrashed(),
            'collaborator' => fn ($query) => $query->withTrashed(),
            'serviceType' => fn ($query) => $query->withTrashed(),
        ]);
        $values = $record->attributesToArray();
        $values['entry_type_name'] = $record->entryType?->name;
        $values['exit_type_name'] = $record->exitType?->name;
        $values['collaborator_name'] = $record->collaborator?->name;
        $values['service_type_name'] = $record->serviceType?->name;

        return $values;
    }
}
