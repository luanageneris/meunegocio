<?php

namespace App\Services;

use App\Models\Collaborator;
use App\Models\EntryType;
use App\Models\ExitType;
use App\Models\ServiceType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MasterDataService
{
    private const REFERENCES = [
        EntryType::class => ['financial_records' => 'entry_type_id'],
        ExitType::class => ['financial_records' => 'exit_type_id'],
        ServiceType::class => ['financial_records' => 'service_type_id'],
        Collaborator::class => [
            'financial_records' => 'collaborator_id',
            'commissions' => 'collaborator_id',
            'work_shifts' => 'collaborator_id',
        ],
    ];

    public function delete(Model $item, ?int $replacementId): void
    {
        DB::transaction(function () use ($item, $replacementId) {
            $item = $item->newQuery()->whereKey($item->getKey())->lockForUpdate()->firstOrFail();
            $references = self::REFERENCES[$item::class];
            $inUse = false;

            foreach ($references as $table => $column) {
                $inUse = $inUse || DB::table($table)
                    ->where('company_id', $item->company_id)
                    ->where($column, $item->getKey())->exists();
            }

            if ($inUse && !$replacementId) {
                abort(response()->json([
                    'message' => 'Este item está em uso. Escolha um substituto para os registros ou coloque o item para dormir.',
                    'code' => 'replacement_required',
                ], 409));
            }

            if ($replacementId) {
                $replacement = $item->newQuery()->where('company_id', $item->company_id)
                    ->where('active', true)->whereKey($replacementId)->lockForUpdate()->first();

                if (!$replacement || $replacement->is($item)) {
                    throw ValidationException::withMessages([
                        'replacement_id' => 'Escolha outro item ativo da sua empresa como substituto.',
                    ]);
                }

                if ($item instanceof Collaborator) {
                    $dates = DB::table('work_shifts')->where('company_id', $item->company_id)
                        ->where('collaborator_id', $item->getKey())->select('date');
                    if (DB::table('work_shifts')->where('company_id', $item->company_id)
                        ->where('collaborator_id', $replacementId)->whereIn('date', $dates)->exists()) {
                        throw ValidationException::withMessages([
                            'replacement_id' => 'Os colaboradores possuem turnos na mesma data. Escolha outro substituto ou coloque o colaborador para dormir.',
                        ]);
                    }
                }

                foreach ($references as $table => $column) {
                    DB::table($table)->where('company_id', $item->company_id)
                        ->where($column, $item->getKey())->update([$column => $replacementId]);
                }
            }

            $item->delete();
        });
    }
}
