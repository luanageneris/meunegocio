<?php

namespace App\Observers;

use App\Models\Commission;
use App\Models\FinancialRecord;
use Illuminate\Validation\ValidationException;

class FinancialRecordObserver
{
    private function importedCommissions(FinancialRecord $record)
    {
        return Commission::forCompany($record->company_id)
            ->where('financial_record_id', $record->id)
            ->where('notes', 'Comissão importada de planilha');
    }

    public function updating(FinancialRecord $record): void
    {
        if ($record->type === 'exit' && !$record->collaborator_id && $this->importedCommissions($record)->exists()) {
            throw ValidationException::withMessages([
                'collaborator_id' => 'Uma comissão importada deve ter um colaborador.',
            ]);
        }
    }

    public function updated(FinancialRecord $record): void
    {
        if ($record->type !== 'exit') {
            return;
        }

        $this->importedCommissions($record)->update([
            'collaborator_id' => $record->collaborator_id,
            'date' => $record->date,
            'amount' => $record->final_amount,
        ]);
    }

    public function deleted(FinancialRecord $record): void
    {
        $this->importedCommissions($record)->update(['deleted_at' => $record->deleted_at]);
    }

    public function restoring(FinancialRecord $record): void
    {
        $this->importedCommissions($record)
            ->onlyTrashed()
            ->where('deleted_at', $record->deleted_at)
            ->restore();
    }
}
