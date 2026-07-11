<?php

namespace App\Services;

use App\Models\Collaborator;
use App\Models\EntryType;
use App\Models\ExitType;

class CompanyDefaultsService
{
    public const DEFAULT_LABEL = 'Não definido';
    public const DEFAULT_EXIT_TYPE_COMMISSION_LABEL = 'Comissão';

    public function ensureForCompany(int $companyId): array
    {
        $entryType = EntryType::withTrashed()
            ->where('company_id', $companyId)
            ->where('name', self::DEFAULT_LABEL)
            ->first();

        if (!$entryType) {
            $entryType = EntryType::create([
                'company_id' => $companyId,
                'name' => self::DEFAULT_LABEL,
            ]);
        } elseif ($entryType->trashed()) {
            $entryType->restore();
        }

        $exitType = $this->ensureExitType($companyId, self::DEFAULT_LABEL);
        $commissionExitType = $this->ensureExitType($companyId, self::DEFAULT_EXIT_TYPE_COMMISSION_LABEL);

        $cpf = $this->defaultCpf($companyId);
        $collaborator = Collaborator::withTrashed()
            ->where('company_id', $companyId)
            ->where('name', self::DEFAULT_LABEL)
            ->first();

        if (!$collaborator) {
            $collaborator = Collaborator::create([
                'company_id' => $companyId,
                'name' => self::DEFAULT_LABEL,
                'cpf' => $cpf,
                'active' => true,
            ]);
        } else {
            $updates = [];
            if ($collaborator->cpf !== $cpf) {
                $updates['cpf'] = $cpf;
            }
            if (!$collaborator->active) {
                $updates['active'] = true;
            }
            if (!empty($updates)) {
                $collaborator->update($updates);
            }
            if ($collaborator->trashed()) {
                $collaborator->restore();
            }
        }

        return [
            'entry_type' => $entryType,
            'exit_type' => $exitType,
            'commission_exit_type' => $commissionExitType,
            'collaborator' => $collaborator,
        ];
    }

    private function ensureExitType(int $companyId, string $name): ExitType
    {
        $exitType = ExitType::withTrashed()
            ->where('company_id', $companyId)
            ->where('name', $name)
            ->first();

        if (!$exitType) {
            return ExitType::create([
                'company_id' => $companyId,
                'name' => $name,
            ]);
        }

        if ($exitType->trashed()) {
            $exitType->restore();
        }

        return $exitType;
    }

    private function defaultCpf(int $companyId): string
    {
        $digits = str_pad((string) $companyId, 11, '0', STR_PAD_LEFT);

        return substr($digits, 0, 3)
            . '.' . substr($digits, 3, 3)
            . '.' . substr($digits, 6, 3)
            . '-' . substr($digits, 9, 2);
    }
}